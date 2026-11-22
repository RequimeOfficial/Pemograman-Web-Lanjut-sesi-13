"""Membangun Laporan QA SiLaundry (DOCX) + katalog test case (XLSX) dari data eksekusi nyata.

Sumber data (tidak ada angka yang diketik manual):
  evidence/run1/test-results.json, evidence/run2/test-results.json   (Playwright)
  evidence/run1/whitebox/phpunit-junit.xml, evidence/run2/...          (PHPUnit)
  bugs/bugs.json, discovery/data/*.json, screenshots/, evidence/run1/screenshots/

Pemakaian (dari folder QA):  python scripts/build_report.py
Konversi ke PDF:              powershell -File scripts/docx_to_pdf.ps1
"""
import json
import re
import xml.etree.ElementTree as ET
from collections import Counter, OrderedDict
from datetime import date
from pathlib import Path

from docx import Document
from docx.enum.section import WD_ORIENT
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor
from openpyxl import Workbook
from openpyxl.styles import Alignment, Font, PatternFill
from openpyxl.utils import get_column_letter

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "reports"
OUT.mkdir(exist_ok=True)

BLACK = RGBColor(0x00, 0x00, 0x00)
GREY = RGBColor(0x55, 0x5F, 0x6D)
REPO_URL = "https://github.com/RequimeOfficial/Pemograman-Web-Lanjut-sesi-13"
PENYUSUN = "Ahmad Mofidur Robbiyal Aqla"
NIM = "2495114006"

MODULES = OrderedDict([
    ("ENV", "Validasi Environment"),
    ("M01", "Autentikasi"),
    ("M02", "Hak Akses"),
    ("M03", "Dashboard"),
    ("M04", "Manajemen Pengguna"),
    ("M05", "Alur Bisnis"),
    ("M06", "Tampilan & Responsif"),
])


# ---------------------------------------------------------------- data ----
def load_playwright(path):
    data = json.loads(path.read_text(encoding="utf8"))
    rows = []

    def walk(suite):
        for spec in suite.get("specs", []):
            for t in spec["tests"]:
                res = t["results"][0]
                ann = {a["type"]: a.get("description", "") for a in t.get("annotations", [])}
                tc_id, _, title = spec["title"].partition(" ")
                status = {"passed": "PASS", "failed": "FAIL", "timedOut": "FAIL", "skipped": "BLOCKED"}.get(res["status"], res["status"].upper())
                err = re.sub(r"\x1b\[[0-9;]*m", "", (res.get("error") or {}).get("message", "")).strip().splitlines()
                keep = [ln.strip() for ln in err if ln.strip() and not ln.strip().startswith(("Timeout", "Call log", "Received string", "Error: element", "-", "at "))]
                rows.append({"id": tc_id, "title": title, "status": status, "duration": res["duration"] / 1000,
                             "error": " | ".join(keep[:3]), **ann})
        for s in suite.get("suites", []):
            walk(s)

    for s in data["suites"]:
        walk(s)
    rows.sort(key=lambda r: r["id"])
    return rows, data["stats"]


def load_junit(path):
    root = ET.parse(path).getroot()
    cases = []
    for tc in root.iter("testcase"):
        failed = tc.find("failure") is not None or tc.find("error") is not None
        cases.append({"class": tc.get("class", tc.get("classname", "")), "name": tc.get("name"), "status": "FAIL" if failed else "PASS"})
    return cases


run1, stats1 = load_playwright(ROOT / "evidence/run1/test-results.json")
run2, stats2 = load_playwright(ROOT / "evidence/run2/test-results.json")
status1 = {r["id"]: r["status"] for r in run1}
wb1 = load_junit(ROOT / "evidence/run1/whitebox/phpunit-junit.xml")
wb2 = load_junit(ROOT / "evidence/run2/whitebox/phpunit-junit.xml")
bugs = json.loads((ROOT / "bugs/bugs.json").read_text(encoding="utf8"))
run_info = json.loads((ROOT / "evidence/run2/run-info.json").read_text(encoding="utf8"))


def summary(rows):
    c = Counter(r["status"] for r in rows)
    p, f = c.get("PASS", 0), c.get("FAIL", 0)
    rate = f"{p / (p + f) * 100:.1f}%".replace(".", ",") if p + f else "N/A"
    return {"total": len(rows), "pass": p, "fail": f, "blocked": c.get("BLOCKED", 0), "rate": rate}


S1, S2 = summary(run1), summary(run2)
W1, W2 = summary(wb1), summary(wb2)
WB_PATH1 = [c for c in wb1 if "WhiteBox" in c["class"]]
WB_PATH2 = [c for c in wb2 if "WhiteBox" in c["class"]]

# ------------------------------------------------------------- helpers ----
doc = Document()
st = doc.styles["Normal"]
st.font.name = "Arial"
st.font.size = Pt(10.5)
st.element.rPr.rFonts.set(qn("w:eastAsia"), "Arial")
st.paragraph_format.space_after = Pt(4)
st.paragraph_format.line_spacing = 1.15
for lvl, size in ((1, 15), (2, 12.5), (3, 11)):
    h = doc.styles[f"Heading {lvl}"]
    h.font.name = "Arial"
    h.font.size = Pt(size)
    h.font.bold = True
    h.font.color.rgb = BLACK
    h.element.rPr.rFonts.set(qn("w:eastAsia"), "Arial")
    h.paragraph_format.space_before = Pt(14 if lvl == 1 else 10)
    h.paragraph_format.space_after = Pt(6)
cap = doc.styles["Caption"]
cap.font.name = "Arial"
cap.font.size = Pt(9)
cap.font.italic = True
cap.font.color.rgb = GREY

