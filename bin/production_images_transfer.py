#!/usr/bin/env python3
"""Package GitHub-built images and import them over pinned SSH; never run containers."""
from __future__ import annotations

import fcntl
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import shlex
import shutil
import signal
import stat
import subprocess
import sys
import tarfile
import tempfile

SCHEMA = "soamco-image-transfer-v1"
TARGETS = ("php", "web")
LOCK = Path("/tmp/soamco-production-deploy.lock")
RESERVE = 1024 * 1024 * 1024


class TransferError(Exception):
    pass


def require(condition, message):
    if not condition:
        raise TransferError(message)


def digest(stream):
    result = hashlib.sha256()
    for chunk in iter(lambda: stream.read(1024 * 1024), b""):
        result.update(chunk)
    return result.hexdigest()


def file_digest(path):
    with path.open("rb") as stream:
        return digest(stream)


def tag(target, commit):
    require(target in TARGETS and re.fullmatch(r"[a-f0-9]{40}", commit), "Invalid target or commit")
    return "soamco-budget-" + target + ":sha-" + commit


def members(archive):
    entries = {}
    for info in archive:
        name = PurePosixPath(info.name)
        require(not name.is_absolute() and ".." not in name.parts and info.name == name.as_posix() and
                (info.isfile() or info.isdir()) and not info.issparse() and info.name not in entries,
                "Unsafe or duplicate archive member")
        entries[info.name] = info
    return entries


def json_member(archive, entries, name):
    info = entries[name]
    require(info.isfile() and 0 < info.size <= 1024 * 1024, "Invalid image metadata")
    return json.load(archive.extractfile(info))


def inspect_archive(path, target, commit, canonical=None):
    with tarfile.open(path, "r:") as archive:
        entries = members(archive)
        manifest = json_member(archive, entries, "manifest.json")
        require(isinstance(manifest, list) and len(manifest) == 1, "Expected exactly one image per archive")
        image = manifest[0]
        require(image["RepoTags"] == [tag(target, commit)], "Unexpected image tags")
        config = json_member(archive, entries, image["Config"])
        require(config["os"] == "linux" and config["architecture"] == "amd64" and
                config["config"]["Labels"]["org.opencontainers.image.revision"] == commit,
                "Image platform or revision mismatch")
        layers = image["Layers"]
        require(config["rootfs"]["type"] == "layers" and len(layers) == len(config["rootfs"]["diff_ids"]),
                "Invalid layer metadata")
        for name, expected in zip(layers, config["rootfs"]["diff_ids"]):
            require(entries[name].isfile(), "Invalid layer file")
            require("sha256:" + digest(archive.extractfile(entries[name])) == expected, "Layer checksum mismatch")
        image_id = "sha256:" + digest(archive.extractfile(entries[image["Config"]]))
        required = {"manifest.json", image["Config"], *layers}
        if canonical is None:
            require(set(entries) == required, "Unexpected canonical image content")
        else:
            # Keep only Docker v1.2 manifest/config/layers. Drop OCI indexes and
            # exporter metadata so docker load cannot import additional references.
            with tarfile.open(canonical, "x", format=tarfile.PAX_FORMAT) as output:
                for name in sorted(required):
                    info = tarfile.TarInfo(name)
                    info.size, info.mode = entries[name].size, 0o600
                    output.addfile(info, archive.extractfile(entries[name]))
        return image_id


def pack(commit, directory):
    records = []
    for target in TARGETS:
        destination = directory / (target + ".tar")
        image_id = inspect_archive(directory / (target + ".export.tar"), target, commit, canonical=destination)
        require(inspect_archive(destination, target, commit) == image_id, "Canonical image verification failed")
        records.append({"archive": target + ".tar", "tag": tag(target, commit), "id": image_id,
                        "sha256": file_digest(destination)})
    record = {"schema": SCHEMA, "commit": commit, "images": records}
    (directory / "manifest.json").write_text(json.dumps(record, sort_keys=True))
    with tarfile.open(directory / "images.tar", "x") as archive:
        for name in ("php.tar", "web.tar", "manifest.json"):
            archive.add(directory / name, arcname=name, recursive=False)
    return record


