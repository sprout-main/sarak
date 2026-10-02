#!/usr/bin/env python3
"""Generates the Draw.io sources for the Sarak ERP manuscript.

The syllabus bans Word drawing shapes and Paint files and mandates
Draw.io / Lucidchart / EdrawMax, so every figure ships as a real .drawio
(mxfile/mxGraphModel) document that opens natively in draw.io.

Each figure is deliberately oversized in page units so an exported PNG drops
into the manuscript frame without rescaling surprises.
"""

from __future__ import annotations

import html
import os
from xml.sax.saxutils import escape

OUT = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "erp-diagrams")

# ---------------------------------------------------------------- primitives


def mxfile(name: str, pages: list[tuple[str, str]], width: int = 1600, height: int = 1200) -> str:
    diagrams = "".join(
        '  <diagram name="{name}" id="page-{i}">\n'
        '    <mxGraphModel dx="1400" dy="900" grid="1" gridSize="10" guides="1" tooltips="1" '
        'connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="{w}" pageHeight="{h}" '
        'math="0" shadow="0">\n'
        '      <root>\n'
        '        <mxCell id="0" />\n'
        '        <mxCell id="1" parent="0" />\n'
        "{root}"
        '      </root>\n'
        '    </mxGraphModel>\n'
        '  </diagram>\n'.format(name=escape(n), i=i - 1, w=width, h=height, root=body)
        for i, (n, body) in enumerate(pages, start=1)
    )
    return '<mxfile host="app.diagrams.net" type="device">\n{diagrams}</mxfile>\n'.format(
        diagrams=diagrams
    )


class Page:
    """Collects cells for one diagram page."""

    def __init__(self) -> None:
        self.cells: list[str] = []
        self.n = 100

    def _id(self, prefix: str = "c") -> str:
        self.n += 1
        return f"{prefix}{self.n}"

    def node(
        self,
        value: str,
        x: float,
        y: float,
        w: float,
        h: float,
        style: str,
    ) -> str:
        cid = self._id()
        self.cells.append(
            '        <mxCell id="{id}" value="{v}" style="{s}" vertex="1" parent="1">\n'
            '          <mxGeometry x="{x}" y="{y}" width="{w}" height="{h}" as="geometry" />\n'
            "        </mxCell>\n".format(
                id=cid, v=escape(value, {'"': "&quot;"}), s=escape(style, {'"': "&quot;"}),
                x=int(x), y=int(y), w=int(w), h=int(h),
            )
        )
        return cid

    def edge(
        self,
        src: str,
        dst: str,
        label: str = "",
        style: str = "edgeStyle=orthogonalEdgeStyle;rounded=0;html=1;endArrow=block;endFill=1;",
    ) -> None:
        cid = self._id("e")
        self.cells.append(
            '        <mxCell id="{id}" value="{v}" style="{s}" edge="1" parent="1" source="{a}" target="{b}">\n'
            '          <mxGeometry relative="1" as="geometry" />\n'
            "        </mxCell>\n".format(
                id=cid, v=escape(label), s=escape(style, {'"': "&quot;"}), a=src, b=dst
            )
        )

    def note(self, value: str, x: float, y: float, w: float, h: float) -> None:
        self.node(
            value, x, y, w, h,
            "shape=note;whiteSpace=wrap;html=1;size=14;align=left;verticalAlign=top;fillColor=#fff2cc;strokeColor=#d6b656;",
        )

    def label(self, value: str, x: float, y: float, w: float, h: float = 24) -> None:
        self.node(
            value, x, y, w, h,
            "text;html=1;align=center;verticalAlign=middle;resizable=0;noEdges=1;strokeColor=none;fillColor=none;"
            "fontStyle=1;fontSize=14;fontColor=#1f2937;",
        )

    def render(self) -> str:
        return "".join(self.cells)


# ------------------------------------------------------------------- styles

TERM = ("shape=rectangle;whiteSpace=wrap;html=1;rounded=0;fillColor=#dae8fc;strokeColor=#6c8ebf;"
        "fontStyle=1;verticalAlign=middle;")
PROC = ("rounded=1;whiteSpace=wrap;html=1;arcSize=0.12;fillColor=#d5e8d4;strokeColor=#82b366;"
        "verticalAlign=middle;")