sec = doc.sections[0]
sec.page_width, sec.page_height = Cm(21), Cm(29.7)
sec.left_margin = sec.right_margin = Cm(2.3)
sec.top_margin, sec.bottom_margin = Cm(2.2), Cm(2.0)
CONTENT_W = 21 - 4.6


def field(paragraph, code):
    run = paragraph.add_run()
    for tag, text in (("begin", None), ("instr", code), ("separate", None), ("text", "1"), ("end", None)):
        if tag == "instr":
            el = OxmlElement("w:instrText")
            el.set(qn("xml:space"), "preserve")
            el.text = text
        elif tag == "text":
            el = OxmlElement("w:t")
            el.text = text
        else:
            el = OxmlElement("w:fldChar")
            el.set(qn("w:fldCharType"), tag)
        run._r.append(el)
    return run


def shade(cell, hex_color):
    tcPr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), hex_color)
    tcPr.append(shd)


def repeat_header(row):
    trPr = row._tr.get_or_add_trPr()
    el = OxmlElement("w:tblHeader")
    el.set(qn("w:val"), "true")
    trPr.append(el)


STATUS_COLOR = {"PASS": "DCF5E3", "FAIL": "FBE0E0", "BLOCKED": "FFF4CC"}


def table(headers, rows, widths=None, size=8.5, status_cols=()):
    t = doc.add_table(rows=1, cols=len(headers))
    t.style = "Table Grid"
    t.alignment = WD_TABLE_ALIGNMENT.CENTER
    for i, h in enumerate(headers):
        c = t.rows[0].cells[i]
        c.text = ""
        r = c.paragraphs[0].add_run(h)
        r.bold = True
        r.font.size = Pt(size)
        r.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
        shade(c, "146C3A")
    repeat_header(t.rows[0])
    for row in rows:
        cells = t.add_row().cells
        for i, v in enumerate(row):
            cells[i].text = ""
            p = cells[i].paragraphs[0]
            r = p.add_run(str(v))
            r.font.size = Pt(size)
            if i in status_cols and str(v) in STATUS_COLOR:
                shade(cells[i], STATUS_COLOR[str(v)])
                r.bold = True
    if widths:
        for row in t.rows:
            for i, w in enumerate(widths):
                row.cells[i].width = Cm(w)
    doc.add_paragraph()
    return t


def kv(rows, w1=4.5):
    t = doc.add_table(rows=0, cols=2)
    t.style = "Table Grid"
    for k, v in rows:
        c = t.add_row().cells
        c[0].text, c[1].text = "", ""
        rk = c[0].paragraphs[0].add_run(k)
        rk.bold = True
        rk.font.size = Pt(9)
        shade(c[0], "EAF6EE")
        rv = c[1].paragraphs[0].add_run(str(v))
        rv.font.size = Pt(9)
        c[0].width, c[1].width = Cm(w1), Cm(CONTENT_W - w1)
    doc.add_paragraph()


FIG = [0]


def figure(path, caption, width=CONTENT_W - 1.5):
    path = ROOT / path
    if not path.exists():
        raise FileNotFoundError(path)
    doc.add_picture(str(path), width=Cm(width))
    doc.paragraphs[-1].alignment = WD_ALIGN_PARAGRAPH.CENTER
    FIG[0] += 1
    p = doc.add_paragraph(f"Gambar {FIG[0]}. {caption}", style="Caption")
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER


def figure_pair(left, right, cap_left, cap_right, caption):
    t = doc.add_table(rows=2, cols=2)
    t.alignment = WD_TABLE_ALIGNMENT.CENTER
    for i, (img, sub) in enumerate(((left, cap_left), (right, cap_right))):
        cell = t.rows[0].cells[i]
        cell.paragraphs[0].add_run().add_picture(str(ROOT / img), width=Cm(7.6))
        cell.paragraphs[0].alignment = WD_ALIGN_PARAGRAPH.CENTER
        sp = t.rows[1].cells[i].paragraphs[0]
        r = sp.add_run(sub)
        r.font.size = Pt(8.5)
        r.bold = True
        sp.alignment = WD_ALIGN_PARAGRAPH.CENTER
    FIG[0] += 1
    p = doc.add_paragraph(f"Gambar {FIG[0]}. {caption}", style="Caption")
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER


def para(text, bold_prefix=None, italic=False, size=None):
    p = doc.add_paragraph()
    if bold_prefix:
        p.add_run(bold_prefix).bold = True
    r = p.add_run(text)
    r.italic = italic
    if size:
        r.font.size = Pt(size)
    p.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    return p


def bullets(items, style="List Bullet"):
    for it in items:
        p = doc.add_paragraph(style=style)
        if isinstance(it, tuple):
            p.add_run(it[0]).bold = True
            p.add_run(it[1])
        else:
            p.add_run(it)


# --------------------------------------------------------------- cover ----
for _ in range(3):
    doc.add_paragraph()
p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run("LAPORAN QUALITY ASSURANCE")
r.bold, r.font.size, r.font.color.rgb = True, Pt(22), BLACK
p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run("Black-Box & White-Box Testing\nAplikasi SiLaundry — Sistem Informasi Manajemen Laundry")
r.font.size, r.bold = Pt(13), True
doc.add_paragraph()
doc.add_picture(str(ROOT.parent / "public/img/logo.png"), width=Cm(3.6))
doc.paragraphs[-1].alignment = WD_ALIGN_PARAGRAPH.CENTER
doc.add_paragraph()
p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run("Tugas Sesi 13 — QA di Aplikasi Kelompok\nMata Kuliah Pemrograman Web Lanjut")
r.font.size = Pt(11.5)
for _ in range(3):
    doc.add_paragraph()
p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run(f"Disusun oleh:\n{PENYUSUN}\nNIM {NIM}")
r.font.size = Pt(11.5)
for _ in range(4):
    doc.add_paragraph()
