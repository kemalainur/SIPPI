<?php
require_once '../config/database.php';
session_start();
check_login();
check_permission('ppi.manage');

$title = "Periode & Kegiatan";
include '../layout/header.php';
include '../layout/sidebar.php';

$message = '';

$active_p = get_active_kepengurusan();
if (!$active_p) {
    die("Error: Tidak ada Tahun Kepengurusan yang aktif.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['open_periode'])) {
        $bulan = (int)$_POST['bulan'];
        $tahun = (int)$_POST['tahun'];
        $jenis_periode = $_POST['jenis_periode'] ?? 'Bulanan';
        $mode_penilaian = $_POST['mode_penilaian'] ?? 'PPI';
        $nama_sesi = trim($_POST['nama_sesi'] ?? '');
        $bulan_disiplin = isset($_POST['bulan_disiplin']) && is_array($_POST['bulan_disiplin']) ? $_POST['bulan_disiplin'] : [];
        try {
            $stmt = $pdo->prepare("INSERT INTO tabel_periode (bulan, tahun, status, kepengurusan_id, jenis_periode, mode_penilaian, nama_sesi) VALUES (?, ?, 'aktif', ?, ?, ?, ?)");
            $stmt->execute([$bulan, $tahun, $active_p['id_kepengurusan'], $jenis_periode, $mode_penilaian, $nama_sesi]);
            $new_periode_id = $pdo->lastInsertId();
            if ($jenis_periode === 'Triwulan' || $jenis_periode === 'Triwulanan') {
                if (!empty($bulan_disiplin)) {
                    $stmtMap = $pdo->prepare("INSERT INTO tabel_periode_disiplin_bulan (periode_id, bulan_sumber, tahun_sumber) VALUES (?, ?, ?)");
                    foreach ($bulan_disiplin as $b_src) {
                        $stmtMap->execute([$new_periode_id, (int)$b_src, $tahun]);
                    }
                }
            }
            $message = "Bulan Penilaian Berhasil Dibuka!";
        } catch (PDOException $e) {
            $message = "Gagal: Bulan ini mungkin sudah ada.";
        }
    } elseif (isset($_POST['close_periode'])) {
        $id = $_POST['id_periode'];
        $stmt = $pdo->prepare("UPDATE tabel_periode SET status = 'tutup' WHERE id_periode = ?");
        $stmt->execute([$id]);
        $message = "Bulan Penilaian Berhasil Ditutup!";
    } elseif (isset($_POST['edit_bulan'])) {
        $id = (int)$_POST['id_periode'];
        $bulan = (int)$_POST['bulan'];
        $tahun = (int)$_POST['tahun'];
        $jenis_periode = $_POST['jenis_periode'] ?? 'Bulanan';
        $mode_penilaian = $_POST['mode_penilaian'] ?? 'PPI';
        $nama_sesi = trim($_POST['nama_sesi'] ?? '');
        $bulan_disiplin = isset($_POST['bulan_disiplin']) && is_array($_POST['bulan_disiplin']) ? $_POST['bulan_disiplin'] : [];
        $stmt = $pdo->prepare("UPDATE tabel_periode SET bulan = ?, tahun = ?, jenis_periode = ?, mode_penilaian = ?, nama_sesi = ? WHERE id_periode = ?");
        $stmt->execute([$bulan, $tahun, $jenis_periode, $mode_penilaian, $nama_sesi, $id]);
        $pdo->prepare("DELETE FROM tabel_periode_disiplin_bulan WHERE periode_id = ?")->execute([$id]);
        if ($jenis_periode === 'Triwulan' || $jenis_periode === 'Triwulanan') {
            if (!empty($bulan_disiplin)) {
                $stmtMap = $pdo->prepare("INSERT INTO tabel_periode_disiplin_bulan (periode_id, bulan_sumber, tahun_sumber) VALUES (?, ?, ?)");
                foreach ($bulan_disiplin as $b_src) {
                    $stmtMap->execute([$id, (int)$b_src, $tahun]);
                }
            }
        }
        $message = "Bulan Penilaian Berhasil Diperbarui!";
    } elseif (isset($_POST['delete_bulan'])) {
        $id = $_POST['id_periode'];
        $stmt = $pdo->prepare("DELETE FROM tabel_periode WHERE id_periode = ?");
        $stmt->execute([$id]);
        $message = "Bulan Penilaian Berhasil Dihapus!";
    } elseif (isset($_POST['add_kegiatan'])) {
        $nama = $_POST['nama_kegiatan'];
        $bulan = $_POST['bulan'];
        $tahun = $_POST['tahun'];
        $stmt = $pdo->prepare("INSERT INTO tabel_kegiatan (nama_kegiatan, bulan, tahun, kepengurusan_id) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$nama, $bulan, $tahun, $active_p['id_kepengurusan']])) {
            $message = "Kegiatan berhasil ditambahkan!";
        }
    } elseif (isset($_POST['edit_kegiatan'])) {
        $id = $_POST['id_kegiatan'];
        $nama = $_POST['nama_kegiatan'];
        $stmt = $pdo->prepare("UPDATE tabel_kegiatan SET nama_kegiatan = ? WHERE id_kegiatan = ?");
        $stmt->execute([$nama, $id]);
        $message = "Kegiatan berhasil diperbarui!";
    } elseif (isset($_POST['delete_kegiatan'])) {
        $id = $_POST['id_kegiatan'];
        $stmt = $pdo->prepare("DELETE FROM tabel_kegiatan WHERE id_kegiatan = ?");
        $stmt->execute([$id]);
        $message = "Kegiatan berhasil dihapus!";
    }
}

