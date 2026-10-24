"""Shared PostgreSQL artifact validation; no private identities or network access."""

from __future__ import annotations

import argparse
import base64
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import sys


SCHEMA = "soamco-postgres-age-v1"
LEGACY = ("database.dump", "database.list", "SHA256SUMS")
ENCRYPTED = ("database.dump.age", "database.list", "manifest.json", "SHA256SUMS")
LEGACY_SUMS = re.compile(rb"([a-f0-9]{64})  database.dump\n([a-f0-9]{64})  database.list\n")
ENCRYPTED_SUMS = re.compile(rb"([a-f0-9]{64})  database.dump.age\n([a-f0-9]{64})  database.list\n([a-f0-9]{64})  manifest.json\n")


class FormatError(ValueError):
    pass


def digest(path: Path) -> str:
    result = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            result.update(chunk)
    return result.hexdigest()


def root_owned(info) -> bool:
    return info.st_uid == 0


def safe_file(path: Path, root: bool = False) -> None:
    info = path.lstat()
    if not stat.S_ISREG(info.st_mode) or info.st_nlink != 1 or info.st_size == 0 or info.st_mode & 0o022 or (root and not root_owned(info)):
        raise FormatError("Expected a protected nonempty regular file without symlinks or hardlinks")


def parse_checksums(data: bytes) -> tuple[tuple[str, ...], dict[str, str]]:
    if len(data) > 512:
        raise FormatError("Checksum manifest is too large")
    for files, pattern in ((LEGACY, LEGACY_SUMS), (ENCRYPTED, ENCRYPTED_SUMS)):
        match = pattern.fullmatch(data)
        if match is not None:
            return files, {name: match[index].decode("ascii") for index, name in enumerate(files[:-1], 1)}
    raise FormatError("Unrecognized checksum manifest; arbitrary paths are not accepted")


