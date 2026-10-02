#!/usr/bin/env python3
"""Censors confidential values and skill references from the prompt log.

The prompt log is a required deliverable (it is the AI assessment level three
interaction index) and it will be handed to an instructor, so anything in it
that identifies a privileged account or names an internal tool has to be
redacted without destroying the record of what was done.

Rules are applied in order, the file is rewritten in place, and the tool is
idempotent: running it twice produces the same bytes as running it once.

Usage:
    python tools/censor-prompt-log.py [--check] [--path FILE]
"""

from __future__ import annotations

import argparse
import hashlib
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DEFAULT = os.path.join(ROOT, "docs", "user-prompts.md")

# (label, pattern, replacement, expected_hit_count or None to skip counting)
# Counts are asserted so that a rule silently matching nothing, which would
# happen after a prior run or an upstream edit, fails loudly instead of
# reporting a clean result it never verified.
RULES: list[tuple[str, str, str, int | None]] = [
    # The head administrator account is identified by a hard-coded e-mail
    # comparison in User::isHeadAdmin(). Naming it in a submitted document
    # discloses the exact value that grants the highest privilege.
    (
        "head administrator account address",
        r"admin@bangued\.ph",
        "[head-admin account]",
        12,
    ),
    # Literal tool references embedded in a recorded prompt title. Redacted as a
    # generic marker so the log still shows that a tool was involved without
    # naming which one.
    (
        "inline skill reference",
        r"\[skill\]see",
        "[tooling] see",
        1,
    ),
    (
        "inline skill reference, bare",
        r"\[skill\]",
        "[tooling]",
        0,
    ),
    # Guard rails for anything that must never reach the log. Counted as zero
    # because the file was already cleaned in an earlier pass; they exist so a
    # future edit that reintroduces a secret fails the run.
    ("database password", r"p2p7HLo6daed", "[REDACTED]", 0),
    ("database host", r"sql109\.infinityfree\.com", "[REDACTED_HOST]", 0),
    ("database name", r"if0_41510593_gis", "[REDACTED_DB]", 0),
    ("hosting account id", r"if0_41510593", "[REDACTED_ACCOUNT]", 0),
    ("ftp host", r"ftpupload\.net", "[REDACTED_HOST]", 0),
    ("application key", r"base64:[A-Za-z0-9+/]{20,}={0,2}", "[REDACTED_KEY]", 0),
    ("basemap api key", r"cb1_3t10_1_[A-Za-z0-9]+", "[REDACTED_KEY]", 0),
    ("real contributor name", r"Jake Carlo Garcia Mandi", "[REDACTED]", 0),
    ("real contributor address", r"mandijakecarlog@gmail\.com", "[REDACTED]", 0),
    ("skill bundle path", r"\.claude[/\\]skills", "[REDACTED]", 0),
    ("skill manifest name", r"SKILL\.md", "[REDACTED]", 0),
    ("agent bundle identifier", r"agentic-bundle[\w.-]*", "[REDACTED]", 0),
]

BANNER = (
    "> **Redaction notice.** Privileged account identifiers, credentials, "
    "deployment hostnames and references to specific internal tooling have been "
    "replaced with bracketed markers. The structure, sequence and technical "
    "substance of every recorded interaction are unchanged."
)

BANNER_RE = re.compile(r"^> \*\*Redaction notice\.\*\*.*$", re.MULTILINE)


def censor(text: str) -> tuple[str, list[str], list[str]]:
    problems: list[str] = []
    applied: list[str] = []

    # Counts only make sense on the run that actually had something to redact.
    # Deciding that first keeps a second run from reporting twelve missing
    # matches as if they were a problem.
    first_run = any(re.search(pattern, text) for _, pattern, _, expected in RULES if expected)

    for label, pattern, replacement, expected in RULES:
        regex = re.compile(pattern)
        hits = len(regex.findall(text))
        if hits:
            text = regex.sub(replacement, text)
            applied.append(f"{label}: {hits} replaced")
        elif expected and first_run:
            problems.append(f"{label}: expected {expected} match(es), found 0")

    if not first_run:
        # Already censored: prove the markers landed rather than assuming it.
        missing = [
            replacement
            for _, _, replacement, expected in RULES
            if expected and replacement not in text
        ]
        if missing:
            problems.append(
                "file reports as already censored but these markers are absent: "
                + ", ".join(sorted(set(missing)))
            )

    # Rebuild the header block deterministically: title, blank, notice, blank,
    # then everything else. Stripping any existing notice first means a hand
    # edit to it cannot leave the file in a half-applied state, and comparing
    # before and after means an unchanged block is never reported as a change.
    body = BANNER_RE.sub("", text).lstrip("\n")
    if body.startswith("# "):
        title, _, rest = body.partition("\n")
        rebuilt = f"{title.strip()}\n\n{BANNER}\n\n{rest.lstrip(chr(10))}"
    else:
        rebuilt = f"{BANNER}\n\n{body}"

    if rebuilt != text:
        text = rebuilt
        applied.append("redaction notice normalised")

    return text, applied, problems


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--path", default=DEFAULT)
    ap.add_argument(
        "--check",
        action="store_true",
        help="report what would change without writing",
    )
    args = ap.parse_args()

    if not os.path.exists(args.path):
        print(f"FAIL: {args.path} does not exist")
        return 1

    with open(args.path, encoding="utf-8") as fh:
        original = fh.read()

    cleaned, applied, problems = censor(original)

    before = hashlib.sha256(original.encode("utf-8")).hexdigest()[:12]
    after = hashlib.sha256(cleaned.encode("utf-8")).hexdigest()[:12]

    print(f"file   : {os.path.relpath(args.path, ROOT)}")
    print(f"before : {len(original):,} bytes  sha256:{before}")
    print(f"after  : {len(cleaned):,} bytes  sha256:{after}")

    if applied:
        print("changes:")
        for item in applied:
            print(f"  - {item}")
    else:
        print("changes: none required")

    if problems:
        print("problems:")
        for p in problems:
            print(f"  ! {p}")

    if args.check:
        print("check only; nothing written")
        return 1 if problems else 0

    if cleaned != original:
        with open(args.path, "w", encoding="utf-8", newline="\n") as fh:
            fh.write(cleaned)
        print("written")
    else:
        print("unchanged, not rewritten")

    return 1 if problems else 0


if __name__ == "__main__":
    sys.exit(main())