$stmtAktif = $pdo->prepare("SELECT * FROM tabel_periode WHERE status = 'aktif' AND kepengurusan_id = ? LIMIT 1");
$stmtAktif->execute([$active_p['id_kepengurusan']]);
$active = $stmtAktif->fetch();

$stmtHistory = $pdo->prepare("SELECT * FROM tabel_periode WHERE kepengurusan_id = ? ORDER BY tahun DESC, bulan DESC");
$stmtHistory->execute([$active_p['id_kepengurusan']]);
$history = $stmtHistory->fetchAll();

$period_discipline_map = [];
$stmtAllMaps = $pdo->query("SELECT periode_id, bulan_sumber, tahun_sumber FROM tabel_periode_disiplin_bulan ORDER BY tahun_sumber ASC, bulan_sumber ASC");
if ($stmtAllMaps) {
    $all_maps = $stmtAllMaps->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all_maps as $m) {
        $period_discipline_map[$m['periode_id']][] = (int)$m['bulan_sumber'];
    }
}

$kegiatans = [];
if ($active) {
    $stmtKeg = $pdo->prepare("SELECT * FROM tabel_kegiatan WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ?");
    $stmtKeg->execute([$active['bulan'], $active['tahun'], $active_p['id_kepengurusan']]);
    $kegiatans = $stmtKeg->fetchAll();
}

$indonesian_months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
?>

