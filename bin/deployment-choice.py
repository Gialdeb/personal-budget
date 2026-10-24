#!/usr/bin/env python3
"""Validate the exact annotated Git selection fetched from the trusted origin."""
import json
import re
import sys

PREPARATIONS = {"ghcr": "production-images.yml", "ssh": "production-images-ssh.yml"}


def selection(commit, repository, text):
    if not re.fullmatch(r"[a-f0-9]{40}", commit) or not re.fullmatch(r"[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*", repository):
        raise ValueError("Invalid expected commit/repository")
    headers, message = text.split("\n\n", 1)
    fields = {}
    for line in headers.splitlines():
        key, value = line.split(" ", 1)
        if key in fields:
            raise ValueError("Duplicate tag header")
        fields[key] = value
    if (set(fields) != {"object", "type", "tag", "tagger"} or fields["object"] != commit or
            fields["type"] != "commit" or fields["tag"] != "soamco-deploy-" + commit):
        raise ValueError("Tag is not bound to the deployed commit")
    record = json.loads(message)
    if (not isinstance(record, dict) or set(record) != {"schema", "commit", "repository", "mode", "selection_run_id", "preparation"} or
            record["schema"] != "soamco-deploy-selection-v1" or record["commit"] != commit or
            record["repository"] != repository or record["mode"] not in ("local", "ghcr", "ssh") or
            type(record["selection_run_id"]) is not int or record["selection_run_id"] <= 0):
        raise ValueError("Invalid selection record")
    proof = record["preparation"]
    if record["mode"] == "local":
        if proof is not None:
            raise ValueError("Unexpected local preparation proof")
    elif (not isinstance(proof, dict) or set(proof) != {"workflow", "run_id", "run_attempt"} or
          proof["workflow"] != PREPARATIONS[record["mode"]] or
          any(type(proof[field]) is not int or proof[field] <= 0 for field in ("run_id", "run_attempt"))):
        raise ValueError("Prepared mode requires a valid matching preparation proof")
    return record["mode"]


def main():
    try:
        if len(sys.argv) != 3:
            raise ValueError("Commit and repository required")
        text = sys.stdin.read(65537)
        if len(text) > 65536:
            raise ValueError("Oversized selection")
        print(selection(sys.argv[1], sys.argv[2].lower(), text))
        return 0
    except (ValueError, KeyError, TypeError):
        print("ERROR invalid or mismatched GitHub deployment choice; no fallback", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
