# Progress Redesign Mudalaya

## Tahap yang Telah Selesai
- [x] **Tahap 1: Fondasi Komponen Bersama** (token `paper-soft`, status-chip, level-chip, due-chip, progress-stack, choice-card-group, checkbox-pill, detail-list/detail-item, helpers format date/rupiah/relative time, pagination view `mudalaya`).
- [x] **Tahap 10: Daftar NCR (`modules/ncr/index.blade.php`)** (header policy-aware, segmented status tabs + counter agregat, pencarian server-side, panel filter lanjutan collapsible, tabel responsive kartu dengan chip status/level, empty state).
- [x] **Tahap 11: Detail NCR (`modules/ncr/show.blade.php`)** (header navigasi kembali + caption + h1 mono + aksi pill policy-aware, grid 1fr / 300px responsive, 5 kartu section temuan/akar masalah/CA/PA/NC Cost & riwayat dengan `<x-detail-list>`, sidebar Ringkasan NCR sticky dengan `<x-ncr-stepper>`).
- [x] **Tahap 12: Form Buat/Edit NCR (`modules/ncr/_form.blade.php`, `create.blade.php`, `edit.blade.php`)** (layout 2 kolom 230px/1fr, sticky nav "Isian NCR" dengan indikator status kelengkapan dan active highlight observer, 5 kartu section berlingkaran nomor 34px, `<x-choice-card-group>` untuk Level/Tipe/Status, `<x-checkbox-pill>` untuk related departments, toggle interaktif Project/Internal + autofill customer, prefix Rp pada NC Cost, ringkasan error validasi & field error styling, pencegahan double-submit).
- [x] **Bugfix & Fitur Form NCR: Toggle Choice Card & Clean Outline** (toggle deselect klik/Spasi pada kartu aktif, fallback handling ketika Tipe kosong, outline focus-visible keyboard tanpa double-ring klik mouse, sinkronisasi visual SSR + JS, hapus auto-default saat refresh/load awal sehingga semua pilihan kartu/indikator bawah mulai dalam keadaan abu-abu kosong).
- [x] **Dashboard NCR: Karakteristik Data & Analisis Akar Masalah (`modules/dashboard.blade.php`)** (redesain kartu Distribusi Tipe NCR dengan `<x-donut-chart>`, Distribusi Status Mutu dengan `<x-stacked-bar>`, Kategori Akar Masalah 4M dengan `<x-metric-bars>`, Temuan per Area Proses dengan `<x-rank-list>`, legend list koma desimal, serta penanganan kondisi kosong tahun 0 NCR).
- [x] **Daftar MDR Checklist (`modules/mdr-checklist-index.blade.php`)** (header brand policy-aware, grid 1fr/1.6fr 3 stat cards + grafik grouped bar bulanan, segmented status tabs dengan counter pencarian, input cari unit/project, tabel kartu responsif <820px berprogres report dan status chip, empty state versus hasil filter).
- [x] **Tahap MDR-1: Halaman New MDR (`modules/mdr-checklist-create.blade.php`) & Detail MDR (`modules/mdr-checklist-show.blade.php`)** (header brand caption + tombol kembali, kartu Project & Unit, kartu Status 16 Report 2-kolom dengan `<x-bulk-status-buttons>` & `<x-report-status-group>` berstatus Open/Close/NA via `has-[:checked]`, sticky summary bar `<x-mdr-summary-bar>` dengan live progress bar & chip status dinamis, pencegahan double-submit).

## Komponen, Service, & Helper Baru
- `<x-report-status-group>`: Radiogroup status 3 opsi (Open, Close, N/A) dengan radio asli 16px, border focus-visible 2px brand, styling terpilih khusus per status via `has-[:checked]`.
- `<x-bulk-status-buttons>`: Tombol penanda massal (Open, Close, N/A) dengan listener JS vanilla untuk mengatur seluruh status report sekaligus.
- `<x-mdr-summary-bar>`: Kartu ringkasan progres report ({selesai} dari {total} selesai) dengan bar horizontal pill dan chip status Open/Close dinamis.
- `$mdrTotals` & `$statusCounts` (`MdrChecklistController::index`): Agregasi tunggal total MDR (Total, Open, Close) dan penghitungan status adaptif per pencarian.
- `<x-donut-chart>`: SVG Donut chart proporsional (viewBox 0 0 120 120, r=44) mulai jam 12 dengan angka total & label di tengah serta cincin abu untuk total 0.
- `<x-stacked-bar>`: Bar horizontal pill 16px track #EEEBE1 dengan segmen proporsional gap 2px (minimal 3% jika >0).
- `<x-legend-list>`: Daftar legenda baris dengan titik bulat/persegi dan persentase berformat Indonesia koma desimal ("28 · 63,6%").
- `<x-metric-bars>`: Daftar bar metrik horizontal 10px pill track #EEEBE1 isi brand dengan label dan persentase 2 desimal ("20 kasus · 45,45%").
- `<x-rank-list>`: Daftar peringkat terurut menurun dengan badge lingkaran peringkat 24px, bar pill 8px relatif max 100%, dan expandable `<details>` untuk item >5.
- `format_percentage()` (`App\Support\Format::percentage`): Format angka persentase lokal Indonesia dengan koma desimal.
- `format_root_cause_category()` (`App\Support\Format::rootCauseCategoryLabel`): Pemetaan kode kategori 4M ke label deskriptif ("Man (Manusia)", dsb).
- `<x-choice-card-group>` & `<x-choice-card>`: Radio card kustom (min-height 48px, radius 12, radio 18px accent brand, terpilih: border 2px brand + bg #E9EFE5 via CSS `has-[:checked]`).
- `<x-checkbox-pill>`: Checkbox pill (radius 22, min-height 44px, padding 0 14px, terpilih: border 2px brand + bg #E9EFE5).
- `<x-ncr-stepper>`: Stepper alur status NCR vertikal (Open → CA Closed → Closed) dengan lingkaran status 28px dan presenter tanggal/status view-only.
- `<x-detail-list>` & `<x-detail-item>`: Komponen grid deskripsi 2 kolom dengan span penuh dan fallback otomatis "Belum diisi" jika nilai kosong.
- `<x-status-chip>`: Pill status dengan indikator bintik berwarna sesuai status (Open, CA Closed, Closed, dsb).
- `<x-level-chip>`: Pill level temuan berbintik (Major, Minor, Near Miss).
- `format_date()` (`App\Support\Format::date`): Format tanggal singkat berbahasa Indonesia ("24 Okt 2026") dengan timezone Asia/Jakarta.

## Hal yang Belum Selesai
- Halaman RFI & Dashboard QC Finding overview sesuai antrean tahap selanjutnya.