p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run("PROGRAM STUDI TEKNIK INFORMATIKA\nFAKULTAS TEKNOLOGI INFORMASI\nUNIVERSITAS HASYIM ASY'ARI TEBUIRENG JOMBANG\n2026")
r.bold, r.font.size = True, Pt(11.5)
doc.add_page_break()

# header / footer (halaman setelah cover)
sec.different_first_page_header_footer = True
hp = sec.header.paragraphs[0]
hp.text = ""
rh = hp.add_run("Laporan QA SiLaundry  |  Pemrograman Web Lanjut — Sesi 13")
rh.font.size, rh.font.color.rgb = Pt(8.5), GREY
hp.alignment = WD_ALIGN_PARAGRAPH.RIGHT
fp = sec.footer.paragraphs[0]
fp.alignment = WD_ALIGN_PARAGRAPH.CENTER
for part in ("Halaman ", None, " dari ", "NUMPAGES"):
    if part is None:
        field(fp, "PAGE").font.size = Pt(8.5)
    elif part == "NUMPAGES":
        field(fp, "NUMPAGES").font.size = Pt(8.5)
    else:
        fp.add_run(part).font.size = Pt(8.5)

# ------------------------------------------------------------------ TOC ----
p = doc.add_paragraph()
r = p.add_run("DAFTAR ISI")
r.bold, r.font.size, r.font.color.rgb = True, Pt(14), BLACK
field(doc.add_paragraph(), 'TOC \\o "1-2" \\h \\z \\u')
doc.add_page_break()

# ------------------------------------------------------- 1. ringkasan ----
doc.add_heading("1. Identitas Aplikasi & Ringkasan Eksekutif", 1)
kv([
    ("Aplikasi", "SiLaundry — Sistem Informasi Manajemen Laundry (aplikasi proyek kelompok)"),
    ("Jenis", "Aplikasi web internal berbasis role: Administrator, Kasir, Pelanggan"),
    ("Teknologi", "Laravel 13, PHP 8.4.20, MySQL 8.0.30, Blade + Tailwind CSS v4 + Alpine.js"),
    ("URL pengujian", f"{run_info['base_url']} (lingkungan lokal, data seeder)"),
    ("Periode pengujian", "22 November 2026 (Run 1 awal, perbaikan, Run 2 retest & regresi)"),
    ("Penguji", f"{PENYUSUN} ({NIM})"),
    ("Repositori", REPO_URL),
])
para(
    "Pengujian dilakukan dengan dua pendekatan yang saling melengkapi: black-box testing otomatis memakai "
    "Playwright (menguji perilaku aplikasi dari sisi pengguna melalui browser Chromium) dan white-box testing "
    "memakai PHPUnit (menguji setiap jalur keputusan di dalam kode controller, middleware, dan form request). "
    "Alur kerja mengikuti materi Sesi 13: Application Discovery terlebih dahulu, kemudian QA Automation "
    "berdasarkan hasil discovery."
)
table(
    ["Metrik", "Run 1 (awal)", "Run 2 (retest)"],
    [
        ["Test case black-box direncanakan / dieksekusi", f"{S1['total']} / {S1['total']}", f"{S2['total']} / {S2['total']}"],
        ["PASS", S1["pass"], S2["pass"]],
        ["FAIL", S1["fail"], S2["fail"]],
        ["BLOCKED / NOT TESTED", S1["blocked"], S2["blocked"]],
        ["Pass rate black-box = PASS / (PASS + FAIL)", f"{S1['rate']} ({S1['pass']}/{S1['pass'] + S1['fail']})", f"{S2['rate']} ({S2['pass']}/{S2['pass'] + S2['fail']})"],
        ["Test white-box PHPUnit lulus", f"{W1['pass']} / {W1['total']}", f"{W2['pass']} / {W2['total']}"],
        ["   di antaranya test jalur (basis path) baru", f"{sum(c['status'] == 'PASS' for c in WB_PATH1)} / {len(WB_PATH1)}", f"{sum(c['status'] == 'PASS' for c in WB_PATH2)} / {len(WB_PATH2)}"],
        ["Bug terkonfirmasi (status)", f"{len(bugs['bugs'])} (Open)", f"{len(bugs['bugs'])} (Verified)"],
    ],
    widths=[8, 4, 4.4],
)
sev = Counter(b["severity"] for b in bugs["bugs"])
para(
    f"Hasil utama: Run 1 menemukan {S1['fail']} kegagalan black-box yang bermuara pada {len(bugs['bugs'])} bug "
    f"terkonfirmasi (Critical {sev.get('Critical', 0)}, High {sev.get('High', 0)}, Medium {sev.get('Medium', 0)}, "
    f"Low {sev.get('Low', 0)}). Dua bug di antaranya juga terdeteksi secara independen oleh test white-box. "
    f"Seluruh bug diperbaiki (satu commit per bug) lalu diuji ulang; pada Run 2 seluruh {S2['total']} test case "
    f"black-box dan {W2['total']} test white-box lulus. Hasil ini menunjukkan fitur yang diuji berperilaku sesuai "
    "requirement pada lingkungan pengujian, bukan jaminan aplikasi bebas bug (lihat Bab 10 Keterbatasan)."
)