def command(arguments, **options):
    environment = {key: value for key, value in os.environ.items() if not key.startswith(("DOCKER_", "SOAMCO_SSH_"))}
    result = subprocess.run(arguments, env=environment, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                            timeout=3600, check=False, **options)
    require(result.returncode == 0, "Local/SSH operation failed; diagnostics withheld; no containers were changed")
    return result.stdout


def acquire_lock():
    try:
        descriptor = os.open(LOCK, os.O_CREAT | os.O_EXCL | os.O_RDONLY | os.O_NOFOLLOW, 0o600)
    except FileExistsError:
        descriptor = os.open(LOCK, os.O_RDONLY | os.O_NOFOLLOW)
    lock = os.fdopen(descriptor, "rb")
    try:
        require(stat.S_ISREG(os.fstat(lock.fileno()).st_mode), "Unsafe deployment lock")
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        require(os.fstat(lock.fileno()).st_ino == LOCK.lstat().st_ino and not LOCK.is_symlink(), "Deployment lock changed")
        return lock
    except BaseException:
        lock.close()
        raise


def receive(commit, checksum, size):
    tag("php", commit)
    require(re.fullmatch(r"[a-f0-9]{64}", checksum) and re.fullmatch(r"[1-9][0-9]{0,13}", size), "Invalid transfer metadata")
    size = int(size)
    # Access to this existing lock and Docker must already be authorized; no sudo
    # or permission changes are attempted. Shared lock inode is never removed.
    with acquire_lock(), tempfile.TemporaryDirectory(prefix="soamco-images-", dir="/tmp") as temporary:
        directory = Path(temporary)
        require(shutil.disk_usage(directory).free >= 2 * size + RESERVE, "Insufficient temporary disk space")
        incoming = directory / "incoming.tar"
        count = 0
        with incoming.open("xb") as output:
            while count <= size:
                chunk = sys.stdin.buffer.read(min(1024 * 1024, size + 1 - count))
                if not chunk:
                    break
                count += len(chunk)
                output.write(chunk)
        require(count == size and file_digest(incoming) == checksum, "Transfer size or SHA-256 mismatch")
        with tarfile.open(incoming, "r:") as archive:
            entries = members(archive)
            require(set(entries) == {"php.tar", "web.tar", "manifest.json"} and
                    all(info.isfile() for info in entries.values()), "Invalid transfer bundle")
            record = json_member(archive, entries, "manifest.json")
            for name in ("php.tar", "web.tar"):
                with archive.extractfile(entries[name]) as source, (directory / name).open("xb") as output:
                    shutil.copyfileobj(source, output, 1024 * 1024)
        require(set(record) == {"schema", "commit", "images"} and record["schema"] == SCHEMA and
                record["commit"] == commit and len(record["images"]) == 2, "Invalid transfer manifest")
        for target, item in zip(TARGETS, record["images"]):
            require(set(item) == {"archive", "tag", "id", "sha256"} and item["archive"] == target + ".tar" and
                    item["tag"] == tag(target, commit), "Unexpected transfer image")
            path = directory / item["archive"]
            require(file_digest(path) == item["sha256"] and inspect_archive(path, target, commit) == item["id"],
                    "Image integrity verification failed")
        endpoint = json.loads(command(["docker", "context", "inspect", "default", "--format", "{{json .Endpoints.docker}}"])).get("Host", "")
        require(endpoint.startswith("unix:///") and not any(ord(c) < 32 for c in endpoint), "Only a local Docker socket is allowed")
        docker = ["docker", "--host", endpoint]
        root = json.loads(command(docker + ["info", "--format", "{{json .DockerRootDir}}"]))
        require(Path(root).is_dir() and shutil.disk_usage(root).free >= 2 * size + RESERVE, "Insufficient Docker disk space")
        # Check both tags before loading either image. Never retarget an existing tag.
        existing = []
        for item in record["images"]:
            rows = command(docker + ["image", "ls", "--no-trunc", "--format", "{{json .}}", "--filter", "reference=" + item["tag"]])
            rows = [json.loads(line) for line in rows.splitlines()]
            require(all(row["Repository"] + ":" + row["Tag"] == item["tag"] and row["ID"] == item["id"] for row in rows),
                    "Existing commit tag differs; import refused")
            existing.append(bool(rows))
        for item, present in zip(record["images"], existing):
            if not present:
                command(docker + ["image", "load", "--quiet", "--input", str(directory / item["archive"])])
            loaded = json.loads(command(docker + ["image", "inspect", item["tag"]]))
            require(len(loaded) == 1 and loaded[0]["Id"] == item["id"] and loaded[0]["Os"] == "linux" and
                    loaded[0]["Architecture"] == "amd64" and loaded[0]["Config"]["Labels"]["org.opencontainers.image.revision"] == commit,
                    "Loaded image verification failed; inspect images manually, no containers were changed")
        print("SUCCESS images verified and loaded; no deployment or container operations")


