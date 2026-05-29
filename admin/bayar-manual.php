<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_admin();

$pdo      = db();
$authUser = current_user();

/* ─── AJAX: cari anggota ─── */
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    $q = '%' . trim($_GET['q'] ?? '') . '%';
    $st = $pdo->prepare("SELECT id_anggota AS id, nama, no_tlp, nik FROM anggota WHERE (nama LIKE ? OR nik LIKE ?) AND status='Disetujui' ORDER BY nama LIMIT 10");
    $st->execute([$q, $q]);
    echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

/* ─── AJAX: pinjaman + angsuran anggota ─── */
if (isset($_GET['ajax_pinjaman'])) {
    header('Content-Type: application/json');
    $aid = (int)($_GET['anggota_id'] ?? 0);
    if (!$aid) { echo json_encode([]); exit; }
    $st = $pdo->prepare("
        SELECT p.id_pinjaman AS id, p.nama_pinjaman AS nomor,
               p.besar_pinjaman, p.tenor, p.bunga_persen, p.angsuran_per_bulan, p.status, p.tgl_pinjaman,
               (SELECT COUNT(*) FROM angsuran WHERE id_pinjaman=p.id_pinjaman AND status='Diterima') AS sudah_bayar,
               (SELECT COUNT(*) FROM angsuran WHERE id_pinjaman=p.id_pinjaman) AS total_angsuran,
               (SELECT COUNT(*) FROM angsuran WHERE id_pinjaman=p.id_pinjaman AND status!='Diterima') AS sisa_belum
        FROM pinjaman p
        WHERE p.id_anggota=? AND p.status IN ('Disetujui','Dicairkan')
        ORDER BY p.created_at DESC
    ");
    $st->execute([$aid]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $as = $pdo->prepare("
            SELECT a.id_angsuran AS id, a.angsuran_ke, a.besar_angsuran AS nominal, a.status,
                   da.tgl_jatuh_tempo AS jatuh_tempo
            FROM angsuran a
            LEFT JOIN detail_angsuran da ON da.id_angsuran=a.id_angsuran
            WHERE a.id_pinjaman=? AND a.status!='Diterima'
            ORDER BY a.angsuran_ke ASC LIMIT 36
        ");
        $as->execute([$row['id']]);
        $row['angsuran'] = $as->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode($rows);
    exit;
}

/* ─── POST: simpan ─── */
if (is_post() && ($_POST['action'] ?? '') === 'bayar') {
    $pid  = (int)($_POST['pinjaman_id'] ?? 0);
    $aid  = (int)($_POST['angsuran_id'] ?? 0);
    $nom  = parse_currency($_POST['nominal'] ?? 0);
    $tgl  = $_POST['tanggal_bayar'] ?? '';
    $mid  = (int)($_POST['anggota_id'] ?? 0);

    if ($pid && $aid && $nom > 0 && $tgl) {
        // Ambil info pinjaman & angsuran sebelum update untuk log & notifikasi
        $st_info = $pdo->prepare("
            SELECT p.nama_pinjaman, a.angsuran_ke 
            FROM angsuran a 
            JOIN pinjaman p ON a.id_pinjaman = p.id_pinjaman 
            WHERE a.id_angsuran = ? AND a.id_pinjaman = ?
        ");
        $st_info->execute([$aid, $pid]);
        $info = $st_info->fetch();
        $nomor_pinjaman = $info['nama_pinjaman'] ?? '';
        $ke = (int)($info['angsuran_ke'] ?? 0);

        // Update angsuran
        $pdo->prepare("UPDATE angsuran SET status='Diterima', besar_angsuran=?, tgl_pembayaran=? WHERE id_angsuran=? AND id_pinjaman=?")
            ->execute([$nom, $tgl, $aid, $pid]);
        
        // Cek pelunasan pinjaman
        $chk = $pdo->prepare("SELECT COUNT(*) AS c FROM angsuran WHERE id_pinjaman=? AND status!='Diterima'");
        $chk->execute([$pid]);
        if ((int)$chk->fetch()['c'] === 0) {
            $pdo->prepare("UPDATE pinjaman SET status='Lunas', tgl_pelunasan=CURDATE() WHERE id_pinjaman=?")
                ->execute([$pid]);
            
            create_notification(
                $mid,
                'Pinjaman Lunas!',
                'Selamat! Seluruh angsuran pinjaman ' . $nomor_pinjaman . ' telah terbayar lunas.'
            );
        } else {
            create_notification(
                $mid,
                'Pembayaran angsuran diterima (Manual)',
                'Pembayaran angsuran ke-' . $ke . ' untuk pinjaman ' . $nomor_pinjaman . ' sebesar ' . format_rupiah($nom) . ' telah berhasil dicatat oleh admin.'
            );
        }

        // Sync denda terlambat setelah pembayaran dicatat
        sync_late_fines($mid);

        log_activity((int)$authUser['id'], "Bayar manual angsuran ke-$ke pinjaman $nomor_pinjaman sebesar " . format_rupiah($nom));
        set_flash('success', 'Pembayaran berhasil disimpan.');
    } else {
        set_flash('danger', 'Data tidak lengkap atau nominal tidak valid.');
    }
    redirect('/admin/bayar-manual.php?anggota_id=' . $mid);
}

/* ─── Pre-load anggota ─── */
$anggotaId   = (int)($_GET['anggota_id'] ?? 0);
$anggotaInfo = null;
if ($anggotaId) {
    $s = $pdo->prepare("SELECT id_anggota AS id, nama, no_tlp, nik FROM anggota WHERE id_anggota=?");
    $s->execute([$anggotaId]);
    $anggotaInfo = $s->fetch();
}

$page_title = 'Input Bayar Manual';
$role       = 'admin';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<style>
/* ═══ FULL-WIDTH BAYAR MANUAL ═══ */

/* Page Header */
.bm-page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}
.bm-page-title { font-size: 1.45rem; font-weight: 800; color: var(--text-primary); margin: 0; }
.bm-page-sub   { font-size: .82rem; color: var(--text-muted); margin: .2rem 0 0; }

/* ── Search Bar Row (exactly like pinjaman search) ─── */
.bm-search-row {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: .85rem 1.1rem;
  display: flex;
  align-items: center;
  gap: .75rem;
  margin-bottom: 1.25rem;
  position: relative;
}
.bm-search-icon { color: var(--text-muted); font-size: 1rem; flex-shrink: 0; }
.bm-search-input {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  font-size: .9rem;
  color: var(--text-primary);
  font-family: 'Inter', sans-serif;
}
.bm-search-input::placeholder { color: var(--text-muted); }
.bm-search-clear {
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  color: var(--text-muted);
  font-size: .75rem;
  padding: .25rem .6rem;
  cursor: pointer;
  transition: var(--transition);
  white-space: nowrap;
  display: none;
}
.bm-search-clear:hover { background: var(--accent-red-light); color: var(--accent-red); border-color: var(--accent-red); }
.bm-search-clear.show { display: block; }

/* Dropdown */
.bm-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0; right: 0;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow-lg);
  z-index: 600;
  max-height: 300px;
  overflow-y: auto;
  display: none;
}
.bm-dropdown.open { display: block; animation: fadeSlide .15s ease; }
@keyframes fadeSlide {
  from { opacity:0; transform:translateY(-6px); }
  to   { opacity:1; transform:translateY(0); }
}
.bm-dd-item {
  display: flex; align-items: center; gap: .85rem;
  padding: .7rem 1rem;
  cursor: pointer;
  border-bottom: 1px solid var(--border-light);
  transition: background .15s;
}
.bm-dd-item:last-child { border-bottom: none; }
.bm-dd-item:hover { background: var(--primary-soft); }
.bm-dd-av {
  width: 36px; height: 36px;
  background: linear-gradient(135deg, var(--primary), var(--primary-light));
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-weight: 700; font-size: .85rem; flex-shrink: 0;
}
.bm-dd-name { font-size: .875rem; font-weight: 600; color: var(--text-primary); }
.bm-dd-sub  { font-size: .74rem; color: var(--text-muted); }
.bm-dd-empty { padding: 1.5rem; text-align: center; color: var(--text-muted); font-size: .85rem; }
.bm-dd-loading { padding: 1rem 1.5rem; color: var(--text-muted); font-size: .83rem; display:flex; align-items:center; gap:.5rem; }

/* ── Anggota Selected Bar ── */
.bm-member-bar {
  background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 65%, var(--primary-light) 100%);
  border-radius: var(--radius-lg);
  padding: 1rem 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
  overflow: hidden;
  position: relative;
}
.bm-member-bar::before {
  content:''; position:absolute; right:-20px; top:-20px;
  width:100px; height:100px; border-radius:50%;
  background:rgba(255,255,255,.05);
}
.bm-member-av {
  width: 44px; height: 44px;
  background: rgba(255,255,255,.18);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-weight: 800; font-size: 1.1rem;
  border: 2px solid rgba(255,255,255,.3);
  flex-shrink: 0;
}
.bm-member-name { font-size: 1rem; font-weight: 700; color: #fff; }
.bm-member-meta { font-size: .76rem; color: rgba(255,255,255,.7); display:flex; gap:.9rem; flex-wrap:wrap; margin-top:.15rem; }
.bm-member-meta span { display:flex; align-items:center; gap:.3rem; }
.bm-btn-ganti {
  margin-left: auto;
  background: rgba(255,255,255,.15);
  border: 1.5px solid rgba(255,255,255,.3);
  color: #fff; border-radius: var(--radius);
  padding: .4rem .9rem; font-size: .8rem; font-weight: 600;
  cursor: pointer; transition: var(--transition); white-space: nowrap; z-index:1;
  text-decoration: none; display:inline-flex; align-items:center; gap:.35rem;
}
.bm-btn-ganti:hover { background: rgba(255,255,255,.28); color: #fff; }

/* ── Section Label ── */
.bm-section-label {
  font-size: .72rem; font-weight: 700; color: var(--text-muted);
  text-transform: uppercase; letter-spacing: .07em;
  margin-bottom: .85rem;
  display: flex; align-items: center; gap: .4rem;
}

/* ── Pinjaman Table (full-width, like pinjaman page) ── */
.bm-panel {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  margin-bottom: 1.25rem;
}
.bm-panel-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border-light);
  flex-wrap: wrap; gap: .5rem;
}
.bm-panel-title { font-size: .95rem; font-weight: 700; color: var(--text-primary); }
.bm-table { width: 100%; border-collapse: collapse; }
.bm-table thead th {
  background: var(--bg);
  color: var(--text-muted); font-size: .72rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .06em;
  padding: .75rem 1.25rem;
  border-bottom: 1px solid var(--border);
  white-space: nowrap; text-align: left;
}
.bm-table tbody td {
  padding: .9rem 1.25rem;
  border-bottom: 1px solid var(--border-light);
  font-size: .875rem; color: var(--text-primary);
  vertical-align: middle;
}
.bm-table tbody tr:last-child td { border-bottom: none; }
.bm-table tbody tr:hover td { background: var(--primary-soft); cursor: pointer; }
.bm-table tbody tr.selected td { background: #dbeafe; }

.bm-badge {
  display: inline-flex; align-items: center; gap: .28rem;
  font-size: .72rem; font-weight: 700;
  padding: .25rem .65rem; border-radius: var(--radius-full);
  white-space: nowrap;
}
.bm-badge::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
.bm-badge.green { background: var(--accent-green-light); color: var(--accent-green); }
.bm-badge.amber { background: var(--accent-amber-light); color: var(--accent-amber); }
.bm-badge.blue  { background: var(--accent-blue-light);  color: var(--accent-blue); }
.bm-badge.gray  { background: var(--bg); color: var(--text-muted); border:1px solid var(--border); }
.bm-badge.red   { background: var(--accent-red-light); color: var(--accent-red); }

.bm-progress { background: var(--bg); border-radius: 99px; height: 5px; width: 100px; overflow: hidden; display:inline-block; vertical-align:middle; }
.bm-progress-fill { height: 100%; background: linear-gradient(90deg, var(--accent-green), #22c55e); border-radius: 99px; }

.bm-btn-pilih {
  background: linear-gradient(135deg, var(--primary), var(--primary-light));
  color: #fff; border: none; border-radius: var(--radius-sm);
  padding: .38rem .9rem; font-size: .8rem; font-weight: 600;
  cursor: pointer; transition: var(--transition);
  display:inline-flex; align-items:center; gap:.3rem;
}
.bm-btn-pilih:hover { transform:translateY(-1px); box-shadow: var(--shadow-sm); }

/* ── Angsuran Table (expanded inline below the loan row) ── */
.angsuran-expand-row { display: none; }
.angsuran-expand-row.open { display: table-row-group; }
.angsuran-expand-cell {
  padding: 0 !important;
  border-bottom: 2px solid var(--primary) !important;
}
.angsuran-expand-inner {
  background: #f8faff;
  padding: 1.25rem 1.5rem;
}
.angsuran-expand-title {
  font-size: .82rem; font-weight: 700; color: var(--primary);
  margin-bottom: 1rem; display:flex; align-items:center; gap:.4rem;
}
.ang-table { width: 100%; border-collapse: collapse; }
.ang-table thead th {
  font-size: .7rem; font-weight: 700; color: var(--text-muted);
  text-transform: uppercase; letter-spacing: .06em;
  padding: .5rem .85rem;
  border-bottom: 1px solid var(--border);
  background: rgba(30,58,95,.04);
  text-align: left;
}
.ang-table tbody td {
  padding: .65rem .85rem;
  border-bottom: 1px solid var(--border-light);
  font-size: .84rem; vertical-align: middle;
}
.ang-table tbody tr:last-child td { border-bottom: none; }
.ang-table tbody tr:hover td { background: rgba(30,58,95,.04); cursor: pointer; }
.ang-table tbody tr.ang-selected td { background: #dbeafe; }
.ang-radio { width:16px; height:16px; accent-color: var(--primary); cursor:pointer; }
.ang-ke-badge {
  background: var(--primary-soft); color: var(--primary);
  font-size:.7rem; font-weight:700;
  padding:.18rem .5rem; border-radius:var(--radius-full);
}
.overdue-cell { color: var(--accent-red); font-weight:600; }

/* ── Payment Form ── */
.bm-pay-panel {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  margin-top: 1.5rem;
}
.bm-pay-header {
  background: linear-gradient(135deg, var(--primary-dark), var(--primary));
  padding: 1rem 1.5rem;
  display: flex; align-items: center; justify-content: space-between;
}
.bm-pay-header-title { color: #fff; font-size: .95rem; font-weight: 700; }
.bm-pay-header-sub { color: rgba(255,255,255,.65); font-size: .76rem; margin-top:.15rem; }
.bm-pay-body { padding: 1.5rem; }
.bm-summary-strip {
  display: flex; gap: 1.5rem; flex-wrap: wrap;
  background: var(--primary-soft);
  border: 1px solid rgba(30,58,95,.1);
  border-radius: var(--radius);
  padding: .85rem 1.25rem;
  margin-bottom: 1.5rem;
}
.bm-sum-item { flex:1; min-width:110px; }
.bm-sum-label { font-size: .7rem; color: var(--primary); font-weight: 600; margin-bottom:.15rem; }
.bm-sum-val   { font-size: .9rem; font-weight: 700; color: var(--primary-dark); }
.bm-form-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.25rem; }
@media (max-width:768px) { .bm-form-grid { grid-template-columns: 1fr; } }

/* Empty/loading states */
.bm-empty { text-align:center; padding: 2.5rem 1.5rem; }
.bm-empty i { font-size:2.5rem; color: var(--border); display:block; margin-bottom:.75rem; }
.bm-empty p { color: var(--text-muted); font-size:.875rem; margin:0; }
.bm-loading { display:flex; align-items:center; justify-content:center; gap:.6rem; padding:2rem; color:var(--text-muted); font-size:.85rem; }

/* Spinner */
.spin-ring { width:18px; height:18px; border:2.5px solid var(--border); border-top-color:var(--primary); border-radius:50%; animation:spin .6s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
</style>

<!-- ═══ PAGE HEADER ═══ -->
<div class="bm-page-header">
  <div>
    <h1 class="bm-page-title"><i class="bi bi-cash-coin me-2" style="color:var(--primary)"></i>Input Pembayaran Manual</h1>
    <p class="bm-page-sub">Cari anggota, pilih pinjaman, lalu pilih angsuran dan simpan pembayaran.</p>
  </div>
  <a href="<?= base_url('/admin/angsuran.php') ?>" class="btn btn-light btn-sm d-flex align-items-center gap-1">
    <i class="bi bi-arrow-left"></i> Kembali ke Angsuran
  </a>
</div>

<!-- ═══ SEARCH BAR (full-width, same as Pinjaman page) ═══ -->
<?php if (!$anggotaInfo): ?>
<div class="bm-search-row" id="searchRow">
  <i class="bi bi-search bm-search-icon"></i>
  <input type="text" id="searchInput" class="bm-search-input"
         placeholder="Cari nama anggota atau NIK..." autofocus>
  <button type="button" class="bm-search-clear" id="clearBtn" onclick="clearSearch()">
    <i class="bi bi-x me-1"></i>Hapus
  </button>
  <div class="bm-dropdown" id="searchDropdown"></div>
</div>

<div class="bm-empty">
  <i class="bi bi-person-search"></i>
  <p>Ketik nama atau NIK anggota di kolom pencarian di atas untuk melihat data pinjaman.</p>
</div>

<?php else: ?>

<!-- ═══ MEMBER BAR ═══ -->
<div class="bm-member-bar">
  <div class="bm-member-av"><?= strtoupper(substr($anggotaInfo['nama'], 0, 1)) ?></div>
  <div>
    <div class="bm-member-name"><?= e($anggotaInfo['nama']) ?></div>
    <div class="bm-member-meta">
      <span><i class="bi bi-credit-card-2-front"></i><?= e($anggotaInfo['nik']) ?></span>
      <?php if ($anggotaInfo['no_tlp']): ?>
        <span><i class="bi bi-telephone"></i><?= e($anggotaInfo['no_tlp']) ?></span>
      <?php endif; ?>
    </div>
  </div>
  <a href="<?= base_url('/admin/bayar-manual.php') ?>" class="bm-btn-ganti">
    <i class="bi bi-arrow-repeat"></i>Ganti Anggota
  </a>
</div>

<!-- ═══ PINJAMAN TABLE ═══ -->
<div class="bm-section-label">
  <i class="bi bi-cash-stack"></i> Daftar Pinjaman Aktif
</div>

<div class="bm-panel" id="pinjamanPanel">
  <div class="bm-loading" id="loadingState">
    <div class="spin-ring"></div> Memuat data pinjaman...
  </div>

  <table class="bm-table" id="pinjamanTable" style="display:none">
    <thead>
      <tr>
        <th>No Pinjaman</th>
        <th>Tanggal Cair</th>
        <th>Nominal</th>
        <th>Tenor</th>
        <th>Angsuran/Bln</th>
        <th>Progress</th>
        <th>Status</th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody id="pinjamanBody"></tbody>
  </table>
  <div id="emptyPinjaman" style="display:none">
    <div class="bm-empty">
      <i class="bi bi-inbox"></i>
      <p>Anggota ini tidak memiliki pinjaman aktif.</p>
    </div>
  </div>
</div>

<!-- ═══ PAYMENT FORM (shown when angsuran selected) ═══ -->
<div id="paySection" style="display:none">
  <div class="bm-pay-panel">
    <div class="bm-pay-header">
      <div>
        <div class="bm-pay-header-title"><i class="bi bi-wallet2 me-2"></i>Form Pembayaran Manual</div>
        <div class="bm-pay-header-sub" id="payHeaderSub">—</div>
      </div>
      <button type="button" onclick="closePayForm()"
        style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:var(--radius-sm);padding:.3rem .7rem;cursor:pointer;">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="bm-pay-body">
      <div class="bm-summary-strip" id="paySummary"></div>
      <form method="post" id="payForm" onsubmit="return doValidate()">
        <input type="hidden" name="action" value="bayar">
        <input type="hidden" name="anggota_id" value="<?= e($anggotaId) ?>">
        <input type="hidden" name="pinjaman_id" id="fPinjamanId">
        <input type="hidden" name="angsuran_id" id="fAngsuranId">
        <div class="bm-form-grid">
          <div>
            <label class="form-label">Nominal Pembayaran <span class="required">*</span></label>
            <div class="input-group">
              <span class="input-group-text">Rp</span>
              <input type="text" name="nominal" id="fNominal" data-type="currency"
                     class="form-control" required placeholder="0"
                     autocomplete="off"
                     style="height:44px;font-size:.95rem;font-weight:600;">
            </div>
            <div class="form-text mt-1" style="font-size:.74rem;">Otomatis terisi sesuai angsuran dipilih.</div>
          </div>
          <div>
            <label class="form-label">Tanggal Bayar <span class="required">*</span></label>
            <input type="date" name="tanggal_bayar" id="fTanggal"
                   class="form-control" value="<?= date('Y-m-d') ?>" required
                   style="height:44px;">
          </div>
          <div class="d-flex align-items-end gap-2">
            <button type="button" onclick="closePayForm()" class="btn btn-light flex-fill">Batal</button>
            <button type="submit" class="btn btn-primary flex-fill"
              style="background:linear-gradient(135deg,var(--primary),var(--primary-light));border:none;height:44px;">
              <i class="bi bi-check2-circle me-1"></i>Simpan
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<?php endif; ?>

<script>
(function(){

/* ═══ SEARCH ═══ */
const inp = document.getElementById('searchInput');
const dd  = document.getElementById('searchDropdown');
const clr = document.getElementById('clearBtn');
let timer;

if (inp) {
  inp.addEventListener('input', function(){
    clearTimeout(timer);
    const q = this.value.trim();
    clr?.classList.toggle('show', q.length > 0);
    if (q.length < 2) { dd?.classList.remove('open'); return; }
    dd.innerHTML = '<div class="bm-dd-loading"><div class="spin-ring"></div> Mencari...</div>';
    dd.classList.add('open');
    timer = setTimeout(()=>{
      fetch('<?= base_url('/admin/bayar-manual.php') ?>?ajax_search=1&q='+encodeURIComponent(q))
        .then(r=>r.json()).then(renderDD).catch(()=>{
          dd.innerHTML='<div class="bm-dd-empty">Terjadi kesalahan.</div>';
        });
    }, 300);
  });
  document.addEventListener('click', e=>{
    if (!inp.contains(e.target) && !dd?.contains(e.target)) dd?.classList.remove('open');
  });
}

function renderDD(data){
  if (!data.length){
    dd.innerHTML='<div class="bm-dd-empty"><i class="bi bi-person-x" style="font-size:1.4rem;display:block;margin-bottom:.4rem;color:var(--border)"></i>Tidak ditemukan / belum diverifikasi.</div>';
    return;
  }
  dd.innerHTML = data.map(a=>`
    <div class="bm-dd-item" onclick="goAnggota(${a.id})">
      <div class="bm-dd-av">${xss(a.nama).charAt(0).toUpperCase()}</div>
      <div>
        <div class="bm-dd-name">${xss(a.nama)}</div>
        <div class="bm-dd-sub">NIK: ${xss(a.nik)}${a.no_tlp?' · '+xss(a.no_tlp):''}</div>
      </div>
    </div>`).join('');
}
window.goAnggota = id => window.location.href='<?= base_url('/admin/bayar-manual.php') ?>?anggota_id='+id;
window.clearSearch = ()=>{ if(inp){inp.value=''; clr?.classList.remove('show'); dd?.classList.remove('open'); inp.focus(); } };

/* ═══ LOAD PINJAMAN ═══ */
const AID = <?= $anggotaId ?: 'null' ?>;
let _pinjList = [];

if (AID) {
  fetch('<?= base_url('/admin/bayar-manual.php') ?>?ajax_pinjaman=1&anggota_id='+AID)
    .then(r=>r.json()).then(renderPinjaman)
    .catch(()=>{
      document.getElementById('loadingState').innerHTML = '<div class="bm-empty" style="width:100%"><i class="bi bi-exclamation-circle"></i><p>Gagal memuat data.</p></div>';
    });
}

function fRp(n){ return 'Rp '+parseFloat(n).toLocaleString('id-ID',{maximumFractionDigits:0}); }
function xss(s){ const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

function renderPinjaman(list){
  document.getElementById('loadingState').style.display='none';
  if (!list||!list.length){
    document.getElementById('emptyPinjaman').style.display='block';
    return;
  }
  _pinjList = list;
  document.getElementById('pinjamanTable').style.display='table';
  const tbody = document.getElementById('pinjamanBody');
  tbody.innerHTML = list.map((p,i)=>{
    const pct = p.total_angsuran>0 ? Math.round(p.sudah_bayar/p.total_angsuran*100) : 0;
    const stClass = p.status.toLowerCase()==='dicairkan'?'green':'amber';
    const tgl = p.tgl_pinjaman ? new Date(p.tgl_pinjaman).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}) : '-';
    return `
      <tr onclick="togglePinjaman(${i})" id="prow-${i}">
        <td><code style="font-size:.8rem;background:var(--bg);padding:.15rem .4rem;border-radius:4px;font-weight:600">${xss(p.nomor)}</code></td>
        <td>${tgl}</td>
        <td style="font-weight:700">${fRp(p.besar_pinjaman)}</td>
        <td>${xss(p.tenor)} bulan</td>
        <td>${fRp(p.angsuran_per_bulan)}</td>
        <td>
          <div style="display:flex;align-items:center;gap:.5rem">
            <div class="bm-progress"><div class="bm-progress-fill" style="width:${pct}%"></div></div>
            <span style="font-size:.75rem;color:var(--text-muted)">${pct}%</span>
          </div>
          <div style="font-size:.72rem;color:var(--text-muted);margin-top:.2rem">${p.sudah_bayar}/${p.total_angsuran} lunas</div>
        </td>
        <td><span class="bm-badge ${stClass}">${xss(p.status)}</span></td>
        <td>
          <button class="bm-btn-pilih" onclick="event.stopPropagation();togglePinjaman(${i})">
            <i class="bi bi-chevron-down" id="chevron-${i}"></i> Pilih
          </button>
        </td>
      </tr>
      <tbody class="angsuran-expand-row" id="expand-${i}">
        <tr>
          <td colspan="8" class="angsuran-expand-cell">
            <div class="angsuran-expand-inner">
              <div class="angsuran-expand-title">
                <i class="bi bi-calendar-check"></i>
                Daftar Angsuran Belum Dibayar — ${xss(p.nomor)}
                <span style="font-size:.75rem;font-weight:500;color:var(--text-muted);margin-left:.5rem">${p.sisa_belum} angsuran tersisa</span>
              </div>
              ${renderAngsuranTable(p, i)}
            </div>
          </td>
        </tr>
      </tbody>`;
  }).join('');
}

function renderAngsuranTable(p, pi){
  if (!p.angsuran||!p.angsuran.length){
    return '<div style="text-align:center;padding:1.5rem;color:var(--text-muted);font-size:.85rem"><i class="bi bi-check-circle-fill" style="color:var(--accent-green);margin-right:.4rem"></i>Semua angsuran sudah lunas.</div>';
  }
  const today = new Date(); today.setHours(0,0,0,0);
  const rows = p.angsuran.map(a=>{
    const jt = a.jatuh_tempo ? new Date(a.jatuh_tempo) : null;
    const late = jt && jt < today;
    const jtStr = jt ? jt.toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}) : '-';
    let sc='gray', sl='Belum Dibayar';
    if(a.status==='Menunggu konfirmasi'){ sc='amber'; sl='Menunggu'; }
    if(a.status==='Ditolak'){ sc='red'; sl='Ditolak'; }
    return `<tr onclick="pickAngsuran(${pi},${a.id},${a.nominal},'${xss(p.nomor)}')" id="arow-${a.id}">
      <td><input type="radio" name="rad_ang" class="ang-radio" value="${a.id}" onclick="event.stopPropagation();pickAngsuran(${pi},${a.id},${a.nominal},'${xss(p.nomor)}')"></td>
      <td><span class="ang-ke-badge">Ke-${a.angsuran_ke}</span></td>
      <td style="font-weight:700">${fRp(a.nominal)}</td>
      <td class="${late?'overdue-cell':''}">${jtStr}${late?' <i class="bi bi-exclamation-triangle-fill" title="Terlambat"></i>':''}</td>
      <td><span class="bm-badge ${sc}">${sl}</span></td>
    </tr>`;
  }).join('');
  return `<table class="ang-table">
    <thead><tr><th>Pilih</th><th>Ke-</th><th>Nominal</th><th>Jatuh Tempo</th><th>Status</th></tr></thead>
    <tbody>${rows}</tbody>
  </table>`;
}

let _openIdx = null;
window.togglePinjaman = function(i){
  const ex = document.getElementById('expand-'+i);
  const ch = document.getElementById('chevron-'+i);
  if (!ex) return;
  const isOpen = ex.classList.contains('open');
  /* tutup semua */
  document.querySelectorAll('.angsuran-expand-row').forEach(el=>el.classList.remove('open'));
  document.querySelectorAll('[id^="chevron-"]').forEach(el=>{ el.className='bi bi-chevron-down'; });
  document.querySelectorAll('[id^="prow-"]').forEach(el=>el.classList.remove('selected'));
  if (!isOpen){
    ex.classList.add('open');
    ch.className = 'bi bi-chevron-up';
    document.getElementById('prow-'+i).classList.add('selected');
    _openIdx = i;
    /* scroll ke tabel angsuran */
    setTimeout(()=>ex.scrollIntoView({behavior:'smooth',block:'nearest'}),100);
  } else {
    _openIdx = null;
    closePayForm();
  }
};

window.pickAngsuran = function(pi, angId, nominal, nomPinjaman){
  const p = _pinjList[pi];
  /* radio */
  document.querySelectorAll('.ang-radio').forEach(r=>r.checked=false);
  const r = document.querySelector(`.ang-radio[value="${angId}"]`);
  if(r) r.checked=true;
  /* highlight row */
  document.querySelectorAll('[id^="arow-"]').forEach(tr=>tr.classList.remove('ang-selected'));
  document.getElementById('arow-'+angId)?.classList.add('ang-selected');

  /* set form values */
  document.getElementById('fPinjamanId').value = p.id;
  document.getElementById('fAngsuranId').value = angId;
  /* format nominal with main.js currency formatter compatible value */
  document.getElementById('fNominal').value = parseFloat(nominal).toLocaleString('id-ID',{maximumFractionDigits:0});

  /* summary */
  document.getElementById('paySummary').innerHTML = `
    <div class="bm-sum-item"><div class="bm-sum-label"><i class="bi bi-tag me-1"></i>No Pinjaman</div><div class="bm-sum-val">${xss(nomPinjaman)}</div></div>
    <div class="bm-sum-item"><div class="bm-sum-label"><i class="bi bi-cash me-1"></i>Pokok Pinjaman</div><div class="bm-sum-val">${fRp(p.besar_pinjaman)}</div></div>
    <div class="bm-sum-item"><div class="bm-sum-label"><i class="bi bi-calendar3 me-1"></i>Angsuran/Bulan</div><div class="bm-sum-val">${fRp(p.angsuran_per_bulan)}</div></div>
    <div class="bm-sum-item"><div class="bm-sum-label"><i class="bi bi-check-circle me-1"></i>Nominal Dipilih</div><div class="bm-sum-val" style="color:var(--accent-green)">${fRp(nominal)}</div></div>
  `;
  document.getElementById('payHeaderSub').textContent = 'Pinjaman: '+nomPinjaman;

  /* show pay form */
  const sec = document.getElementById('paySection');
  sec.style.display='block';
  sec.style.animation='none'; sec.offsetHeight; /* reflow */
  sec.style.animation='slideIn .25s ease';
  sec.scrollIntoView({behavior:'smooth', block:'nearest'});
};

window.closePayForm = function(){
  document.getElementById('paySection').style.display='none';
  document.querySelectorAll('.ang-radio').forEach(r=>r.checked=false);
  document.querySelectorAll('[id^="arow-"]').forEach(tr=>tr.classList.remove('ang-selected'));
};

window.doValidate = function(){
  const angId = document.getElementById('fAngsuranId').value;
  if (!angId){ alert('Pilih angsuran yang akan dibayar.'); return false; }
  return true;
};

})();
</script>

<style>
@keyframes slideIn {
  from { opacity:0; transform:translateY(10px); }
  to   { opacity:1; transform:translateY(0); }
}
</style>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