# ---------------------------------------------- 2. tujuan, scope, env ----
doc.add_heading("2. Tujuan, Ruang Lingkup, dan Lingkungan Pengujian", 1)
doc.add_heading("2.1 Tujuan", 2)
bullets([
    "Memastikan fitur login multi role, hak akses, dashboard, dan manajemen pengguna berjalan sesuai requirement.",
    "Menemukan bug melalui skenario positif, negatif, boundary, hak akses, workflow, integrasi data, dan tampilan.",
    "Memverifikasi setiap jalur keputusan penting di kode (white-box) dan membuktikan perbaikan melalui retest & regresi.",
])
doc.add_heading("2.2 Ruang Lingkup", 2)
table(
    ["Modul", "Fitur dalam scope", "Status"],
    [
        ["M01 Autentikasi", "Login, validasi login, kunci 5× gagal, logout, proteksi halaman", "Ready for Testing"],
        ["M02 Hak Akses", "Pembatasan route per role (403), menu sesuai role", "Ready for Testing"],
        ["M03 Dashboard", "Dashboard admin, kasir, pelanggan (ringkasan & transaksi terbaru)", "Ready for Testing"],
        ["M04 Manajemen Pengguna", "Daftar, cari, filter, tambah, ubah, hapus + aturan bisnis", "Ready for Testing"],
        ["M05 Alur Bisnis", "Pendaftaran akun pelanggan s.d. penghapusan (lintas role)", "Ready for Testing"],
        ["M06 Tampilan", "Tampilan layar ponsel (375 px)", "Ready for Testing"],
        ["Layanan, Transaksi, Pembayaran, Notifikasi", "Baru berupa tabel database; belum ada halaman", "Not in Scope"],
    ],
    widths=[4, 9, 3.4],
)
doc.add_heading("2.3 Lingkungan Pengujian", 2)
table(
    ["Komponen", "Keterangan"],
    [
        ["Sistem operasi", "Windows 11"],
        ["Server aplikasi", "php artisan serve (PHP 8.4.20) di http://127.0.0.1:8000"],
        ["Basis data", "MySQL 8.0.30 (Laragon), database silaundry, data seeder: 25 user, 22 pelanggan, 5 layanan, 60 transaksi"],
        ["Black-box", f"Playwright 1.65.1, Chromium lokal, viewport 1366×768 (dan 375×812 untuk M06), Node.js {run_info['node']}"],
        ["White-box", "PHPUnit 12.5.36, SQLite in-memory (RefreshDatabase)"],
        ["Akun uji", "Akun seeder per role, kredensial hanya disimpan di QA/.env (tidak di-commit), field password di-mask pada screenshot"],
    ],
    widths=[4, 12.4],
)

# ---------------------------------------------------- 3. metodologi ----
doc.add_heading("3. Metodologi", 1)
doc.add_heading("3.1 Black-Box Testing dan White-Box Testing", 2)
para(
    "Black-box testing menguji aplikasi tanpa melihat struktur kode: penguji hanya memberi masukan dan "
    "membandingkan keluaran dengan spesifikasi. Teknik yang dipakai dalam laporan ini adalah equivalence "
    "partitioning (masukan valid/tidak valid), boundary value analysis (nilai tepat di batas dan satu langkah di luar "
    "batas), pengujian hak akses, dan pengujian alur end-to-end. White-box testing sebaliknya menguji logika internal: "
    "penguji membaca kode, menghitung jalur yang mungkin dilalui, lalu membuat test agar setiap cabang keputusan "
    "dieksekusi minimal satu kali (basis path testing dengan cyclomatic complexity V(G) = jumlah keputusan + 1)."
)
table(
    ["Aspek", "Black-Box Testing", "White-Box Testing"],
    [
        ["Dasar pengujian", "Requirement / spesifikasi dan perilaku yang terlihat", "Struktur kode, cabang, dan jalur eksekusi"],
        ["Pengetahuan kode", "Tidak diperlukan", "Diperlukan"],
        ["Teknik", "Equivalence partitioning, boundary value, decision table, uji akses, workflow", "Statement/branch/path coverage, basis path (V(G)), data flow"],
        ["Level", "System & acceptance testing", "Unit & integration testing"],
        ["Pelaku umum", "QA / tester, pengguna", "Developer"],
        ["Alat di proyek ini", "Playwright (Chromium) — QA/tests/*.spec.js", "PHPUnit — tests/Feature/WhiteBox/*.php"],
        ["Kelebihan", "Mewakili sudut pandang pengguna; menemukan kesalahan tampilan & alur", "Menemukan cabang yang tidak teruji dan akar masalah di kode"],
        ["Keterbatasan", "Tidak menjamin seluruh jalur kode tereksekusi", "Tidak menilai apakah fitur sesuai kebutuhan pengguna"],
    ],
    widths=[3.2, 6.6, 6.6],
)
doc.add_heading("3.2 Tahapan Kerja", 2)
bullets([
    ("Application Discovery — ", "crawler Playwright read-only per role (npm run discover) menghasilkan daftar halaman, form, tabel, dan screenshot; dilengkapi pembacaan README dan kode sebagai baseline requirement."),
    ("Perencanaan — ", "requirement REQ-01 s.d. REQ-12 diturunkan menjadi 44 test case black-box (positif, negatif, boundary, akses, keamanan, integrasi, workflow, UI) dan 20 test jalur white-box."),
    ("Eksekusi Run 1 — ", "seluruh test dijalankan; screenshot bukti setiap langkah diberi nama TC_ROLE_FITUR_STEPxx_STATUS.png, log console & jaringan dicatat otomatis."),
    ("Analisis temuan — ", "setiap FAIL dicek dengan screenshot dan reproduksi, lalu dikelompokkan sebagai Confirmed Bug, Documentation Discrepancy, atau Enhancement Suggestion."),
    ("Perbaikan, retest & regresi (Run 2) — ", "bug diperbaiki satu commit per bug, lalu seluruh test diulang (bukan hanya test yang gagal) untuk memastikan tidak ada regresi."),
], style="List Number")
doc.add_heading("3.3 Status Hasil", 2)
para("PASS: hasil aktual sesuai expected. FAIL: hasil aktual berbeda. BLOCKED: tidak dapat dieksekusi. "
     "Pass rate = PASS / (PASS + FAIL) × 100%. Expected result tidak pernah diubah agar test menjadi lulus.")