def send(commit, directory):
    tag("php", commit)
    host = os.environ.get("SOAMCO_SSH_HOST", "")
    user = os.environ.get("SOAMCO_SSH_USER", "")
    port = os.environ.get("SOAMCO_SSH_PORT", "22") or "22"
    require(re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9.-]{0,252}", host) and re.fullmatch(r"[a-z_][a-z0-9_-]{0,31}", user) and
            port.isdigit() and 1 <= int(port) <= 65535, "Configure a valid SSH host, user and port")
    key = os.environ.get("SOAMCO_SSH_PRIVATE_KEY", "")
    hosts = os.environ.get("SOAMCO_SSH_KNOWN_HOSTS", "")
    require(key.strip() and hosts.strip(), "Pinned known_hosts and private SSH key are required")
    bundle = directory / "images.tar"
    with tempfile.TemporaryDirectory(prefix="soamco-ssh-", dir=os.environ["RUNNER_TEMP"]) as temporary:
        private = Path(temporary)
        for name, value in (("identity", key), ("known_hosts", hosts)):
            descriptor = os.open(private / name, os.O_CREAT | os.O_EXCL | os.O_WRONLY | os.O_NOFOLLOW, 0o600)
            with os.fdopen(descriptor, "w") as output:
                output.write(value.rstrip() + "\n")
        options = ["ssh", "-T", "-F", "/dev/null", "-p", port, "-i", str(private / "identity")]
        for option in ("BatchMode=yes", "StrictHostKeyChecking=yes", "IdentitiesOnly=yes", "IdentityAgent=none",
                       "PreferredAuthentications=publickey", "PasswordAuthentication=no", "KbdInteractiveAuthentication=no",
                       "ForwardAgent=no", "ClearAllForwardings=yes", "GlobalKnownHostsFile=/dev/null", "ConnectTimeout=15",
                       "ServerAliveInterval=15", "ServerAliveCountMax=3", "UserKnownHostsFile=" + str(private / "known_hosts")):
            options.extend(["-o", option])
        code = Path(__file__).read_text()
        remote = "python3 -c " + shlex.quote(code) + " receive " + commit + " " + file_digest(bundle) + " " + str(bundle.stat().st_size)
        with bundle.open("rb") as stream:
            command(options + [user + "@" + host, remote], stdin=stream)
    record = json.loads((directory / "manifest.json").read_text())
    for target, item in zip(TARGETS, record["images"]):
        require(item["tag"] == tag(target, commit) and re.fullmatch(r"sha256:[a-f0-9]{64}", item["id"]), "Invalid local image identifiers")
        print("VERIFIED " + item["tag"] + " " + item["id"])
    print("SUCCESS verified images transferred and loaded; no deployment")


def interrupted(signum, frame):
    raise TransferError("Transfer interrupted; no container operations were requested")


def main():
    os.umask(0o077)
    signal.signal(signal.SIGINT, interrupted)
    signal.signal(signal.SIGTERM, interrupted)
    try:
        action, *arguments = sys.argv[1:]
        if action == "pack" and len(arguments) == 2:
            pack(arguments[0], Path(arguments[1]))
        elif action == "send" and len(arguments) == 2:
            send(arguments[0], Path(arguments[1]))
        elif action == "receive" and len(arguments) == 3:
            receive(*arguments)
        else:
            raise TransferError("Use pack/send COMMIT DIRECTORY or receive COMMIT SHA256 SIZE")
        return 0
    except (TransferError, OSError, ValueError, KeyError, TypeError, EOFError, tarfile.TarError, subprocess.TimeoutExpired) as error:
        print("ERROR " + (str(error) if isinstance(error, TransferError) else type(error).__name__) + "; existing containers preserved", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