<div id="content" class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-brand-red mb-1">Manajemen Bulan Penilaian</h4>
            <div class="small text-muted">
                Tahun Kepengurusan Aktif: <span class="badge bg-primary px-2 rounded-pill"><?= $active_p['nama_periode'] ?></span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary d-flex align-items-center shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#openModal">
                <i class="fas fa-plus-circle me-1"></i> Buka Bulan Baru
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show d-flex align-items-center rounded-3 p-3 mb-4" role="alert">
            <i class="fas fa-check-circle fa-lg me-3"></i>
            <div><?= $message ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h6 class="mb-0 fw-800"><i class="fas fa-bullseye text-primary me-2"></i>Status Bulan Aktif</h6>
                </div>
                <div class="card-body p-4">
                    <?php if ($active): 
                        $active_mapped = $period_discipline_map[$active['id_periode']] ?? [];
                    ?>
                        <div class="bg-gradient-emerald p-4 rounded-4 text-white text-center shadow-sm mb-4">
                            <?php if (!empty($active['nama_sesi'])): ?>
                                <div class="small fw-800 text-white-50 text-uppercase ls-1 mb-1"><?= htmlspecialchars($active['nama_sesi']) ?></div>
                            <?php endif; ?>
                            <h2 class="fw-800 mb-0"><?= $indonesian_months[(int)$active['bulan']] ?></h2>
                            <p class="mb-0 opacity-75 fw-600 ls-1"><?= $active['tahun'] ?></p>
                            <div class="mt-3 d-flex justify-content-center gap-1 flex-wrap">
                                <span class="badge bg-white bg-opacity-20 px-3 py-1 rounded-pill small fw-bold">SEDANG BERJALAN</span>
                                <span class="badge bg-white text-dark px-2 py-1 rounded-pill small fw-bold"><?= htmlspecialchars($active['jenis_periode'] ?? 'Bulanan') ?></span>
                                <span class="badge bg-warning text-dark px-2 py-1 rounded-pill small fw-bold"><?= htmlspecialchars($active['mode_penilaian'] ?? 'PPI') ?></span>
                            </div>
                            <?php if (!empty($active_mapped)): ?>
                                <div class="mt-3 pt-2 border-top border-white border-opacity-25 very-small">
                                    <i class="fas fa-calendar-alt me-1"></i> Disiplin: 
                                    <strong><?= implode(', ', array_map(function($m) use ($indonesian_months) { return substr($indonesian_months[$m], 0, 3); }, $active_mapped)) ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-primary w-100 rounded-pill fw-800 shadow-sm py-2" data-bs-toggle="modal" data-bs-target="#editModal<?= $active['id_periode'] ?>">
                                <i class="fas fa-sliders-h me-1"></i> Ubah Jenis & Mode
                            </button>
                            <form method="POST" onsubmit="return confirm('Tutup bulan penilaian ini?')">
                                <input type="hidden" name="id_periode" value="<?= $active['id_periode'] ?>">
                                <button type="submit" name="close_periode" class="btn btn-dark w-100 rounded-pill fw-800 shadow-sm py-2">
                                    <i class="fas fa-lock me-1"></i> Tutup Penilaian
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 border-2 border-dashed rounded-4 bg-light">
                            <div class="stats-icon bg-white shadow-sm mx-auto mb-3" style="width: 50px; height: 50px; border-radius: 15px;">
                                <i class="fas fa-calendar-times text-muted"></i>
                            </div>
                            <p class="text-muted small fw-bold mb-0">Tidak ada bulan yang aktif</p>
                            <button class="btn btn-link text-primary text-decoration-none small fw-800 mt-2" data-bs-toggle="modal" data-bs-target="#openModal">
                                Buka sekarang <i class="fas fa-arrow-right ms-1"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-800"><i class="fas fa-clipboard-list text-primary me-2"></i>Kegiatan di Bulan Terpilih</h6>
                    <?php if($active): ?>
                        <button class="btn btn-primary btn-sm px-3 rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#addKegiatanModal">
                            <i class="fas fa-plus me-1"></i> Tambah Kegiatan
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-slate-50 text-muted small text-uppercase">
                                <tr>
                                    <th width="60" class="ps-4">No</th>
                                    <th>Nama Kegiatan</th>
                                    <th class="text-center pe-4" style="width: 200px;">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($kegiatans): $n=1; foreach($kegiatans as $k): ?>
                                    <tr class="modern-row">
                                        <td class="ps-4 fw-600 text-muted"><?= $n++ ?></td>
                                        <td class="fw-800 text-dark"><?= $k['nama_kegiatan'] ?></td>
                                        <td class="text-center pe-4">
                                            <div class="d-flex justify-content-center gap-1">
                                                <a href="kehadiran_input.php?id=<?= $k['id_kegiatan'] ?>" class="btn btn-primary-soft btn-sm rounded-pill px-3 fw-800" title="Input Kehadiran">
                                                    <i class="fas fa-user-check me-1"></i> Input
                                                </a>
                                                <button class="btn btn-icon btn-light-soft text-primary" data-bs-toggle="modal" data-bs-target="#editKegiatanModal<?= $k['id_kegiatan'] ?>" title="Ubah Nama"><i class="fas fa-edit small"></i></button>
                                                <button class="btn btn-icon btn-light-soft text-danger" data-bs-toggle="modal" data-bs-target="#deleteKegiatanModal<?= $k['id_kegiatan'] ?>" title="Hapus Kegiatan"><i class="fas fa-trash-alt small"></i></button>
                                            </div>
                                        </td>
                                    </tr>                                <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-5">
                                            <div class="opacity-50 text-center w-100 py-3">
                                                <i class="fas fa-tasks fa-2x mb-2"></i>
                                                <p class="small fw-bold mb-0"><?= $active ? 'Belum ada kegiatan di bulan ini' : 'Buka bulan penilaian untuk melihat kegiatan' ?></p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h6 class="mb-0 fw-800"><i class="fas fa-history text-primary me-2"></i>Riwayat Bulan Penilaian</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-slate-50 text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4 py-3">Bulan & Tahun</th>
                                    <th class="text-center">Jenis & Mode</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center pe-4">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($history): ?>
                                    <?php foreach($history as $h): 
                                        $h_mapped = $period_discipline_map[$h['id_periode']] ?? [];
                                    ?>
                                        <tr class="modern-row">
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center py-1">
                                                    <div class="avatar-sm me-3 <?= $h['status'] == 'aktif' ? 'bg-brand-green-soft text-brand-green' : 'bg-slate-100 text-muted' ?> rounded-3 d-flex align-items-center justify-content-center fw-800">
                                                        <i class="fas fa-calendar-check"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-800 text-dark"><?= $indonesian_months[(int)$h['bulan']] ?> <?= $h['tahun'] ?></div>
                                                        <?php if (!empty($h['nama_sesi'])): ?>
                                                            <div class="text-muted small ls-1 fw-bold"><?= htmlspecialchars($h['nama_sesi']) ?></div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($h_mapped)): ?>
                                                            <div class="badge bg-light text-primary border px-2 py-0-5 very-small mt-1">
                                                                <i class="fas fa-link me-1"></i>Disiplin: <?= implode(', ', array_map(function($m) use ($indonesian_months) { return substr($indonesian_months[$m], 0, 3); }, $h_mapped)) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-light text-dark px-2 py-1 rounded-pill small fw-bold me-1"><?= htmlspecialchars($h['jenis_periode'] ?? 'Bulanan') ?></span>
                                                <span class="badge bg-warning text-dark px-2 py-1 rounded-pill small fw-bold"><?= htmlspecialchars($h['mode_penilaian'] ?? 'PPI') ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= $h['status'] == 'aktif' ? 'bg-brand-green-soft text-brand-green' : 'bg-slate-100 text-muted' ?> px-3 py-1 rounded-pill small fw-bold">
                                                    <?= strtoupper($h['status']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center pe-4">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <button class="btn btn-icon btn-light-soft text-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $h['id_periode'] ?>"><i class="fas fa-edit"></i></button>
                                                    <button class="btn btn-icon btn-light-soft text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $h['id_periode'] ?>"><i class="fas fa-trash-alt"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted small">Belum ada riwayat.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="openModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="modal-title fw-800">Buka Bulan Penilaian Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Bulan Periode</label>
                            <select name="bulan" class="form-select border-0 bg-light" required>
                                <?php foreach($indonesian_months as $num => $nama): ?>
                                    <option value="<?= $num ?>" <?= date('n') == $num ? 'selected' : '' ?>><?= $nama ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Tahun</label>
                            <input type="number" name="tahun" value="<?= date('Y') ?>" class="form-control border-0 bg-light" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Jenis Periode</label>
                            <select name="jenis_periode" id="open_jenis_periode" class="form-select border-0 bg-light" onchange="toggleDisiplinSection(this, 'open_mode_penilaian', 'open_disiplin_wrapper')">
                                <option value="Bulanan">Bulanan</option>
                                <option value="Triwulan">Triwulan (Peer Assessment)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Mode Penilaian</label>
                            <select name="mode_penilaian" id="open_mode_penilaian" class="form-select border-0 bg-light">
                                <option value="PPI">Penilaian PPI (TIM PPI)</option>
                                <option value="Peer Assessment">Peer Assessment (Pengurus)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Nama Sesi / Keterangan (Opsional)</label>
                            <input type="text" name="nama_sesi" class="form-control border-0 bg-light" placeholder="Contoh: Triwulan I (Q1 - Evaluasi)">
                        </div>
                        <div class="col-12" id="open_disiplin_wrapper" style="display: none;">
                            <div class="p-3 bg-light rounded-3 border">
                                <label class="form-label small fw-800 text-dark text-uppercase mb-1">
                                    <i class="fas fa-tasks text-primary me-1"></i> Sumber Data Disiplin (Absensi & Kas)
                                </label>
                                <p class="text-muted very-small mb-2">Pilih bulan-bulan yang datanya akan dirata-ratakan untuk nilai disiplin periode triwulan ini:</p>
                                <div class="row g-2">
                                    <?php foreach($indonesian_months as $m_num => $m_name): ?>
                                        <div class="col-4 col-sm-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="bulan_disiplin[]" value="<?= $m_num ?>" id="open_disiplin_<?= $m_num ?>">
                                                <label class="form-check-label small fw-600" for="open_disiplin_<?= $m_num ?>">
                                                    <?= substr($m_name, 0, 3) ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" name="open_periode" class="btn btn-primary w-100 fw-800 py-2 rounded-3 shadow-sm">Aktifkan Periode Ini</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleDisiplinSection(selectElem, targetModeId, targetDisiplinWrapperId) {
    var modeSelect = document.getElementById(targetModeId);
    var disiplinWrapper = document.getElementById(targetDisiplinWrapperId);
    if (selectElem.value === 'Bulanan') {
        if (modeSelect) modeSelect.value = 'PPI';
        if (disiplinWrapper) disiplinWrapper.style.display = 'none';
    } else if (selectElem.value === 'Triwulan' || selectElem.value === 'Triwulanan') {
        if (modeSelect) modeSelect.value = 'Peer Assessment';
        if (disiplinWrapper) disiplinWrapper.style.display = 'block';
    }
}
</script>

