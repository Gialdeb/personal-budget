"""Targeted synthetic tests: no Docker daemon, SSH connection or production data."""
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import stat
import sys
import tarfile
import tempfile
from types import SimpleNamespace
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location("transfer", ROOT / "bin/production_images_transfer.py")
transfer = importlib.util.module_from_spec(spec)
spec.loader.exec_module(transfer)


class ProductionImagesTransferTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix="soamco-image-test-")
        self.root = Path(self.temporary.name)
        self.exports = self.root / "exports"
        self.exports.mkdir(mode=0o700)
        self.commit = "a" * 40
        self.calls = []
        for target in transfer.TARGETS:
            self.export(target)

    def tearDown(self):
        self.temporary.cleanup()

    def export(self, target, failure=None):
        layer = io.BytesIO()
        with tarfile.open(fileobj=layer, mode="w") as archive:
            entry = tarfile.TarInfo("synthetic.txt")
            entry.size = len(b"synthetic")
            archive.addfile(entry, io.BytesIO(b"synthetic"))
        layer = layer.getvalue()
        config = {"os": "linux", "architecture": "arm64" if failure == "platform" else "amd64",
                  "config": {"Labels": {"org.opencontainers.image.revision": "b" * 40 if failure == "revision" else self.commit}},
                  "rootfs": {"type": "layers", "diff_ids": ["sha256:" + hashlib.sha256(layer).hexdigest()]}}
        manifest = [{"Config": "config.json", "RepoTags": [transfer.tag(target, self.commit)], "Layers": ["layer.tar"]}]
        if failure == "tags":
            manifest[0]["RepoTags"].append("production:latest")
        contents = {"config.json": json.dumps(config).encode(), "layer.tar": layer,
                    "manifest.json": json.dumps(manifest).encode(), "index.json": b'{"ignored":"exporter metadata"}',
                    "oci-layout": b'{"imageLayoutVersion":"1.0.0"}'}
        if failure == "layer":
            contents["layer.tar"] = b"corrupt"
        if failure == "traversal":
            contents["../outside"] = b"unsafe"
        with tarfile.open(self.exports / (target + ".export.tar"), "w") as archive:
            for name, data in contents.items():
                info = tarfile.TarInfo(name)
                info.size = len(data)
                archive.addfile(info, io.BytesIO(data))

    def bundle(self):
        self.record = transfer.pack(self.commit, self.exports)
        data = (self.exports / "images.tar").read_bytes()
        return data, hashlib.sha256(data).hexdigest(), str(len(data))

    def docker(self, args, **options):
        self.calls.append(args)
        if args[1:3] == ["context", "inspect"]:
            return json.dumps({"Host": "ssh://production" if getattr(self, "remote_context", False) else "unix:///var/run/docker.sock"}).encode()
        command = args[3:]
        if command[0] == "info":
            return json.dumps(str(self.root)).encode()
        if command[:2] == ["image", "ls"]:
            if getattr(self, "conflict", False):
                return json.dumps({"Repository": "soamco-budget-php", "Tag": "sha-" + self.commit,
                                   "ID": "sha256:" + "f" * 64}).encode()
            if getattr(self, "existing", False):
                item = next(item for item in self.record["images"] if "reference=" + item["tag"] in args)
                repository, version = item["tag"].split(":")
                return json.dumps({"Repository": repository, "Tag": version, "ID": item["id"]}).encode()
            return b""
        if command[:2] == ["image", "load"]:
            return b"loaded"
        if command[:2] == ["image", "inspect"]:
            target = "php" if "php:" in args[-1] else "web"
            item = self.record["images"][transfer.TARGETS.index(target)]
            return json.dumps([{"Id": item["id"], "Os": "linux", "Architecture": "amd64",
                                "Config": {"Labels": {"org.opencontainers.image.revision": self.commit}}}]).encode()
        raise AssertionError("Unexpected Docker command")

    def receive(self, data, checksum, size, free=10**12):
        original = tempfile.TemporaryDirectory
        def temporary(*args, **kwargs):
            kwargs["dir"] = self.root
            return original(*args, **kwargs)
        with patch.object(transfer, "LOCK", self.root / "deploy.lock"), \
                patch.object(transfer, "command", self.docker), \
                patch.object(transfer.shutil, "disk_usage", return_value=SimpleNamespace(free=free)), \
                patch.object(transfer.tempfile, "TemporaryDirectory", temporary), \
                patch.object(sys, "stdin", SimpleNamespace(buffer=io.BytesIO(data))):
            transfer.receive(self.commit, checksum, size)

    def test_pack_and_receive_verify_both_images_without_container_operations(self):
        data, checksum, size = self.bundle()
        with tarfile.open(self.exports / "php.tar") as archive:
            self.assertEqual(set(archive.getnames()), {"config.json", "layer.tar", "manifest.json"})
        self.receive(data, checksum, size)
        loads = [args for args in self.calls if args[3:5] == ["image", "load"]]
        self.assertEqual(len(loads), 2)
        self.assertTrue(all(args[1:3] == ["context", "inspect"] or args[3] in ("info", "image") for args in self.calls))
        self.assertFalse(list(self.root.glob("soamco-images-*")))
        self.assertTrue((self.root / "deploy.lock").exists())

    def test_corruption_truncation_and_space_errors_never_access_docker(self):
        data, checksum, size = self.bundle()
        for incoming, expected, amount, free in [(data + b"x", checksum, size, 10**12),
                                               (data[:-1], checksum, size, 10**12),
                                               (data, "0" * 64, size, 10**12),
                                               (data, checksum, size, 0)]:
            with self.subTest(amount=amount, free=free):
                with self.assertRaises(transfer.TransferError):
                    self.receive(incoming, expected, amount, free)
                self.assertEqual(self.calls, [])
                self.assertFalse(list(self.root.glob("soamco-images-*")))

    def test_unsafe_image_exports_are_refused(self):
        for failure in ("platform", "revision", "tags", "layer", "traversal"):
            with self.subTest(failure=failure):
                self.export("php", failure)
                with self.assertRaises(transfer.TransferError):
                    transfer.inspect_archive(self.exports / "php.export.tar", "php", self.commit,
                                             canonical=self.root / (failure + ".tar"))

    def test_existing_conflicting_tags_are_not_overwritten(self):
        data, checksum, size = self.bundle()
        self.conflict = True
        with self.assertRaisesRegex(transfer.TransferError, "Existing commit tag differs"):
            self.receive(data, checksum, size)
        self.assertFalse(any(args[3:5] == ["image", "load"] for args in self.calls))
        self.assertFalse(list(self.root.glob("soamco-images-*")))

    def test_existing_deployment_lock_prevents_import(self):
        data, checksum, size = self.bundle()
        with (self.root / "deploy.lock").open("w") as lock:
            transfer.fcntl.flock(lock, transfer.fcntl.LOCK_EX)
            inode = (self.root / "deploy.lock").stat().st_ino
            with self.assertRaises(BlockingIOError):
                self.receive(data, checksum, size)
            self.assertEqual((self.root / "deploy.lock").stat().st_ino, inode)
            self.assertEqual(self.calls, [])

    def test_identical_existing_images_are_verified_without_loading_again(self):
        data, checksum, size = self.bundle()
        self.existing = True
        self.receive(data, checksum, size)
        self.assertFalse(any(args[3:5] == ["image", "load"] for args in self.calls))
        self.assertEqual(len([args for args in self.calls if args[3:5] == ["image", "inspect"]]), 2)

    def test_remote_docker_context_is_refused_before_loading(self):
        data, checksum, size = self.bundle()
        self.remote_context = True
        with self.assertRaisesRegex(transfer.TransferError, "local Docker socket"):
            self.receive(data, checksum, size)
        self.assertEqual(len(self.calls), 1)
        self.assertFalse(list(self.root.glob("soamco-images-*")))

    def test_lock_symlinks_are_refused_without_modifying_the_target(self):
        data, checksum, size = self.bundle()
        target = self.root / "protected"
        target.write_text("unchanged")
        (self.root / "deploy.lock").symlink_to(target)
        with self.assertRaises(OSError):
            self.receive(data, checksum, size)
        self.assertEqual(target.read_text(), "unchanged")
        self.assertEqual(self.calls, [])

    def test_send_pins_ssh_keeps_credentials_outside_images_and_cleans_on_failure(self):
        self.bundle()
        environment = {"RUNNER_TEMP": str(self.root), "SOAMCO_SSH_HOST": "example.invalid", "SOAMCO_SSH_USER": "importer",
                       "SOAMCO_SSH_PRIVATE_KEY": "SYNTHETIC-PRIVATE-KEY-DO-NOT-LOG", "SOAMCO_SSH_KNOWN_HOSTS": "synthetic pinned host"}
        def ssh(args, **options):
            self.assertEqual(args[0], "ssh")
            self.assertIn("StrictHostKeyChecking=yes", args)
            self.assertIn("BatchMode=yes", args)
            self.assertIn("IdentityAgent=none", args)
            key = Path(args[args.index("-i") + 1])
            self.assertEqual(stat.S_IMODE(key.stat().st_mode), 0o600)
            self.assertNotIn(environment["SOAMCO_SSH_PRIVATE_KEY"], " ".join(args))
            self.assertIn("python3 -c", args[-1])
            self.assertTrue(options["stdin"].read(1))
            raise transfer.TransferError("synthetic SSH failure")
        with patch.dict(os.environ, environment), patch.object(transfer, "command", ssh):
            with self.assertRaisesRegex(transfer.TransferError, "SSH failure"):
                transfer.send(self.commit, self.exports)
        self.assertFalse(list(self.root.glob("soamco-ssh-*")))

    def test_invalid_ssh_values_and_missing_pins_are_rejected_before_connection(self):
        for values in ({"SOAMCO_SSH_HOST": "host;echo unsafe"}, {"SOAMCO_SSH_HOST": "host", "SOAMCO_SSH_USER": "root"}):
            with patch.dict(os.environ, values, clear=True), patch.object(transfer, "command") as command:
                with self.assertRaises(transfer.TransferError):
                    transfer.send(self.commit, self.exports)
                command.assert_not_called()


if __name__ == "__main__":
    unittest.main()
