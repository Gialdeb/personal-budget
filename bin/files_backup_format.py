"""Read-only validation of published encrypted file backups on the NAS."""

from __future__ import annotations

import datetime as dt
import hashlib
import json
from pathlib import Path
import re
import stat

from postgres_backup_format import digest, safe_file, verify_cipher

NAME = re.compile(r"files-encrypted-(\d{8}T\d{6}Z)-[a-f0-9]{10}")
FILES = ("files.tar.gz.age", "manifest.json", "SHA256SUMS")
SUMS = re.compile(rb"([a-f0-9]{64})  files.tar.gz.age\n([a-f0-9]{64})  manifest.json\n")
RECEIPT = ".soamco-nas-receipt.json"
SOURCE = "soamco-backup@100.73.11.75:/soamco"
LEGACY_SOURCE = "soamco-backup@100.73.11.75:/var/backups/soamco"


def backup_time(name: str) -> dt.datetime:
    match = NAME.fullmatch(name)
    if match is None:
        raise ValueError("Unrecognized encrypted files backup name")
    return dt.datetime.strptime(match[1], "%Y%m%dT%H%M%SZ").replace(tzinfo=dt.timezone.utc)


def parse_checksums(data: bytes) -> tuple[tuple[str, ...], dict[str, str]]:
    match = SUMS.fullmatch(data) if len(data) <= 512 else None
    if match is None:
        raise ValueError("Unrecognized encrypted files checksums; arbitrary paths refused")
    return FILES, {name: match[index].decode("ascii") for index, name in enumerate(FILES[:-1], 1)}


def verify(directory: Path, receipt: bool = True, expected_name: str | None = None) -> tuple[str, ...]:
    info = directory.lstat()
    if not stat.S_ISDIR(info.st_mode) or info.st_mode & 0o022:
        raise ValueError("Unsafe encrypted files backup directory")
    expected = set(FILES) | ({RECEIPT} if receipt else set())
    if {entry.name for entry in directory.iterdir()} != expected:
        raise ValueError("Unexpected or missing files in encrypted files backup")
    for name in expected:
        safe_file(directory / name)
    if (directory / "SHA256SUMS").stat().st_size > 512 or (directory / "manifest.json").stat().st_size > 4096:
        raise ValueError("Oversized encrypted files metadata")
    checksum_bytes = (directory / "SHA256SUMS").read_bytes()
    _, hashes = parse_checksums(checksum_bytes)
    if any(digest(directory / name) != value for name, value in hashes.items()):
        raise ValueError("Encrypted files SHA-256 verification failed")
    record = json.loads((directory / "manifest.json").read_text())
    name = directory.name if expected_name is None else expected_name
    backup_time(name)
    if (not isinstance(record, dict) or record.get("schema") != "soamco-encrypted-files-v1" or
            record.get("backup") != name or record.get("cipher") != "age-X25519" or
            record.get("archive_verified") is not True or record.get("database_files_atomic") is not False or
            record.get("decryption_verified_on_vps") is not False or
            type(record.get("plaintext_bytes")) is not int or record["plaintext_bytes"] < 1 or
            any(re.fullmatch(r"[a-f0-9]{64}", str(record.get(field, ""))) is None
                for field in ("plaintext_sha256", "recipient_sha256"))):
        raise ValueError("Unrecognized encrypted files manifest")
    verify_cipher(directory / "files.tar.gz.age", record["plaintext_bytes"])
    if receipt:
        if (directory / RECEIPT).stat().st_size > 1024:
            raise ValueError("Oversized NAS receipt")
        if json.loads((directory / RECEIPT).read_text()) not in [{
            "schema": 1, "source": source, "backup": name,
            "checksums_sha256": hashlib.sha256(checksum_bytes).hexdigest(),
        } for source in (SOURCE, LEGACY_SOURCE)]:
            raise ValueError("Encrypted files backup has no valid NAS receipt")
    return FILES