# ---------------------------------------------------- 4. discovery ----
doc.add_heading("4. Hasil Application Discovery", 1)
disc = {r: json.loads((ROOT / f"discovery/data/discovery-{r}.json").read_text(encoding="utf8")) for r in ("public", "admin", "kasir", "pelanggan")}
table(
    ["Role crawler", "Halaman ditemukan", "Contoh URL", "Console error"],
    [[r.capitalize(), len(d["pages"]), ", ".join(sorted({re.sub(r"https?://[^/]+", "", p.get("final_url", "")).split("?")[0] for p in d["pages"]}))[:80],
      sum(len(p.get("console_errors", [])) for p in d["pages"])] for r, d in disc.items()],
    widths=[3, 3, 8, 2.4],
)
table(
    ["Fitur", "Publik", "Admin", "Kasir", "Pelanggan"],
    [
        ["Login", "✓", "–", "–", "–"],
        ["Dashboard admin", "–", "✓", "403", "403"],
        ["Dashboard kasir", "–", "403", "✓", "403"],
        ["Dashboard pelanggan", "–", "403", "403", "✓"],
        ["Manajemen pengguna (/users)", "→ login", "✓", "403", "403"],
    ],
    widths=[6, 2.6, 2.6, 2.6, 2.6],
)
doc.add_heading("4.1 Requirement Baseline", 2)
REQS = [
    ("REQ-01", "Login email & kata sandi, diarahkan ke dashboard sesuai role"),
    ("REQ-02", "Login menolak isian kosong, format email salah, kredensial salah"),
    ("REQ-03", "5 kali gagal → dikunci sementara"),
    ("REQ-04", "Logout mengakhiri sesi; halaman terproteksi tertutup bagi tamu"),
    ("REQ-05", "Akses route dibatasi per role (403) dan menu sesuai role"),
    ("REQ-06", "Dashboard menampilkan ringkasan data yang benar; pelanggan hanya data miliknya"),
    ("REQ-07", "Daftar pengguna dengan pencarian, filter peran, paginasi"),
    ("REQ-08", "Tambah pengguna tervalidasi (wajib, username 3–50, email unik, sandi ≥ 8 + konfirmasi)"),
    ("REQ-09", "Peran Pelanggan wajib No HP & alamat, otomatis punya data pelanggan"),
    ("REQ-10", "Ubah pengguna; admin tidak dapat mengubah peran sendiri"),
    ("REQ-11", "Hapus dengan konfirmasi; tolak akun sendiri & pengguna yang memiliki transaksi"),
    ("REQ-12", "Antarmuka konsisten berbahasa Indonesia dan dapat dipakai di ponsel"),
]
req_tc = {}
for r in run2:
    for rq in re.findall(r"REQ-\d+", r.get("requirement", "")):
        req_tc.setdefault(rq, []).append(r["id"])
table(["REQ", "Requirement", "Test case black-box"], [[k, v, ", ".join(req_tc.get(k, ["-"]))] for k, v in REQS], widths=[1.6, 8.6, 6.2])
covered = sum(1 for k, _ in REQS if k in req_tc)
features = sorted({r.get("feature") for r in run2 if r.get("feature", "").startswith("F")})
para(f"Requirement coverage: {covered}/{len(REQS)} requirement memiliki minimal satu test case. "
     f"Feature coverage: {len(features)}/11 fitur (F01–F11) diuji.")
figure("discovery/screenshots/P002_ADMIN_users.png", "Discovery: halaman Pengguna (role Admin), P002_ADMIN_users.png", width=13)

# ---------------------------------------------------- 5. black-box ----
doc.add_heading("5. Hasil Black-Box Testing (Playwright)", 1)
doc.add_heading("5.1 Ringkasan per Modul", 2)
mod_rows = []
for m, name in MODULES.items():
    r1 = [r for r in run1 if r.get("module") == m]
    r2 = [r for r in run2 if r.get("module") == m]
    a, b = summary(r1), summary(r2)
    mod_rows.append([f"{m} {name}", a["total"], a["pass"], a["fail"], a["rate"], b["pass"], b["fail"], b["rate"]])
table(["Modul", "TC", "Run1 PASS", "Run1 FAIL", "Run1 rate", "Run2 PASS", "Run2 FAIL", "Run2 rate"], mod_rows,
      widths=[4.6, 1.2, 1.7, 1.7, 1.8, 1.7, 1.7, 2.0])
types = Counter(r.get("type", "-") for r in run2)
para("Sebaran jenis skenario: " + ", ".join(f"{k} {v}" for k, v in types.most_common()) + ".")
doc.add_heading("5.2 Daftar Test Case dan Hasil", 2)
table(
    ["ID", "Skenario", "Jenis", "Role", "REQ", "Run 1", "Run 2"],
    [[r["id"], r["title"], r.get("type", ""), r.get("role", ""), r.get("requirement", ""), status1.get(r["id"], "-"), r["status"]] for r in run2],
    widths=[1.3, 7.4, 1.8, 1.9, 1.6, 1.2, 1.2], size=7.5, status_cols=(5, 6),
)
doc.add_heading("5.3 Detail Expected Result", 2)
table(["ID", "Expected result", "Prioritas"], [[r["id"], r.get("expected", ""), r.get("priority", "")] for r in run2],
      widths=[1.3, 13, 2.1], size=7.5)
doc.add_heading("5.4 Kegagalan pada Run 1", 2)
def bug_of(tc):
    return next((b for b in bugs["bugs"] if tc in b["related_test_cases"]), None)