def verify_cipher(path: Path, plaintext_bytes: int) -> None:
    with path.open("rb") as stream:
        if stream.readline(128) != b"age-encryption.org/v1\n":
            raise FormatError("Not an age v1 encrypted archive")
        x25519 = False
        while stream.tell() < 65536:
            line = stream.readline(256)
            if line.startswith(b"-> X25519 "):
                x25519 = True
            if re.fullmatch(rb"--- [A-Za-z0-9+/]{43}\n", line):
                if len(base64.b64decode(line[4:-1] + b"=", validate=True)) != 32:
                    raise FormatError("Malformed age header")
                break
            if not line.endswith(b"\n"):
                raise FormatError("Malformed age header")
        else:
            raise FormatError("Oversized age header")
        header_bytes = stream.tell()
    expected = header_bytes + 16 + plaintext_bytes + 16 * max(1, (plaintext_bytes + 65535) // 65536)
    if not x25519 or path.stat().st_size != expected:
        raise FormatError("Unexpected or truncated age payload")


def verify_directory(directory: Path, *, root: bool = False, receipt: bool = False) -> tuple[tuple[str, ...], dict | None]:
    info = directory.lstat()
    if not stat.S_ISDIR(info.st_mode) or info.st_mode & 0o022 or (root and not root_owned(info)):
        raise FormatError("Unsafe backup directory")
    safe_file(directory / "SHA256SUMS", root)
    files, hashes = parse_checksums((directory / "SHA256SUMS").read_bytes())
    expected = set(files) | ({".soamco-nas-receipt.json"} if receipt else set())
    if {path.name for path in directory.iterdir()} != expected:
        raise FormatError("Unexpected or missing backup files; mixed plaintext/encrypted layouts are refused")
    for file in files:
        safe_file(directory / file, root)
        if file in hashes and digest(directory / file) != hashes[file]:
            raise FormatError("SHA-256 verification failed")
    if files == LEGACY:
        with (directory / "database.dump").open("rb") as stream:
            if stream.read(5) != b"PGDMP":
                raise FormatError("Not a PostgreSQL custom archive")
        return files, None
    if (directory / "manifest.json").stat().st_size > 4096:
        raise FormatError("Oversized encrypted manifest")
    record = json.loads((directory / "manifest.json").read_text())
    if (not isinstance(record, dict) or record.get("schema") != SCHEMA or record.get("postgres_major") != 17 or
            record.get("pg_restore_list_verified") is not True or record.get("decryption_verified_on_vps") is not False or
            type(record.get("plaintext_bytes")) is not int or record["plaintext_bytes"] < 5 or
            re.fullmatch(r"[a-f0-9]{64}", str(record.get("plaintext_sha256", ""))) is None or
            re.fullmatch(r"[a-f0-9]{64}", str(record.get("recipient_sha256", ""))) is None):
        raise FormatError("Unrecognized encrypted PostgreSQL manifest")
    verify_cipher(directory / "database.dump.age", record["plaintext_bytes"])
    return files, record


def recipient(config_path: Path) -> str:
    if os.geteuid() != 0 or not config_path.is_absolute():
        raise FormatError("Encryption configuration must be read by root from an absolute path")
    safe_file(config_path, root=True)
    config = json.loads(config_path.read_text())
    path = Path(config["recipient_file"])
    if not path.is_absolute():
        raise FormatError("Recipient path must be absolute")
    safe_file(path, root=True)
    for item in (config_path, path):
        for parent in item.parents:
            info = parent.lstat()
            if not stat.S_ISDIR(info.st_mode) or not root_owned(info) or (info.st_mode & 0o022 and not info.st_mode & stat.S_ISVTX):
                raise FormatError("Encryption configuration requires protected root-owned ancestors")
    if path.stat().st_size > 128:
        raise FormatError("Recipient file must contain one public X25519 recipient only")
    value = path.read_text().strip()
    if re.fullmatch(r"age1[023456789acdefghjklmnpqrstuvwxyz]{58}", value) is None:
        raise FormatError("Private identities and non-native recipients are refused on the VPS")
    return value


def encrypt(directory: Path, config_path: Path) -> None:
    public = recipient(config_path)
    files, _ = verify_directory(directory, root=True)
    if files != LEGACY:
        raise FormatError("Encryption requires a verified private plaintext staging directory")
    if directory.stat().st_mode & 0o077:
        raise FormatError("Plaintext staging must be private to root")
    plain = directory / "database.dump"
    ciphertext = directory / "database.dump.age"
    record = {"schema": SCHEMA, "postgres_major": 17, "pg_restore_list_verified": True,
              "decryption_verified_on_vps": False, "plaintext_bytes": plain.stat().st_size,
              "plaintext_sha256": digest(plain), "recipient_sha256": hashlib.sha256(public.encode()).hexdigest()}
    environment = {key: value for key, value in os.environ.items() if not key.startswith("AGE_")}
    result = subprocess.run(["age", "--encrypt", "--recipient", public, "--output", str(ciphertext), str(plain)],
                            stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, env=environment, timeout=1800, check=False)
    if result.returncode != 0:
        raise FormatError(f"age encryption failed (exit={result.returncode}); diagnostics withheld")
    safe_file(ciphertext, root=True)
    verify_cipher(ciphertext, record["plaintext_bytes"])
    (directory / "manifest.json").write_text(json.dumps(record, sort_keys=True))
    plain.unlink()
    (directory / "SHA256SUMS").write_text("".join(digest(directory / name) + "  " + name + "\n" for name in ENCRYPTED[:-1]))
    verify_directory(directory, root=True)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("action", choices=("check-config", "encrypt", "verify"))
    parser.add_argument("directory", nargs="?", type=Path)
    parser.add_argument("--config", type=Path, default=Path("/etc/soamco-backup/files.json"))
    args = parser.parse_args()
    try:
        if args.action == "check-config":
            recipient(args.config)
        elif args.directory is None:
            parser.error("A backup directory is required")
        elif args.action == "encrypt":
            encrypt(args.directory, args.config)
        else:
            verify_directory(args.directory, root=True)
        return 0
    except (OSError, ValueError, KeyError, TypeError, subprocess.TimeoutExpired) as error:
        print("ERROR " + (str(error) if isinstance(error, FormatError) else type(error).__name__), file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