STORE = ("shape=cylinder3;whiteSpace=wrap;html=1;boundedLbl=1;backgroundOutline=1;size=12;"
         "fillColor=#ffe6cc;strokeColor=#d79b00;verticalAlign=middle;")
FLOW = ("rounded=1;whiteSpace=wrap;html=1;arcSize=0.3;fillColor=#e1d5e7;strokeColor=#9673a6;"
        "fontStyle=1;")
RISK = ("rounded=0;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;align=left;"
        "verticalAlign=top;spacingLeft=6;dashed=1;")
NODE = ("rounded=0;whiteSpace=wrap;html=1;fillColor=#ffffff;strokeColor=#666666;verticalAlign=top;"
        "spacingTop=4;align=left;spacingLeft=6;")
CLOUD = ("shape=cloud;whiteSpace=wrap;html=1;fillColor=#f5f5f5;strokeColor=#666666;verticalAlign=middle;")

SERVER = ("shape=cylinder3;whiteSpace=wrap;html=1;boundedLbl=1;backgroundOutline=1;size=10;"
          "fillColor=#d5e8d4;strokeColor=#82b366;verticalAlign=middle;")
BOUNDARY = ("rounded=0;whiteSpace=wrap;html=1;fillColor=#eeeeee;strokeColor=#666666;dashed=1;"
            "verticalAlign=top;align=center;")

# ------------------------------------------------------------------- figures


def fig_as_is_network() -> Page:
    p = Page()
    p.label("Figure 1 - Reconstructed As-Is network topology of the target office", 0, 10, 1200)

    p.node("Boundary of the office LAN", 40, 50, 1120, 560, BOUNDARY)

    dsk = p.node("Office PC<br><i>Windows 10, Chrome</i><br>registry held in Excel on the<br>desktop of this single machine",
                 90, 130, 250, 110, NODE)
    lap = p.node("Laptop<br><i>second working copy,<br>kept in sync manually</i>",
                 90, 300, 250, 90, NODE)
    mfp = p.node("Multi-function<br>printer", 400, 130, 150, 70, NODE)
    nas = p.node("External USB drive<br><i>manual weekly copy,<br>stored in a drawer</i>",
                 400, 300, 190, 90, NODE)

    p.edge(dsk, mfp, "print")
    p.edge(dsk, nas, "manual copy")

    rtr = p.node("Consumer router<br><i>no firewall, no VLAN,<br>default credentials</i>",
                 660, 180, 200, 100, NODE)
    p.edge(dsk, rtr, "Ethernet")
    p.edge(lap, rtr, "Wi-Fi")
    p.edge(rtr, mfp, "Wi-Fi")

    dsl = p.node("DSL / fibre line<br><i>20 Mbps</i>", 940, 180, 180, 80, NODE)
    p.edge(rtr, dsl, "WAN")

    fb = p.node("Facebook group page<br><i>public listing, unmoderated</i>", 940, 330, 180, 80, CLOUD)
    p.edge(dsk, fb, "manual retyping of<br>each new listing")

    p.note(
        "<b>Reconstructed baseline.</b><br><br>"
        "No application server exists in the As-Is environment. There is no dedicated database, "
        "no shared storage service and no backup scheduler. Availability of the directory depends "
        "entirely on one desktop machine remaining switched on and undamaged.<br><br>"
        "The second working copy on the laptop and the USB copy diverge silently; there is no "
        "reconciliation step and no version history.",
        40, 640, 1120, 150,
    )
    return p


