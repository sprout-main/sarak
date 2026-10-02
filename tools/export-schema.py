#!/usr/bin/env python3
"""Exports the live Sarak domain schema for the manuscript data dictionary.

The dictionary in Chapter IV must describe the schema that actually exists, so
it is read from information_schema at build time rather than transcribed.

PowerShell's ">" redirection writes UTF-16, which corrupts the leading byte of
a UTF-8 read, so the dump is produced here with an explicit encoding.

Usage:
    python tools/export-schema.py [--out PATH] [--host H] [--user U] [--database D]
"""

from __future__ import annotations

import argparse
import json
import os
import subprocess
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DEFAULT_OUT = os.path.join(ROOT, "storage", "logs", "schema-export.json")

DOMAIN_TABLES = [
    "places",
    "place_photos",
    "place_hours",
    "place_reports",
    "categories",
    "boundary_areas",
    "users",
    "mod_queue",
    "audit_log",
    "import_runs",
]

MYSQL = r"C:\xampp\mysql\bin\mysql.exe"

QUERY = """
SELECT c.TABLE_NAME, c.ORDINAL_POSITION, c.COLUMN_NAME, c.COLUMN_TYPE,
       c.IS_NULLABLE, IFNULL(c.COLUMN_KEY,''), IFNULL(c.COLUMN_DEFAULT,''),
       IFNULL(c.EXTRA,''),
       IFNULL((SELECT GROUP_CONCAT(
                   CONCAT(k.COLUMN_NAME,' -> ',k.REFERENCED_TABLE_NAME,'.',k.REFERENCED_COLUMN_NAME)
                   ORDER BY k.COLUMN_NAME SEPARATOR '; ')
               FROM information_schema.KEY_COLUMN_USAGE k
               WHERE k.TABLE_SCHEMA = c.TABLE_SCHEMA
                 AND k.TABLE_NAME   = c.TABLE_NAME
                 AND k.COLUMN_NAME  = c.COLUMN_NAME
                 AND k.REFERENCED_TABLE_NAME IS NOT NULL), '')
FROM information_schema.COLUMNS c
WHERE c.TABLE_SCHEMA = '{db}'
  AND c.TABLE_NAME IN ({tables})
ORDER BY FIELD(c.TABLE_NAME,{order}), c.ORDINAL_POSITION
"""


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--out", default=DEFAULT_OUT)
    ap.add_argument("--host", default="127.0.0.1")
    ap.add_argument("--user", default="root")
    ap.add_argument("--password", default="")
    ap.add_argument("--database", default="bangued_atlas")
    args = ap.parse_args()

    quoted = ",".join(f"'{t}'" for t in DOMAIN_TABLES)
    sql = QUERY.format(db=args.database, tables=quoted, order=quoted)

    cmd = [
        MYSQL,
        f"--host={args.host}",
        f"--user={args.user}",
        "--batch",
        "--raw",
        "--skip-column-names",
    ]
    if args.password:
        cmd.append(f"--password={args.password}")
    cmd += ["--execute", sql]

    result = subprocess.run(cmd, capture_output=True, text=True)
    if result.returncode != 0:
        sys.stderr.write(result.stderr or "mysql failed\n")
        return 1

    schema: dict[str, list[dict[str, object]]] = {t: [] for t in DOMAIN_TABLES}
    for line in result.stdout.splitlines():
        parts = line.split("\t")
        if len(parts) < 8:
            continue
        schema.setdefault(parts[0], []).append(
            {
                "ordinal": int(parts[1]),
                "column": parts[2],
                "type": parts[3],
                "nullable": parts[4],
                "key": parts[5],
                "default": parts[6],
                "extra": parts[7],
                "ref": parts[8] if len(parts) > 8 else "",
            }
        )

    total = sum(len(v) for v in schema.values())
    if total == 0:
        sys.stderr.write("no columns returned; refusing to write an empty export\n")
        return 1

    os.makedirs(os.path.dirname(args.out), exist_ok=True)
    with open(args.out, "w", encoding="utf-8") as fh:
        json.dump(schema, fh, indent=2, ensure_ascii=False)

    for name in DOMAIN_TABLES:
        print(f"  {name:<16} {len(schema[name]):>3} columns")
    print(f"total columns: {total}")
    print(f"wrote {args.out}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