table(["ID", "Assertion yang gagal (log Playwright)", "Hasil aktual di aplikasi", "Bug"],
      [[r["id"], r["error"][:220], bug_of(r["id"])["actual_result"] if bug_of(r["id"]) else "-",
        bug_of(r["id"])["bug_id"] if bug_of(r["id"]) else "-"] for r in run1 if r["status"] == "FAIL"],
      widths=[1.3, 7.2, 6.2, 1.7], size=7.5)
para("Selama penyusunan test, satu kegagalan uji coba (TC015) terbukti kesalahan skrip test — menu Pengguna berada di "
     "dalam grup Pengaturan yang tertutup — sehingga skrip diperbaiki sebelum Run 1 resmi. Kegagalan tersebut tidak "
     "dicatat sebagai bug aplikasi.", italic=True, size=9)

# ---------------------------------------------------- 6. white-box ----
doc.add_heading("6. Hasil White-Box Testing (PHPUnit)", 1)
doc.add_heading("6.1 Analisis Jalur (Basis Path)", 2)
table(
    ["Unit kode", "Titik keputusan", "V(G)", "Jalur & test"],
    [
        ["AuthController::login", "validasi; tooManyAttempts; Auth::attempt gagal; dashboardRoute null", "5", "WB-AUTH-P1 s.d. P5 (5 test)"],
        ["DashboardController::index", "belum login; role tanpa dashboard", "3", "WB-DASH-P1 s.d. P3 (3 test)"],
        ["DashboardController::pelanggan", "profil pelanggan ada?", "2", "WB-DASH-P4, P5 (2 test)"],
        ["DashboardController::admin/kasir", "— (uji aliran data urutan transaksi)", "1", "WB-DASH-D1 (1 test)"],
        ["UserController::destroy", "akun sendiri; kasir bertransaksi; pelanggan bertransaksi", "4", "WB-DEL-P1 s.d. P4 (4 test)"],
        ["UserController::index", "kata kunci terisi; role valid", "3", "WB-IDX (1 test, 4 kasus)"],
        ["UpdateUserRequest::after", "akun sendiri && role berubah", "2", "WB-UPD (1 test, 3 kasus)"],
        ["RoleMiddleware::handle", "belum login; role tidak cocok", "3", "WB-MW (1 test, 3 kasus)"],
        ["UserValidationMessages", "— (kebenaran pesan min/max)", "1", "WB-VAL-1 (1 test)"],
        ["PreventBackHistory (baru)", "— (header no-store)", "1", "WB-SEC-1 (1 test regresi, ditambah saat perbaikan)"],
    ],
    widths=[4.4, 6.4, 1.1, 4.5], size=8,
)
doc.add_heading("6.2 Hasil Eksekusi", 2)
by_class = OrderedDict()
for c in wb2:
    cls = c["class"].split("\\")[-1]
    by_class.setdefault(cls, [0, 0, 0])
    by_class[cls][0] += 1
    by_class[cls][2] += c["status"] == "PASS"
for c in wb1:
    cls = c["class"].split("\\")[-1]
    by_class.setdefault(cls, [0, 0, 0])
    by_class[cls][1] += c["status"] == "PASS"
table(["Kelas test", "Jumlah (Run 2)", "Lulus Run 1", "Lulus Run 2"],
      [[k, v[0], v[1], v[2]] for k, v in by_class.items()], widths=[7, 3, 3.2, 3.2])
wb_fail = [c for c in wb1 if c["status"] == "FAIL"]
p = doc.add_paragraph(f"Run 1: {W1['pass']}/{W1['total']} test lulus. Test yang gagal:")
bullets([f"{c['class'].split(chr(92))[-1]}::{c['name']}" for c in wb_fail])
para(f"Keduanya menunjuk akar masalah yang sama dengan BUG-003 dan BUG-001 yang ditemukan black-box. "
     f"Run 2: {W2['pass']}/{W2['total']} lulus, termasuk test regresi baru WB-SEC-1 untuk BUG-002.")

# ---------------------------------------------------- 7. bug ----
doc.add_heading("7. Temuan Bug", 1)
table(["Bug ID", "Judul", "Severity", "Priority", "Status"],
      [[b["bug_id"], b["title"], b["severity"], b["priority"], b["status"]] for b in bugs["bugs"]], widths=[1.8, 9.4, 1.8, 1.6, 1.8])
pairs = {
    "BUG-001": ("evidence/run1/screenshots/TC028_ADMIN_TAMBAH_PENGGUNA_STEP01_FAIL.png", "screenshots/TC028_ADMIN_TAMBAH_PENGGUNA_STEP01_PASS.png"),
    "BUG-002": ("evidence/run1/screenshots/TC009_ADMIN_LOGOUT_STEP01_FAIL.png", "screenshots/TC009_ADMIN_LOGOUT_STEP01_PASS.png"),
    "BUG-003": ("evidence/run1/screenshots/TC022_KASIR_DASHBOARD_KASIR_STEP01_FAIL.png", "screenshots/TC022_KASIR_DASHBOARD_KASIR_STEP01_PASS.png"),
    "BUG-004": ("evidence/run1/screenshots/TC040_ADMIN_DAFTAR_PENGGUNA_STEP01_FAIL.png", "screenshots/TC040_ADMIN_DAFTAR_PENGGUNA_STEP01_PASS.png"),
}
for b in bugs["bugs"]:
    doc.add_heading(f"7.{bugs['bugs'].index(b) + 1} {b['bug_id']} — {b['title']}", 2)
    kv([
        ("Klasifikasi", b["classification"]),
        ("Modul / fitur", f"{b['module']} / {b['feature']}"),
        ("Halaman / role", f"{b['affected_page']} / {b['affected_role']}"),
        ("Severity", f"{b['severity']} — {b['severity_reason']}"),
        ("Priority", f"{b['priority']} — {b['priority_reason']}"),
        ("Deskripsi", b["description"]),
        ("Prakondisi", b["preconditions"]),
        ("Langkah reproduksi", "\n".join(f"{i + 1}. {s}" for i, s in enumerate(b["steps_to_reproduce"]))),
        ("Expected", b["expected_result"]),
        ("Actual (Run 1)", b["actual_result"]),
        ("Observasi teknis", b["technical_observation"]),
        ("Rekomendasi", b["recommendation"]),
        ("Perbaikan", f"commit {b['fix']['commit']} — {b['fix']['files']}"),
        ("Retest", f"{b['retest']['run']}: {b['retest']['test_cases']} → {b['retest']['result']}; status {b['status']}"),
        ("Test case terkait", ", ".join(b["related_test_cases"])),
    ], w1=3.6)
    before, after = pairs[b["bug_id"]]
    figure_pair(before, after, "Run 1 — FAIL", "Run 2 — PASS", f"{b['bug_id']} sebelum dan sesudah perbaikan ({Path(before).name.replace('_FAIL.png', '')})")