def fig_as_is_dataflow() -> Page:
    p = Page()
    p.label("Figure 2 - Reconstructed As-Is level-0 data flow diagram", 0, 10, 1200)

    b1 = p.node("Business owner<br>(applicant)", 40, 90, 170, 60, TERM)
    b2 = p.node("Walk-in client", 40, 560, 170, 60, TERM)
    b3 = p.node("Association<br>officer", 40, 320, 170, 60, TERM)

    f1 = p.node("1.0<br>Capture registration<br><i>(handwritten form)</i>", 300, 90, 190, 90, PROC)
    f2 = p.node("2.0<br>Re-key into spreadsheet<br><i>(manual transcription)</i>", 560, 90, 200, 90, PROC)
    f3 = p.node("3.0<br>Resolve duplicate and<br>spelling variants", 830, 90, 190, 90, PROC)
    f4 = p.node("4.0<br>Print directory<br>and issue copy", 560, 330, 200, 90, PROC)
    f5 = p.node("5.0<br>Retype listing onto<br>social media page", 830, 480, 200, 90, PROC)

    d1 = p.node("D1 Paper<br>register", 300, 560, 160, 80, STORE)
    d2 = p.node("D2 master.xlsx<br>on one desktop", 560, 480, 190, 80, STORE)
    d3 = p.node("D3 USB copy<br>(weekly)", 300, 720, 170, 80, STORE)

    p.edge(b1, f1, "signed form")
    p.edge(f1, d1, "filed")
    p.edge(d1, f2, "read aloud")
    p.edge(f2, d2, "typed")
    p.edge(f2, f4, "print job")
    p.edge(d2, f3, "scan for dupes")
    p.edge(f3, d2, "corrections")
    p.edge(f4, b2, "photocopy")
    p.edge(b2, f5, "asks by message")
    p.edge(f5, d2, "no write-back")
    p.edge(d2, d3, "manual copy")

    p.note(
        "<b>Manual hand-off points.</b> Information moves form to paper to spreadsheet to print to "
        "social media. Each arrow is a re-keying event, so transcription error compounds at every hop. "
        "Process 5.0 reads from D2 but never writes back, so the printed directory and the online "
        "listing diverge permanently with no detection mechanism. Coordinates and boundary "
        "assignments are absent from the As-Is model entirely.",
        830, 640, 500, 190,
    )
    return p


def fig_to_be_process() -> Page:
    p = Page()
    p.label("Figure 3 - To-Be process model for the Sarak directory", 0, 10, 1300)

    pub = p.node("Public<br>(unauthenticated)", 40, 90, 150, 70, TERM)
    con = p.node("Contributor<br>(signed in)", 40, 300, 150, 70, TERM)
    adm = p.node("Administrator", 40, 620, 150, 70, TERM)

    e1 = p.node("1.0 Serve map,<br>boundaries, tiles", 250, 90, 190, 80, PROC)
    e2 = p.node("2.0 Submit place<br>or photo", 250, 290, 190, 80, PROC)
    e3 = p.node("3.0 Validate and<br>normalise", 510, 290, 190, 80, PROC)
    e4 = p.node("4.0 Moderation<br>queue", 770, 290, 180, 80, PROC)
    e5 = p.node("5.0 Approve and<br>publish", 1010, 290, 180, 80, PROC)
    e6 = p.node("6.0 Bulk import<br>(Overpass, Nominatim)", 510, 520, 210, 80, PROC)
    e7 = p.node("7.0 Dedupe and<br>snapping", 770, 520, 180, 80, PROC)
    e8 = p.node("8.0 Reject, discard<br>and reclaim file", 1010, 520, 190, 80, PROC)
    e9 = p.node("9.0 Audit every<br>state change", 1010, 690, 180, 70, PROC)

    s1 = p.node("Places, hours,<br>photos (MySQL)", 250, 700, 190, 80, STORE)
    s2 = p.node("Photo tree on the<br>public disk", 250, 820, 190, 70, STORE)
    s3 = p.node("Tile cache", 510, 820, 180, 70, STORE)

    p.edge(pub, e1, "GET /api/places")
    p.edge(con, e2, "POST /api/places/{id}/photos")
    p.edge(e2, e3, "validated request")
    p.edge(e3, e4, "queue row")
    p.edge(e4, e5, "review")
    p.edge(e4, e8, "reject")
    p.edge(e5, e9)
    p.edge(e8, e9)
    p.edge(e6, e7)
    p.edge(e7, e4, "bulk promote")
    p.edge(e5, s1, "INSERT / UPDATE")
    p.edge(e2, s2, "store file")
    p.edge(e8, s2, "delete file")
    p.edge(e1, s3, "cached tiles")
    p.edge(adm, e4, "review queue")
    p.edge(adm, e1, "admin console")

    p.note(
        "<b>Eliminated hand-offs.</b> The As-Is chain of five re-keying steps collapses to a single "
        "write. Rejection now removes the uploaded file rather than leaving it on disk, and every "
        "approval, rejection, relocation and deletion is written to an append-only audit log. "
        "Administrative uploads publish directly because administrators are the moderating authority; "
        "contributor submissions always pass through 4.0.",
        40, 780, 190, 120,
    )
    return p


