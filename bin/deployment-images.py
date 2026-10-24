#!/usr/bin/env python3
"""Resolve commit-specific prepared images; never build or start containers."""
from __future__ import annotations

import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys

PHP_SERVICES = ("app", "logs-init", "maintenance", "horizon", "scheduler", "reverb")


class PreflightError(ValueError):
    pass


def require(condition, message):
    if not condition:
        raise PreflightError(message)


def docker(*arguments):
    result = subprocess.run(["docker", *arguments], stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=1800, check=False)
    require(result.returncode == 0, "Docker image operation failed; check local images or GHCR read access (diagnostics withheld)")
    return result.stdout


def ghcr_credentials():
    path = Path(os.environ.get("DOCKER_CONFIG", str(Path.home() / ".docker"))) / "config.json"
    config = json.loads(path.read_text())
    auth = config.get("auths", {}).get("ghcr.io", {})
    require(auth.get("auth") or auth.get("identitytoken") or config.get("credHelpers", {}).get("ghcr.io") or config.get("credsStore"),
            "Authenticate to private GHCR with read:packages before deployment; no login is performed")


def resolve(mode, commit, directory):
    require(mode in ("ghcr", "ssh") and re.fullmatch(r"[a-f0-9]{40}", commit), "Invalid prepared image mode or commit")
    namespace = os.environ.get("SOAMCO_IMAGE_REPOSITORY", "").lower()
    require(re.fullmatch(r"[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*", namespace), "Set SOAMCO_IMAGE_REPOSITORY=owner/repository matching GitHub Actions")
    if mode == "ghcr":
        ghcr_credentials()
    model = json.loads((directory / "compose-before-images.json").read_text())
    args = model["services"]["app"]["build"]["args"]
    values = sorted((name, str(value)) for name, value in args.items() if name.startswith("VITE_"))
    fingerprint = hashlib.sha256(json.dumps(values, separators=(",", ":"), ensure_ascii=False).encode()).hexdigest()
    info = json.loads(docker("info", "--format", "{{json .}}"))
    root = Path(info["DockerRootDir"])
    require(info["OSType"] == "linux" and info["Architecture"] in ("amd64", "x86_64") and
            root.is_dir() and shutil.disk_usage(root).free >= 8 * 1024**3 and os.statvfs(root).f_favail >= 50000,
            "Prepared deployment requires a linux/amd64 Docker daemon and disk headroom")
    ids = []
    for target in ("php", "web"):
        reference = ("ghcr.io/" + namespace + "/" + target if mode == "ghcr" else "soamco-budget-" + target) + ":sha-" + commit
        override = os.environ.get("SOAMCO_" + target.upper() + "_IMAGE", "")
        require(not override or override == reference, "Explicit image override conflicts with the commit-specific prepared image")
        if mode == "ghcr":
            docker("pull", "--platform", "linux/amd64", reference)
        images = json.loads(docker("image", "inspect", reference))
        require(len(images) == 1, "Expected a single prepared image")
        image = images[0]
        labels = image.get("Config", {}).get("Labels") or {}
        require(image.get("Os") == "linux" and image.get("Architecture") == "amd64", "Prepared image must target linux/amd64")
        require(labels.get("org.opencontainers.image.revision") == commit and
                str(labels.get("org.opencontainers.image.source", "")).lower() == "https://github.com/" + namespace,
                "Prepared image revision or repository does not match the deployed commit")
        require(labels.get("io.soamco.frontend-config.sha256") == fingerprint,
                "Prepared frontend build variables differ from the production Compose configuration")
        require(re.fullmatch(r"sha256:[a-f0-9]{64}", str(image.get("Id", ""))), "Invalid prepared image ID")
        if mode == "ghcr":
            require(any(re.fullmatch(re.escape("ghcr.io/" + namespace + "/" + target) + r"@sha256:[a-f0-9]{64}", value)
                        for value in image.get("RepoDigests", [])), "Missing verified GHCR repository digest")
        ids.append(image["Id"])
    # These IDs are resolved once and used by every one-off/runtime PHP service.
    lines = ["services:"]
    for service, image_id in [(name, ids[0]) for name in PHP_SERVICES] + [("web", ids[1])]:
        lines.extend(["  " + service + ":", "    image: " + image_id, "    pull_policy: never"])
        if service in ("app", "web"):
            lines.append("    build: !reset null")
    (directory / "prepared.yml").write_text("\n".join(lines) + "\n")
    (directory / "image-ids").write_text(" ".join(ids) + "\n")


def verify_config(php_id, web_id):
    config = json.load(sys.stdin)
    services = config["services"]
    for service, image_id in [(name, php_id) for name in PHP_SERVICES] + [("web", web_id)]:
        item = services[service]
        require(item["image"] == image_id and item.get("pull_policy") == "never" and not item.get("build"),
                "Prepared Compose configuration permits an unexpected image, pull or build")
    env = services["app"]["environment"]
    require(env.get("APP_ENV") == "production" and str(env.get("APP_DEBUG")).lower() in ("false", "0") and
            env.get("APP_KEY") and env.get("DB_CONNECTION") == "pgsql" and
            all(env.get(key) for key in ("DB_HOST", "DB_DATABASE", "DB_USERNAME")),
            "Production application/database configuration is missing or invalid (values withheld)")


def main():
    os.umask(0o077)
    try:
        if sys.argv[1:2] == ["resolve"] and len(sys.argv) == 5:
            resolve(sys.argv[2], sys.argv[3], Path(sys.argv[4]))
        elif sys.argv[1:2] == ["config"] and len(sys.argv) == 4:
            verify_config(sys.argv[2], sys.argv[3])
        else:
            raise PreflightError("Use resolve MODE COMMIT DIRECTORY or config PHP_ID WEB_ID")
        return 0
    except PreflightError as error:
        print("ERROR " + str(error) + "; no fallback", file=sys.stderr)
        return 1
    except (OSError, ValueError, KeyError, TypeError, subprocess.TimeoutExpired):
        print("ERROR prepared image/configuration preflight failed; verify commit, platform, repository, Docker credentials and production environment; no fallback", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
