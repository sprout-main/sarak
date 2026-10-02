#!/usr/bin/env python3
"""Verification gates for the ERP manuscript.

Fails loudly rather than warning quietly, because the failure that matters most
is a real citizen identifier or the production database password reaching a
submitted academic paper, which the Data Privacy Act of 2012 forbids.
"""

from __future__ import annotations

import glob
import json
import os
import re
import sys
import zipfile

from docx import Document

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DOCX = os.path.join(ROOT, "Sarak-ERP-Midterm.docx")
SCHEMA_JSON = os.path.join(ROOT, "storage", "logs", "schema-export.json")
DIAGRAMS = os.path.join(ROOT, "erp-diagrams")

# Values that must never appear in the manuscript.
FORBIDDEN = {
    "real contributor name": "Jake Carlo Garcia Mandi",
    "real contributor email": "mandijakecarlog@gmail.com",
    "production db password": "p2p7HLo6daed",
    "production db host": "sql109.infinityfree.com",
    "production db name": "if0_41510593_gis",
    "production db user": "if0_41510593",
    "head admin email": "admin@bangued.ph",
    "resident email": "resident@bangued.ph",
    "infinityfree ftp host": "ftpupload.net",
    "carto api key": "cb1_3t10_1_84d924588c1ce8f54c486ff0",
}

# Bare "if0_41510593" is the db user and also the hosting account name.
FORBIDDEN["hosting account id"] = "if0_41510593"

# Real establishment names that appear in the seeded dataset.
REAL_ESTABLISHMENTS = [
    "Chowking",
    "Jollibee",
    "Max's Restaurant",
    "Shakey's",
    "Generics Pharmacy",
    "Cafe Merci",
    "Aquino General",
    "HMC - Medical",
    "St. James Hospital",
    "MedHeals",
    "Rina's Pharmacy",
    "Belmes-Madriaga",
    "Ladrillo Cafe",
    "Kapepiliin",
    "Igop Cafe",
    "Depresso Cafe",
    "Daily Dose Coffee",
    "Miguel Chike",
    "NCT Department",
    "St. Joseph Drug",
    "San Mig Super",
    "7-Eleven",
    "Family Mart",
    "Puregold",
]


def all_text(doc: Document) -> str:
    parts: list[str] = []
    for p in doc.paragraphs:
        parts.append(p.text)
    for t in doc.tables:
        for row in t.rows:
            for cell in row.cells:
                for p in cell.paragraphs:
                    parts.append(p.text)
    return "\n".join(parts)