def fig_to_be_dataflow() -> Page:
    p = Page()
    p.label("Figure 4 - To-Be level-0 data flow diagram for Sarak", 0, 10, 1300)

    br = p.node("Browser<br>(Leaflet client)", 40, 120, 160, 70, TERM)
    ad = p.node("Administrator<br>browser", 40, 560, 160, 70, TERM)

    w1 = p.node("W1 Apache<br>rewrite + deny rules", 270, 120, 190, 80, FLOW)
    w2 = p.node("W2 Laravel<br>front controller", 530, 120, 190, 80, FLOW)

    pr1 = p.node("P1 Public read<br>(ETag, cached)", 800, 60, 190, 80, PROC)
    pr2 = p.node("P2 Authenticated<br>write", 800, 180, 190, 80, PROC)
    pr3 = p.node("P3 Admin and<br>moderation", 800, 300, 190, 80, PROC)
    pr4 = p.node("P4 Tile proxy", 800, 420, 190, 70, PROC)
    pr5 = p.node("P5 Import pipeline", 800, 540, 190, 70, PROC)

    sv1 = p.node("GeoSpatial,<br>BarangayResolver", 1060, 60, 200, 70, PROC)
    sv2 = p.node("Moderation,<br>Photo services", 1060, 180, 200, 70, PROC)
    sv3 = p.node("Import, Dedupe,<br>AddressEnricher", 1060, 540, 200, 70, PROC)

    d1 = p.node("D1 places,<br>place_hours,<br>place_photos", 1060, 300, 200, 90, STORE)
    d2 = p.node("D2 boundary_areas<br>(POLYGON)", 1060, 420, 200, 70, STORE)
    d3 = p.node("D3 audit_log,<br>mod_queue", 1260, 300, 200, 80, STORE)
    d4 = p.node("D4 public disk<br>photo tree", 1260, 420, 200, 70, STORE)

    e1 = p.node("Carto basemap and<br>OpenStreetMap", 530, 640, 190, 70, CLOUD)
    e2 = p.node("Overpass API and<br>Nominatim", 530, 750, 190, 70, CLOUD)

    p.edge(br, w1, "HTTPS")
    p.edge(ad, w1, "HTTPS")
    p.edge(w1, w2, "internal")
    p.edge(w2, pr1)
    p.edge(w2, pr2)
    p.edge(w2, pr3)
    p.edge(w2, pr4)
    p.edge(w2, pr5)
    p.edge(pr1, sv1)
    p.edge(sv1, d1, "ST_Within,\nST_Distance_Sphere")
    p.edge(sv1, d2)
    p.edge(pr2, sv2)
    p.edge(sv2, d1)
    p.edge(sv2, d4, "write file")
    p.edge(pr3, sv2)
    p.edge(sv2, d3, "append")
    p.edge(pr4, e1, "fetch + cache")
    p.edge(pr5, e2, "fetch")
    p.edge(sv3, e2)
    p.edge(sv3, d1, "upsert")
    p.edge(pr5, sv3)

    p.note(
        "<b>Trust boundaries.</b> W1 is the only public-facing hop and is where every directory deny "
        "rule and the dotfile block are enforced. Outbound calls to third-party geocoding and tile "
        "services originate only from P4 and P5; no client-supplied value reaches those providers "
        "except through validated coordinates.",
        40, 640, 160, 190,
    )
    return p