<?php if($active): ?>
<div class="modal fade" id="addKegiatanModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST">
                <input type="hidden" name="bulan" value="<?= $active['bulan'] ?>">
                <input type="hidden" name="tahun" value="<?= $active['tahun'] ?>">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="modal-title fw-800">Tambah Kegiatan Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-0">
                        <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Nama Kegiatan</label>
                        <input type="text" name="nama_kegiatan" class="form-control form-control-lg border-0 bg-light" placeholder="Contoh: Rapat Koordinasi Rutin" required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" name="add_kegiatan" class="btn btn-primary w-100 fw-800 py-2 rounded-3 shadow-sm">Simpan Kegiatan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php foreach($history as $h): 
    $mapped_for_h = $period_discipline_map[$h['id_periode']] ?? [];
    $is_triwulan = ($h['jenis_periode'] === 'Triwulan' || $h['jenis_periode'] === 'Triwulanan');
?>
<div class="modal fade" id="editModal<?= $h['id_periode'] ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h6 class="fw-800">Ubah Periode Penilaian</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id_periode" value="<?= $h['id_periode'] ?>">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label small fw-800 text-muted text-uppercase">Bulan Periode</label>
                            <select name="bulan" class="form-select border-0 bg-light">
                                <?php foreach($indonesian_months as $m => $name): ?>
                                    <option value="<?= $m ?>" <?= $m == $h['bulan'] ? 'selected' : '' ?>><?= $name ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-800 text-muted text-uppercase">Tahun</label>
                            <input type="number" name="tahun" value="<?= $h['tahun'] ?>" class="form-control border-0 bg-light">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-800 text-muted text-uppercase">Jenis Periode</label>
                            <select name="jenis_periode" class="form-select border-0 bg-light" onchange="toggleDisiplinSection(this, 'edit_mode_<?= $h['id_periode'] ?>', 'edit_disiplin_wrapper_<?= $h['id_periode'] ?>')">
                                <option value="Bulanan" <?= ($h['jenis_periode'] ?? 'Bulanan') == 'Bulanan' ? 'selected' : '' ?>>Bulanan</option>
                                <option value="Triwulan" <?= $is_triwulan ? 'selected' : '' ?>>Triwulan (Peer Assessment)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-800 text-muted text-uppercase">Mode Penilaian</label>
                            <select name="mode_penilaian" id="edit_mode_<?= $h['id_periode'] ?>" class="form-select border-0 bg-light">
                                <option value="PPI" <?= ($h['mode_penilaian'] ?? 'PPI') == 'PPI' ? 'selected' : '' ?>>Penilaian PPI (TIM PPI)</option>
                                <option value="Peer Assessment" <?= ($h['mode_penilaian'] ?? '') == 'Peer Assessment' ? 'selected' : '' ?>>Peer Assessment (Pengurus)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-800 text-muted text-uppercase">Nama Sesi / Keterangan</label>
                            <input type="text" name="nama_sesi" value="<?= htmlspecialchars($h['nama_sesi'] ?? '') ?>" class="form-control border-0 bg-light" placeholder="Contoh: Triwulan I (Q1)">
                        </div>
                        <div class="col-12" id="edit_disiplin_wrapper_<?= $h['id_periode'] ?>" style="<?= $is_triwulan ? 'display: block;' : 'display: none;' ?>">
                            <div class="p-3 bg-light rounded-3 border">
                                <label class="form-label small fw-800 text-dark text-uppercase mb-1">
                                    <i class="fas fa-tasks text-primary me-1"></i> Sumber Data Disiplin (Absensi & Kas)
                                </label>
                                <p class="text-muted very-small mb-2">Pilih bulan-bulan yang datanya akan dirata-ratakan untuk nilai disiplin periode triwulan ini:</p>
                                <div class="row g-2">
                                    <?php foreach($indonesian_months as $m_num => $m_name): ?>
                                        <div class="col-4 col-sm-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="bulan_disiplin[]" value="<?= $m_num ?>" id="edit_disiplin_<?= $h['id_periode'] ?>_<?= $m_num ?>" <?= in_array($m_num, $mapped_for_h) ? 'checked' : '' ?>>
                                                <label class="form-check-label small fw-600" for="edit_disiplin_<?= $h['id_periode'] ?>_<?= $m_num ?>">
                                                    <?= substr($m_name, 0, 3) ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" name="edit_bulan" class="btn btn-primary w-100 fw-800 shadow-sm py-2">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal<?= $h['id_periode'] ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4 p-4 text-center">
            <div class="stats-icon bg-danger-soft text-danger mx-auto mb-3" style="width: 60px; height: 60px; border-radius: 20px;">
                <i class="fas fa-exclamation-triangle fa-2x"></i>
            </div>
            <h5 class="fw-800 text-dark">Hapus Data?</h5>
            <p class="text-muted small">Tindakan ini permanen dan menghapus seluruh penilaian terkait.</p>
            <form method="POST">
                <input type="hidden" name="id_periode" value="<?= $h['id_periode'] ?>">
                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-light w-100 fw-600" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="delete_bulan" class="btn btn-danger w-100 fw-800">Ya, Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php if ($kegiatans): foreach($kegiatans as $k): ?>