def main() -> int:
    failures: list[str] = []
    warnings: list[str] = []

    # Overridable so the privacy gate itself can be exercised against a
    # deliberately poisoned document as a negative test.
    docx_path = sys.argv[1] if len(sys.argv) > 1 else DOCX

    if not os.path.exists(docx_path):
        print(f"FAIL: {docx_path} does not exist")
        return 1

    size = os.path.getsize(docx_path)
    if size < 20000:
        failures.append(f"document suspiciously small: {size} bytes")
    if not zipfile.is_zipfile(docx_path):
        failures.append("not a valid OOXML zip container")
        return report(failures, warnings)

    with zipfile.ZipFile(docx_path) as zf:
        names = zf.namelist()
    for required in ("[Content_Types].xml", "word/document.xml"):
        if required not in names:
            failures.append(f"missing OOXML part {required}")

    doc = Document(docx_path)
    text = all_text(doc)

    # ---- gate 1: privacy masking
    for label, needle in FORBIDDEN.items():
        if needle.lower() in text.lower():
            failures.append(f"PRIVACY: {label} present in manuscript ({needle})")
    for name in REAL_ESTABLISHMENTS:
        if name.lower() in text.lower():
            failures.append(f"PRIVACY: real establishment name present ({name})")
    if re.search(r"\b\d{10,11}\b", text):
        for match in set(re.findall(r"\b\d{10,11}\b", text)):
            failures.append(f"PRIVACY: possible telephone number present ({match})")
    if re.search(r"[\w.+-]+@(?!(?:masked\.local)\b)[\w-]+\.[\w.]+", text):
        for match in set(re.findall(r"[\w.+-]+@(?!masked\.local)[\w-]+\.[\w.]+", text)):
            failures.append(f"PRIVACY: non-masked e-mail address present ({match})")

    # ---- gate 2: structure
    headings = [p.text for p in doc.paragraphs if p.style.name.startswith("Heading")]
    required_headings = [
        "Chapter I: Company and Target Office Profiling",
        "Chapter II: Current As-Is System Analysis",
        "Chapter III: Business Process Re-engineering and Core Optimisation",
        "Chapter IV: Proposed ERP Module Design and Technical Blueprints",
        "Chapter V: Prototype Deployment",
        "Privacy Threshold Assessment",
        "Data Dictionary",
        "Role-Based Access Control Matrix",
        "Appendix A: AI Interaction Index Log",
        "References",
    ]
    for need in required_headings:
        # Headings carry their section number, for example "1.4 Privacy Threshold
        # Assessment", so match on the trailing title rather than the whole line.
        if not any(need in got for got in headings):
            failures.append(f"STRUCTURE: missing heading {need!r}")

    # ---- gate 3: TOC field
    xml = doc.element.xml
    if "TOC \\o" not in xml:
        failures.append("STRUCTURE: no TOC field code present")
    if 'w:fldCharType="begin"' not in xml or 'w:fldCharType="end"' not in xml:
        failures.append("STRUCTURE: TOC field is not a complete begin/separate/end run")

    # ---- gate 4: figures
    # A real caption paragraph carries the diagram filename; the List of Figures
    # in the front matter repeats the label without it, so the two are counted
    # separately rather than conflated.
    numbered_pattern = re.compile(r"^Figure (\d+)\.")
    caption_paras = [p.text for p in doc.paragraphs if numbered_pattern.match(p.text)]
    figure_caps = [c for c in caption_paras if "erp-diagrams/" in c]
    list_of_figures = [c for c in caption_paras if "erp-diagrams/" not in c]

    expected_figures = 6
    if len(figure_caps) != expected_figures:
        failures.append(
            f"FIGURES: {len(figure_caps)} figure captions found, expected {expected_figures}"
        )
    if len(list_of_figures) != expected_figures:
        failures.append(
            f"FIGURES: List of Figures has {len(list_of_figures)} entries, expected {expected_figures}"
        )
    drawio_files = sorted(os.path.basename(p) for p in glob.glob(os.path.join(DIAGRAMS, "*.drawio")))
    if len(drawio_files) != expected_figures:
        failures.append(
            f"FIGURES: {len(drawio_files)} .drawio sources found, expected {expected_figures}"
        )
    for cap in figure_caps:
        match = re.search(r"erp-diagrams/([\w.-]+\.drawio)", cap)
        if not match:
            failures.append(f"FIGURES: caption does not name a .drawio source: {cap!r}")
        elif match.group(1) not in drawio_files:
            failures.append(f"FIGURES: caption references missing file {match.group(1)}")
    numbered_figs = sorted(int(numbered_pattern.match(c).group(1)) for c in figure_caps)
    if numbered_figs != list(range(1, expected_figures + 1)):
        failures.append(f"FIGURES: numbering is not contiguous 1..{expected_figures}: {numbered_figs}")

    # ---- gate 5: data dictionary parity with the live schema
    with open(SCHEMA_JSON, encoding="utf-8") as fh:
        schema = json.load(fh)
    expected_cols = sum(len(v) for v in schema.values())
    dict_tables = [t for t in doc.tables if t.rows and t.rows[0].cells[0].text == "Column"]
    dict_rows = sum(len(t.rows) - 1 for t in dict_tables)
    if dict_rows != expected_cols:
        failures.append(
            f"SCHEMA: data dictionary has {dict_rows} rows, live schema has {expected_cols} columns"
        )
    for name in schema:
        if not any(f"table {name}" in c.text for c in doc.paragraphs):
            warnings.append(f"SCHEMA: no dictionary table titled for {name}")

    # ---- gate 6: house style, no em dashes
    if "\u2014" in text:
        failures.append(f"STYLE: {text.count(chr(8212))} em dash(es) present")

    # ---- gate 7: placeholder honesty, unresolved placeholders must be visible
    placeholders = re.findall(r"\[TO BE COMPLETED[^\]]*\]", text)
    if placeholders:
        warnings.append(
            f"{len(placeholders)} explicit placeholder(s) left for the group to resolve"
        )

    print(f"document      : {os.path.basename(docx_path)}  ({size:,} bytes)")
    print(f"paragraphs    : {len(doc.paragraphs)}")
    print(f"tables        : {len(doc.tables)}")
    print(f"headings      : {len(headings)}")
    print(f"words         : {len(text.split()):,}")
    print(f"figure captions: {len(figure_caps)}")
    print(f"dict rows     : {dict_rows} (schema {expected_cols})")
    print(f"drawio sources: {len(drawio_files)}")
    return report(failures, warnings)


def report(failures: list[str], warnings: list[str]) -> int:
    for w in warnings:
        print(f"WARN : {w}")
    for f in failures:
        print(f"FAIL : {f}")
    print()
    if failures:
        print(f"GATE RESULT: FAIL ({len(failures)} failure(s))")
        return 1
    print("GATE RESULT: PASS")
    return 0


if __name__ == "__main__":
    sys.exit(main())