def fig_erd() -> Page:
    p = Page()
    p.label("Figure 5 - Entity-relationship diagram (domain schema)", 0, 5, 1500)

    tables = [
        ("users", 40, 40, 300, ["id PK", "name", "email UK", "email_verified_at", "password",
                                "role enum(user,admin)", "karma_points", "reputation_badge",
                                "avatar_path", "censor_contributor_name", "remember_token",
                                "created_at", "updated_at"]),
        ("categories", 400, 40, 240, ["id PK", "slug UK", "label", "icon_key", "color",
                                       "sort_order", "is_active", "created_at", "updated_at"]),
        ("boundary_areas", 700, 40, 260, ["id PK", "name", "type enum", "geom POLYGON",
                                          "boundary_source", "psgc", "imported_at",
                                          "created_at", "updated_at"]),
        ("places", 40, 400, 300, ["id PK", "category_id FK", "name", "slug UK", "description",
                                  "address", "barangay_id FK", "geom POINT + SPATIAL INDEX",
                                  "lat decimal(10,7)", "lng decimal(10,7)",
                                  "source enum(6)", "source_ref", "source_url",
                                  "status enum(4)", "submitted_by", "approved_by", "approved_at",
                                  "verified_tier enum(3)", "karma_score", "is_claimed",
                                  "claimed_by FK", "phone", "website", "created_at",
                                  "updated_at", "deleted_at"]),
        ("place_photos", 400, 400, 260, ["id PK", "place_id FK cascade", "path", "uploader_id FK",
                                          "is_cover", "status enum(3)", "created_at", "updated_at"]),
        ("place_hours", 700, 400, 240, ["id PK", "place_id FK cascade", "day tinyint",
                                        "opens time", "closes time", "is_closed", "note",
                                        "created_at", "updated_at"]),
        ("place_reports", 980, 400, 280, ["id PK", "place_id FK cascade", "user_id FK set null",
                                          "issue_type", "details", "status", "reviewed_by FK",
                                          "reviewed_at", "admin_notes", "created_at", "updated_at"]),
        ("mod_queue", 400, 840, 260, ["id PK", "place_id FK", "action enum(5)", "payload json",
                                      "requested_by FK", "status enum(3)", "reviewed_by FK",
                                      "reviewed_at", "reviewer_note", "created_at", "updated_at"]),
        ("audit_log", 700, 840, 260, ["id PK", "model", "model_id", "actor_id", "action",
                                      "diff json", "created_at"]),
        ("import_runs", 980, 840, 240, ["id PK", "source", "batch_summary", "status enum(3)",
                                         "started_at", "finished_at"]),
    ]

    ids: dict[str, str] = {}
    for name, x, y, w, cols in tables:
        header = f"<b>{name}</b>"
        cid = p.node(header, x, y, w, 26,
                     "swimlane;html=1;startSize=26;horizontal=1;fillColor=#dae8fc;"
                     "strokeColor=#6c8ebf;fontStyle=1;align=center;verticalAlign=middle;")
        ids[name] = cid
        for i, col in enumerate(cols):
            style = ("text;html=1;strokeColor=#6c8ebf;fillColor=#ffffff;align=left;"
                     "spacingLeft=6;verticalAlign=middle;fontSize=11;")
            if "PK" in col:
                style = style.replace("fontSize=11;", "fontSize=11;fontStyle=1;")
            p.node(col, x, y + 26 + i * 20, w, 20, style)

    for a, b in [("places", "categories"), ("places", "boundary_areas"), ("places", "users"),
                 ("place_photos", "places"), ("place_hours", "places"),
                 ("place_reports", "places"), ("place_reports", "users"),
                 ("mod_queue", "places")]:
        p.edge(ids[a], ids[b], "1 : N", "edgeStyle=orthogonalEdgeStyle;rounded=0;html=1;"
                                        "endArrow=ERmany;startArrow=ERone;startFill=0;endFill=0;")
    p.edge(ids["mod_queue"], ids["users"], "1 : N", "edgeStyle=orthogonalEdgeStyle;rounded=0;html=1;"
                                                "endArrow=ERmany;startArrow=ERone;startFill=0;endFill=0;")

    p.note(
        "<b>Cascade caveat.</b> place_photos.place_id, place_hours.place_id and place_reports.place_id "
        "declare cascadeOnDelete, enforced by MySQL rather than Eloquent. A database cascade fires no "
        "model event, so the application removes photo files explicitly through PhotoStorage at every "
        "deletion site. This was a measured defect: 1,803 unreferenced files totalling 4.36 GB before "
        "the leak paths were closed.<br><br>"
        "places.geom is a POINT SRID 4326 carrying a SPATIAL INDEX; boundary_areas.geom is a "
        "POLYGON used for point-in-polygon barangay assignment.",
        1300, 40, 420, 250,
    )
    return p