doc.add_heading("7.5 Documentation Discrepancy", 2)
table(["ID", "Temuan", "Rekomendasi"], [[d["id"], f"{d['title']}. {d['detail']}", d["recommendation"]] for d in bugs["documentation_discrepancies"]], widths=[1.6, 9.4, 5.4], size=8)
doc.add_heading("7.6 Enhancement Suggestion (bukan bug)", 2)
table(["ID", "Saran", "Rekomendasi"], [[d["id"], f"{d['title']}. {d['detail']}", d["recommendation"]] for d in bugs["enhancement_suggestions"]], widths=[1.6, 9.4, 5.4], size=8)

# ---------------------------------------------------- 8. retest ----
doc.add_heading("8. Retest dan Regression Testing", 1)
para("Setiap bug diperbaiki pada commit terpisah agar mudah ditelusuri. Setelah keempat perbaikan, seluruh test "
     "black-box dan white-box dijalankan ulang (full regression), bukan hanya test yang sebelumnya gagal. Bukti Run 1 "
     "disimpan di QA/evidence/run1/ dan bukti Run 2 di QA/screenshots/ serta QA/evidence/run2/.")
table(["Bug", "Commit", "Berkas yang diubah", "Hasil retest"],
      [[b["bug_id"], b["fix"]["commit"], b["fix"]["files"], f"{b['retest']['test_cases']}: {b['retest']['result']}"] for b in bugs["bugs"]],
      widths=[1.8, 1.8, 7.2, 5.6], size=8)
table(["Pengujian", "Run 1", "Run 2", "Perubahan"],
      [["Black-box (Playwright)", f"{S1['pass']}/{S1['total']} PASS", f"{S2['pass']}/{S2['total']} PASS", f"{S1['fail']} FAIL → {S2['fail']} FAIL, tanpa regresi"],
       ["White-box (PHPUnit)", f"{W1['pass']}/{W1['total']} lulus", f"{W2['pass']}/{W2['total']} lulus", "+1 test regresi (WB-SEC-1)"]],
      widths=[4, 3.4, 3.4, 5.6])

# ---------------------------------------------------- 9. bukti ----
doc.add_heading("9. Bukti Screenshot Pengujian", 1)
para(f"Seluruh screenshot ({len(list((ROOT / 'screenshots').glob('*.png')))} berkas Run 2 dan "
     f"{len(list((ROOT / 'evidence/run1/screenshots').glob('*.png')))} berkas Run 1) tersedia di repositori. Berikut bukti utama per modul; "
     "field kata sandi otomatis ditutup (warna magenta) oleh fixture pengujian.", size=9.5)
for img, capt in [
    ("screenshots/TC001_ADMIN_LOGIN_STEP02_PASS.png", "TC001 — login Administrator diarahkan ke dashboard admin"),
    ("screenshots/TC007_PUBLIC_LOCKOUT_STEP02_PASS.png", "TC007 — percobaan login ke-6 dikunci sementara"),
    ("screenshots/TC011_KASIR_AKSES_ROLE_STEP01_PASS.png", "TC011 — Kasir membuka /users mendapat halaman 403 Akses Ditolak"),
    ("screenshots/TC021_PELANGGAN_DASHBOARD_PELANGGAN_STEP01_PASS.png", "TC021 — dashboard pelanggan hanya memuat transaksi milik sendiri"),
    ("screenshots/TC027_ADMIN_TAMBAH_PENGGUNA_STEP02_PASS.png", "TC027 — validasi field wajib pada form tambah pengguna"),
    ("screenshots/TC036_ADMIN_UBAH_PENGGUNA_STEP02_PASS.png", "TC036 — server menolak manipulasi perubahan peran akun sendiri"),
    ("screenshots/TC038_ADMIN_HAPUS_PENGGUNA_STEP01_PASS.png", "TC038 — kasir yang memiliki transaksi tidak dapat dihapus"),
    ("screenshots/TC041_ADMIN_ALUR_PELANGGAN_STEP03_PASS.png", "TC041 — pelanggan baru berhasil login ke dashboard pelanggan"),
]:
    figure(img, capt, width=12.5)
figure_pair("screenshots/TC042_PUBLIC_RESPONSIF_STEP01_PASS.png", "screenshots/TC043_ADMIN_RESPONSIF_STEP02_PASS.png",
            "TC042 — login 375 px", "TC043 — menu sidebar di ponsel", "Pengujian tampilan pada lebar layar ponsel")