<div class="modal fade" id="editKegiatanModal<?= $k['id_kegiatan'] ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h6 class="fw-800">Ubah Kegiatan</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id_kegiatan" value="<?= $k['id_kegiatan'] ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-800 text-muted text-uppercase">Nama Kegiatan</label>
                        <input type="text" name="nama_kegiatan" value="<?= $k['nama_kegiatan'] ?>" class="form-control border-0 bg-light fw-600" required>
                    </div>
                    <button type="submit" name="edit_kegiatan" class="btn btn-primary w-100 fw-800 shadow-sm">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteKegiatanModal<?= $k['id_kegiatan'] ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4 p-4 text-center">
            <div class="stats-icon bg-danger-soft text-danger mx-auto mb-3" style="width: 60px; height: 60px; border-radius: 20px;">
                <i class="fas fa-exclamation-triangle fa-2x"></i>
            </div>
            <h5 class="fw-800 text-dark">Hapus Kegiatan?</h5>
            <p class="text-muted small">Tindakan ini tidak dapat dibatalkan.</p>
            <form method="POST">
                <input type="hidden" name="id_kegiatan" value="<?= $k['id_kegiatan'] ?>">
                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-light w-100 fw-600" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="delete_kegiatan" class="btn btn-danger w-100 fw-800">Ya, Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; endif; ?>

<style>
.bg-gradient-emerald { background: linear-gradient(135deg, #059669 0%, #10B981 100%); }
.bg-brand-green-soft { background-color: #FEF2F2; color: #DC2626; }
.text-brand-green { color: #DC2626; }
.btn-light-soft { background-color: #f1f5f9; border: none; }
.btn-icon { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
.avatar-sm { width: 34px; height: 34px; font-size: 0.9rem; }
.stats-icon { display: flex; align-items: center; justify-content: center; }
.ls-1 { letter-spacing: 0.5px; }
.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.modern-row:hover { background-color: #f9fafb; }
.rounded-4 { border-radius: 1.25rem !important; }
</style>

<?php include '../layout/footer.php'; ?>