def fig_deployment() -> Page:
    p = Page()
    p.label("Figure 6 - Proposed network topology and deployment model", 0, 10, 1300)

    cli = p.node("Visitor browser<br>(public map)", 40, 90, 170, 70, TERM)
    adm = p.node("Administrator<br>browser", 40, 260, 170, 70, TERM)

    edge = p.node("InfinityFree shared hosting edge<br><i>Apache + PHP 8.3, no SSH, no shell</i>",
                  280, 80, 250, 90, NODE)

    h1 = p.node("Document root: /htdocs", 620, 80, 200, 60, BOUNDARY)
    ha = p.node("/htdocs/.htaccess<br><i>deny dotfiles, deny backend trees,<br>rewrite into public/</i>",
                600, 170, 240, 80, NODE)
    hb = p.node("/htdocs/public/.htaccess<br><i>front controller,<br>compress, cache, nosniff</i>",
                600, 280, 240, 80, NODE)

    p.edge(cli, edge, "HTTPS")
    p.edge(adm, edge, "HTTPS")
    p.edge(edge, h1)
    p.edge(h1, ha)
    p.edge(h1, hb)

    ap = p.node("Laravel application<br><i>83 routes, file sessions,<br>sync queue</i>",
                930, 150, 210, 90, NODE)
    db = p.node("MySQL 8 shared instance<br><i>POINT and POLYGON geometry,<br>spatial indexes</i>",
                930, 300, 210, 90, SERVER)
    ps = p.node("public/storage/photo tree<br><i>real files, served statically</i>",
                930, 440, 210, 80, NODE)
    lg = p.node("storage/logs<br><i>laravel.log, level error</i>", 930, 560, 210, 70, NODE)

    p.edge(hb, ap, "index.php")
    p.edge(ap, db, "PDO, utf8mb4")
    p.edge(ap, ps, "writes")
    p.edge(ap, lg, "writes")

    pm = p.node("phpMyAdmin<br>(control panel)", 280, 470, 170, 70, NODE)
    ftp = p.node("File manager / FTP<br><i>ftpupload.net</i>", 280, 600, 170, 70, NODE)
    p.edge(pm, db, "imports .sql dump")
    p.edge(ftp, h1, "extract archive")

    ext = p.node("Third-party geocoding and<br>basemap providers", 600, 470, 240, 70, CLOUD)
    p.edge(ap, ext, "outbound HTTPS,<br>server-side only")

    p.note(
        "<b>Why this shape.</b> InfinityFree fixes the document root at /htdocs and offers no shell, "
        "so the framework is deployed one level above public/ and two rewrite layers bridge the gap. "
        "The public disk is a real directory inside the web root because the host cannot create the "
        "storage:link symlink; config/filesystems.php reads FILESYSTEM_PUBLIC_ROOT so the application "
        "resolves the same tree. Sessions and cache are file-backed and the queue is synchronous "
        "because no daemon is available.",
        620, 600, 520, 150,
    )
    return p


# --------------------------------------------------------------------- main

FIGURES = [
    ("01-as-is-network-topology.drawio", "As-Is Network Topology", fig_as_is_network, 1200, 900),
    ("02-as-is-dataflow.drawio", "As-Is Data Flow Diagram", fig_as_is_dataflow, 1400, 1000),
    ("03-to-be-process.drawio", "To-Be Process Model", fig_to_be_process, 1400, 1000),
    ("04-to-be-dataflow.drawio", "To-Be Data Flow Diagram", fig_to_be_dataflow, 1500, 1000),
    ("05-erd.drawio", "Entity Relationship Diagram", fig_erd, 1900, 1250),
    ("06-proposed-deployment-topology.drawio", "Proposed Deployment Topology", fig_deployment, 1400, 1000),
]


def main() -> None:
    os.makedirs(OUT, exist_ok=True)
    for filename, title, builder, w, h in FIGURES:
        page = builder()
        doc = mxfile(title, [(title, page.render())], width=w, height=h)
        path = os.path.join(OUT, filename)
        with open(path, "w", encoding="utf-8") as fh:
            fh.write(doc)
        print(f"wrote {filename}  ({len(page.cells)} cells)")


if __name__ == "__main__":
    main()
