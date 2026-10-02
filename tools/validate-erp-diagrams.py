"""Structural validation for the generated .drawio sources.

Checks that each file parses, has exactly one diagram page, declares the two
reserved root cells draw.io requires, and that every edge references a cell id
that actually exists. A dangling source/target is silently dropped by draw.io,
so it must fail the build rather than produce a figure with a missing arrow.
"""

import glob
import os
import sys
import xml.etree.ElementTree as ET

PATTERN = os.path.join(
    os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "erp-diagrams", "*.drawio"
)


def main() -> int:
    failures = 0
    files = sorted(glob.glob(PATTERN))
    if not files:
        print("no .drawio files found")
        return 1

    for path in files:
        problems = []
        try:
            root = ET.parse(path).getroot()
        except ET.ParseError as exc:
            print(f"{os.path.basename(path):44} PARSE FAIL {exc}")
            failures += 1
            continue

        if root.tag != "mxfile":
            problems.append(f"root is {root.tag}, expected mxfile")

        pages = root.findall("diagram")
        if len(pages) != 1:
            problems.append(f"{len(pages)} diagram pages, expected 1")

        cells = root.findall(".//mxCell")
        if len(cells) < 2:
            problems.append("no cells declared")
        ids = {c.get("id") for c in cells}
        if "0" not in ids or "1" not in ids:
            problems.append("missing reserved root cell 0 or 1")

        dangling = [
            c.get("id")
            for c in cells
            if c.get("edge") == "1"
            and (c.get("source") not in ids or c.get("target") not in ids)
        ]
        if dangling:
            problems.append(f"dangling edges {dangling}")

        vertices = sum(1 for c in cells if c.get("vertex") == "1")
        edges = sum(1 for c in cells if c.get("edge") == "1")

        status = "OK" if not problems else "FAIL " + "; ".join(problems)
        if problems:
            failures += 1
        print(
            f"{os.path.basename(path):44} vertices={vertices:4} edges={edges:3}  {status}"
        )

    print(f"\nfiles failing: {failures}")
    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main())