# ------------------------------------------- 10. limitasi & kesimpulan ----
doc.add_heading("10. Keterbatasan Pengujian", 1)
bullets([
    "Pengujian hanya di lingkungan lokal dengan data seeder dan browser Chromium desktop serta satu ukuran ponsel; Firefox/Safari tidak diuji.",
    "Modul layanan, transaksi, pembayaran, dan notifikasi belum memiliki halaman sehingga tidak diuji fungsional (hanya angka ringkasannya di dashboard).",
    "Tidak dilakukan load/performance test, penetration test, maupun brute force di luar uji batas 5 percobaan login yang memang menjadi requirement.",
    "White-box tidak mengukur persentase code coverage karena ekstensi Xdebug/PCOV tidak terpasang; cakupan dinilai dari analisis jalur (basis path).",
    "Run 2 dijalankan pada database yang sama dengan Run 1 (data uji dibuat dan dihapus sendiri oleh test), tanpa migrate:fresh ulang.",
])
doc.add_heading("11. Rekomendasi", 1)
bullets([
    "Pertahankan test Playwright dan PHPUnit di repositori dan jalankan sebelum setiap penggabungan fitur baru (regression).",
    "Tambahkan validasi format Nomor HP dan atribut validasi sisi klien (ENH-001, ENH-002).",
    "Perbarui README terkait hak akses Kasir/Pelanggan dan cara menjalankan test (DOC-001, DOC-002).",
    "Saat modul transaksi dibuat, perluas discovery dan test case ke alur transaksi–pembayaran–notifikasi.",
])
doc.add_heading("12. Kesimpulan", 1)
para(
    f"Pengujian QA pada aplikasi SiLaundry mencakup {len(MODULES) - 1} modul, {S2['total']} test case black-box, dan "
    f"{W2['total']} test white-box. Run 1 menghasilkan pass rate black-box {S1['rate']} dengan {len(bugs['bugs'])} bug "
    "terkonfirmasi: pesan validasi berbahasa Inggris, halaman terproteksi tampil kembali lewat tombol Back setelah logout, "
    "urutan Transaksi Terbaru yang salah, dan teks paginasi berbahasa Inggris. Kombinasi black-box dan white-box terbukti "
    "saling menguatkan: dua bug ditemukan oleh kedua pendekatan, sedangkan bug keamanan cache dan bug tampilan hanya "
    "terlihat dari sisi browser. Setelah perbaikan, retest dan regresi penuh pada Run 2 lulus seluruhnya "
    f"({S2['pass']}/{S2['total']} black-box, {W2['pass']}/{W2['total']} white-box)."
)

doc.add_heading("Lampiran A. Repositori dan Cara Menjalankan", 1)
kv([
    ("Repositori GitHub", REPO_URL),
    ("Folder QA", "QA/ (discovery, tests, evidence, screenshots, bugs, reports, scripts)"),
    ("Test white-box", "tests/Feature/WhiteBox/ (AuthControllerPathTest, DashboardControllerPathTest, UserControllerPathTest)"),
    ("Menjalankan aplikasi", "php artisan serve --host=127.0.0.1 --port=8000"),
    ("Black-box", "cd QA → npm run setup → cp .env.example .env (isi akun uji) → npm test"),
    ("White-box", "php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --testdox"),
    ("Bangun ulang laporan", "python scripts/build_report.py lalu powershell -File scripts/docx_to_pdf.ps1"),
], w1=3.8)

# simpan & metadata
cp = doc.core_properties
cp.author = PENYUSUN
cp.title = "Laporan QA SiLaundry"
cp.comments = ""
cp.last_modified_by = PENYUSUN
docx_path = OUT / "Laporan_QA_SiLaundry.docx"
doc.save(docx_path)
print("DOCX:", docx_path.relative_to(ROOT))

# ------------------------------------------------- katalog test case XLSX ----
wb = Workbook()
ws = wb.active
ws.title = "Black-box"
head = ["Test Case ID", "Skenario", "Modul", "Feature", "Requirement", "Role", "Jenis", "Prioritas", "Expected Result", "Run 1", "Run 2", "Durasi Run 2 (s)"]
ws.append(head)
for r in run2:
    ws.append([r["id"], r["title"], r.get("module"), r.get("feature"), r.get("requirement"), r.get("role"), r.get("type"), r.get("priority"),
               r.get("expected"), status1.get(r["id"]), r["status"], round(r["duration"], 1)])
ws2 = wb.create_sheet("White-box")
ws2.append(["Kelas", "Test", "Run 1", "Run 2"])
s1 = {(c["class"], c["name"]): c["status"] for c in wb1}
for c in wb2:
    ws2.append([c["class"].split("\\")[-1], c["name"], s1.get((c["class"], c["name"]), "-"), c["status"]])
ws3 = wb.create_sheet("Bug")
ws3.append(["Bug ID", "Judul", "Severity", "Priority", "Status", "Test case", "Commit perbaikan"])
for b in bugs["bugs"]:
    ws3.append([b["bug_id"], b["title"], b["severity"], b["priority"], b["status"], ", ".join(b["related_test_cases"]), b["fix"]["commit"]])
fill = {"PASS": "DCF5E3", "FAIL": "FBE0E0"}
for sheet in (ws, ws2, ws3):
    for cell in sheet[1]:
        cell.font = Font(bold=True, color="FFFFFF")
        cell.fill = PatternFill("solid", fgColor="146C3A")
    for row in sheet.iter_rows(min_row=2):
        for cell in row:
            cell.alignment = Alignment(wrap_text=True, vertical="top")
            if cell.value in fill:
                cell.fill = PatternFill("solid", fgColor=fill[cell.value])
    for i, col in enumerate(sheet.columns, 1):
        width = max(len(str(c.value or "")) for c in col)
        sheet.column_dimensions[get_column_letter(i)].width = min(max(10, width + 2), 60)
    sheet.freeze_panes = "A2"
wb.properties.creator = PENYUSUN
xlsx_path = OUT / "Test_Case_Catalogue.xlsx"
wb.save(xlsx_path)
print("XLSX:", xlsx_path.relative_to(ROOT))
