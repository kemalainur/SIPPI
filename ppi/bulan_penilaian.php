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
        $raw_jenis = $_POST['jenis_periode'] ?? 'Bulanan';
        $is_triwulan = ($raw_jenis === 'Triwulan' || $raw_jenis === 'Triwulanan');
        $jenis_periode = $is_triwulan ? 'Triwulanan' : 'Bulanan';
        $nama_sesi = null;

        if ($is_triwulan) {
            $nama_sesi = trim($_POST['nama_sesi'] ?? '');
            $bulan = (int)($_POST['bulan'] ?? date('n'));
            $mode_penilaian = 'Peer Assessment';
        } else {
            $bulan = (int)$_POST['bulan'];
            $mode_penilaian = 'PPI';
        }
        $tahun = (int)$_POST['tahun'];
        $bulan_disiplin = isset($_POST['bulan_disiplin']) && is_array($_POST['bulan_disiplin']) ? $_POST['bulan_disiplin'] : [];
        try {
            $stmt = $pdo->prepare("INSERT INTO tabel_periode (bulan, tahun, status, kepengurusan_id, jenis_periode, mode_penilaian, nama_sesi) VALUES (?, ?, 'aktif', ?, ?, ?, ?)");
            $stmt->execute([$bulan, $tahun, $active_p['id_kepengurusan'], $jenis_periode, $mode_penilaian, $nama_sesi]);
            $new_periode_id = $pdo->lastInsertId();
            if ($is_triwulan) {
                if (!empty($bulan_disiplin)) {
                    $stmtMap = $pdo->prepare("INSERT INTO tabel_periode_disiplin_bulan (periode_id, bulan_sumber, tahun_sumber) VALUES (?, ?, ?)");
                    foreach ($bulan_disiplin as $b_src) {
                        $stmtMap->execute([$new_periode_id, (int)$b_src, $tahun]);
                    }
                }
            }
            $message = "Periode Penilaian Berhasil Dibuka!";
        } catch (PDOException $e) {
            $message = "Gagal: Periode ini mungkin sudah ada.";
        }
    } elseif (isset($_POST['close_periode'])) {
        $id = $_POST['id_periode'];
        $stmt = $pdo->prepare("UPDATE tabel_periode SET status = 'tutup' WHERE id_periode = ?");
        $stmt->execute([$id]);
        $message = "Periode Penilaian Berhasil Ditutup!";
    } elseif (isset($_POST['edit_bulan'])) {
        $id = (int)$_POST['id_periode'];
        $raw_jenis = $_POST['jenis_periode'] ?? 'Bulanan';
        $is_triwulan = ($raw_jenis === 'Triwulan' || $raw_jenis === 'Triwulanan');
        $jenis_periode = $is_triwulan ? 'Triwulanan' : 'Bulanan';
        $nama_sesi = null;

        if ($is_triwulan) {
            $nama_sesi = trim($_POST['nama_sesi'] ?? '');
            $bulan = (int)($_POST['bulan'] ?? date('n'));
            $mode_penilaian = 'Peer Assessment';
        } else {
            $bulan = (int)$_POST['bulan'];
            $mode_penilaian = 'PPI';
        }
        $tahun = (int)$_POST['tahun'];
        $bulan_disiplin = isset($_POST['bulan_disiplin']) && is_array($_POST['bulan_disiplin']) ? $_POST['bulan_disiplin'] : [];
        $stmt = $pdo->prepare("UPDATE tabel_periode SET bulan = ?, tahun = ?, jenis_periode = ?, mode_penilaian = ?, nama_sesi = ? WHERE id_periode = ?");
        $stmt->execute([$bulan, $tahun, $jenis_periode, $mode_penilaian, $nama_sesi, $id]);
        $pdo->prepare("DELETE FROM tabel_periode_disiplin_bulan WHERE periode_id = ?")->execute([$id]);
        if ($is_triwulan) {
            if (!empty($bulan_disiplin)) {
                $stmtMap = $pdo->prepare("INSERT INTO tabel_periode_disiplin_bulan (periode_id, bulan_sumber, tahun_sumber) VALUES (?, ?, ?)");
                foreach ($bulan_disiplin as $b_src) {
                    $stmtMap->execute([$id, (int)$b_src, $tahun]);
                }
            }
        }
        $message = "Periode Penilaian Berhasil Diperbarui!";
    } elseif (isset($_POST['delete_bulan'])) {
        $id = $_POST['id_periode'];
        $stmt = $pdo->prepare("DELETE FROM tabel_periode WHERE id_periode = ?");
        $stmt->execute([$id]);
        $message = "Periode Penilaian Berhasil Dihapus!";
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
    } elseif (isset($_POST['delete_bulan'])) {
        $id = $_POST['id_periode'];
        $stmt = $pdo->prepare("DELETE FROM tabel_periode WHERE id_periode = ?");
        $stmt->execute([$id]);
        $message = "Periode Penilaian Berhasil Dihapus!";
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

// Ambil HANYA bulan penilaian bulanan yang SUDAH memiliki data absensi kegiatan dan kas
$stmtAvailable = $pdo->prepare("
    SELECT DISTINCT p.bulan, p.tahun 
    FROM tabel_periode p
    WHERE p.kepengurusan_id = ? 
      AND p.jenis_periode = 'Bulanan'
      AND EXISTS (
          SELECT 1 FROM tabel_kegiatan k 
          JOIN tabel_kehadiran h ON k.id_kegiatan = h.kegiatan_id 
          WHERE k.bulan = p.bulan AND k.tahun = p.tahun AND k.kepengurusan_id = p.kepengurusan_id
      )
      AND EXISTS (
          SELECT 1 FROM tabel_kas_pengurus kas 
          WHERE kas.bulan = p.bulan AND kas.tahun = p.tahun
      )
    ORDER BY p.tahun ASC, p.bulan ASC
");
$stmtAvailable->execute([$active_p['id_kepengurusan']]);
$available_months = $stmtAvailable->fetchAll(PDO::FETCH_ASSOC);

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
                    <h6 class="mb-0 fw-800"><i class="fas fa-bullseye text-primary me-2"></i>Status Periode Aktif</h6>
                </div>
                <div class="card-body p-4">
                    <?php if ($active): 
                        $active_mapped = $period_discipline_map[$active['id_periode']] ?? [];
                        $is_active_triwulan = ($active['jenis_periode'] === 'Triwulan' || $active['jenis_periode'] === 'Triwulanan' || $active['mode_penilaian'] === 'Peer Assessment');
                    ?>
                        <div class="bg-gradient-emerald p-4 rounded-4 text-white text-center shadow-sm mb-4">
                            <h2 class="fw-800 mb-1"><?= format_nama_periode($active) ?></h2>
                            <p class="mb-0 opacity-75 fw-600 ls-1">Tahun <?= $active['tahun'] ?></p>
                            <div class="mt-3 d-flex justify-content-center gap-1 flex-wrap">
                                <span class="badge bg-white bg-opacity-20 px-3 py-1 rounded-pill small fw-bold">SEDANG BERJALAN</span>
                                <span class="badge <?= $is_active_triwulan ? 'bg-info text-dark' : 'bg-white text-dark' ?> px-2 py-1 rounded-pill small fw-bold"><?= htmlspecialchars($active['jenis_periode'] ?? 'Bulanan') ?></span>
                                <span class="badge bg-warning text-dark px-2 py-1 rounded-pill small fw-bold"><?= htmlspecialchars($active['mode_penilaian'] ?? 'PPI') ?></span>
                            </div>
                            <?php if (!empty($active_mapped)): ?>
                                <div class="mt-3 pt-2 border-top border-white border-opacity-25 very-small">
                                    <i class="fas fa-calendar-alt me-1"></i> Disiplin dari Bulan: 
                                    <strong><?= implode(', ', array_map(function($m) use ($indonesian_months) { return substr($indonesian_months[$m], 0, 3); }, $active_mapped)) ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-primary w-100 rounded-pill fw-800 shadow-sm py-2" data-bs-toggle="modal" data-bs-target="#editModal<?= $active['id_periode'] ?>">
                                <i class="fas fa-sliders-h me-1"></i> Ubah Periode & Mode
                            </button>
                            <form method="POST" onsubmit="return confirm('Tutup periode penilaian ini?')">
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
                            <p class="text-muted small fw-bold mb-0">Tidak ada periode yang aktif</p>
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
                    <h6 class="mb-0 fw-800"><i class="fas fa-clipboard-list text-primary me-2"></i>Kegiatan di Periode Terpilih</h6>
                    <?php if($active && ($active['jenis_periode'] ?? 'Bulanan') === 'Bulanan'): ?>
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
                                <?php if ($active && ($active['jenis_periode'] ?? 'Bulanan') !== 'Bulanan'): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted small">
                                            <i class="fas fa-info-circle text-primary me-1"></i> Pada Periode Triwulan (Peer Assessment), kehadiran kegiatan & disiplin diakumulasikan otomatis dari bulan-bulan yang diceklis.
                                        </td>
                                    </tr>
                                <?php elseif ($kegiatans): $n=1; foreach($kegiatans as $k): ?>
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
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-5">
                                            <div class="opacity-50 text-center w-100 py-3">
                                                <i class="fas fa-tasks fa-2x mb-2"></i>
                                                <p class="small fw-bold mb-0"><?= $active ? 'Belum ada kegiatan di bulan ini' : 'Buka periode penilaian untuk melihat kegiatan' ?></p>
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
                    <h6 class="mb-0 fw-800"><i class="fas fa-history text-primary me-2"></i>Riwayat Periode Penilaian</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-slate-50 text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4 py-3">Nama Periode</th>
                                    <th class="text-center">Jenis & Mode</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center pe-4">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($history): ?>
                                    <?php foreach($history as $h): 
                                        $h_mapped = $period_discipline_map[$h['id_periode']] ?? [];
                                        $is_h_triwulan = ($h['jenis_periode'] === 'Triwulan' || $h['jenis_periode'] === 'Triwulanan' || $h['mode_penilaian'] === 'Peer Assessment');
                                    ?>
                                        <tr class="modern-row">
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center py-1">
                                                    <div class="avatar-sm me-3 <?= $h['status'] == 'aktif' ? 'bg-brand-green-soft text-brand-green' : 'bg-slate-100 text-muted' ?> rounded-3 d-flex align-items-center justify-content-center fw-800">
                                                        <i class="<?= $is_h_triwulan ? 'fas fa-layer-group' : 'fas fa-calendar-check' ?>"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-800 text-dark"><?= format_nama_periode($h) ?></div>
                                                        <?php if (!empty($h_mapped)): ?>
                                                            <div class="badge bg-light text-primary border px-2 py-0-5 very-small mt-1">
                                                                <i class="fas fa-link me-1"></i>Disiplin: <?= implode(', ', array_map(function($m) use ($indonesian_months) { return substr($indonesian_months[$m], 0, 3); }, $h_mapped)) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= $is_h_triwulan ? 'bg-info text-dark' : 'bg-light text-dark' ?> px-2 py-1 rounded-pill small fw-bold me-1"><?= htmlspecialchars($h['jenis_periode'] ?? 'Bulanan') ?></span>
                                                <span class="badge bg-warning text-dark px-2 py-1 rounded-pill small fw-bold"><?= htmlspecialchars($h['mode_penilaian'] ?? 'PPI') ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= $h['status'] == 'aktif' ? 'bg-brand-green-soft text-brand-green' : 'bg-slate-100 text-muted' ?> px-3 py-1 rounded-pill small fw-bold">
                                                    <?= strtoupper($h['status']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center pe-4">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <button class="btn btn-icon btn-light-soft text-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $h['id_periode'] ?>" title="Ubah Periode"><i class="fas fa-edit"></i></button>
                                                    <button class="btn btn-icon btn-light-soft text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $h['id_periode'] ?>" title="Hapus Periode"><i class="fas fa-trash-alt"></i></button>
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
                    <h5 class="modal-title fw-800">Buka Periode Penilaian Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Jenis Periode</label>
                            <select name="jenis_periode" id="open_jenis_periode" class="form-select border-0 bg-light fw-bold" onchange="togglePeriodeType(this, 'open')">
                                <option value="Bulanan">Penilaian Bulanan (PPI)</option>
                                <option value="Triwulanan">Penilaian Triwulan (Peer Assessment)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Tahun</label>
                            <input type="number" name="tahun" value="<?= date('Y') ?>" class="form-control border-0 bg-light fw-bold" required>
                        </div>

                        <!-- Selector untuk Bulanan -->
                        <div class="col-12" id="open_bulan_wrapper">
                            <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Bulan Penilaian</label>
                            <select name="bulan" id="open_bulan_select" class="form-select border-0 bg-light fw-bold">
                                <?php foreach($indonesian_months as $num => $nama): ?>
                                    <option value="<?= $num ?>" <?= date('n') == $num ? 'selected' : '' ?>><?= $nama ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Selector & Nama Kustom untuk Triwulan -->
                        <div class="col-12" id="open_triwulan_wrapper" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Nama Periode Triwulan (Bebas / Kustom)</label>
                                <input type="text" name="nama_sesi" id="open_nama_sesi" class="form-control border-0 bg-light fw-bold" placeholder="Contoh: Triwulan 3, Evaluasi Q3, dll." value="Triwulan 3">
                                <small class="text-muted very-small">Nama ini yang akan ditampilkan di seluruh sistem untuk evaluasi triwulan ini.</small>
                            </div>
                        </div>

                        <!-- Info Mode Penilaian -->
                        <div class="col-12">
                            <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Mode Penilaian</label>
                            <input type="text" id="open_mode_display" class="form-control border-0 bg-light fw-bold" value="Penilaian PPI (TIM PPI)" readonly>
                            <input type="hidden" name="mode_penilaian" id="open_mode_val" value="PPI">
                        </div>

                        <!-- Wrapper Disiplin untuk Triwulan (Bulan yang Sudah Memiliki Data) -->
                        <div class="col-12" id="open_disiplin_wrapper" style="display: none;">
                            <div class="p-3 bg-light rounded-3 border">
                                <label class="form-label small fw-800 text-dark text-uppercase mb-1">
                                    <i class="fas fa-tasks text-primary me-1"></i> Sumber Data Disiplin (Absensi & Kas)
                                </label>
                                <p class="text-muted very-small mb-2">Pilih bulan yang sudah ada data nilainya untuk dirata-ratakan pada disiplin periode ini:</p>
                                <div class="row g-2">
                                    <?php if (!empty($available_months)): ?>
                                        <?php foreach($available_months as $em): 
                                            $m_val = (int)$em['bulan'];
                                            $y_val = (int)$em['tahun'];
                                            $m_label = $indonesian_months[$m_val] . ' ' . $y_val;
                                        ?>
                                            <div class="col-6 col-sm-4">
                                                <div class="form-check p-2 border rounded bg-white">
                                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="bulan_disiplin[]" value="<?= $m_val ?>" id="open_disiplin_<?= $m_val ?>">
                                                    <label class="form-check-label small fw-bold text-dark cursor-pointer" for="open_disiplin_<?= $m_val ?>">
                                                        <?= $m_label ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="col-12 text-muted small p-2">
                                            <i class="fas fa-info-circle me-1 text-muted"></i> Belum ada bulan dengan riwayat data penilaian / kegiatan pada tahun kepengurusan ini.
                                        </div>
                                    <?php endif; ?>
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
function togglePeriodeType(selectElem, prefix) {
    var val = selectElem.value;
    var bulanWrapper = document.getElementById(prefix + '_bulan_wrapper');
    var triwulanWrapper = document.getElementById(prefix + '_triwulan_wrapper');
    var disiplinWrapper = document.getElementById(prefix + '_disiplin_wrapper');
    var modeDisplay = document.getElementById(prefix + '_mode_display');
    var modeVal = document.getElementById(prefix + '_mode_val');

    if (val === 'Triwulan' || val === 'Triwulanan') {
        if (bulanWrapper) bulanWrapper.style.display = 'none';
        if (triwulanWrapper) triwulanWrapper.style.display = 'block';
        if (disiplinWrapper) disiplinWrapper.style.display = 'block';
        if (modeDisplay) modeDisplay.value = 'Peer Assessment (Pengurus)';
        if (modeVal) modeVal.value = 'Peer Assessment';
    } else {
        if (bulanWrapper) bulanWrapper.style.display = 'block';
        if (triwulanWrapper) triwulanWrapper.style.display = 'none';
        if (disiplinWrapper) disiplinWrapper.style.display = 'none';
        if (modeDisplay) modeDisplay.value = 'Penilaian PPI (TIM PPI)';
        if (modeVal) modeVal.value = 'PPI';
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
    $is_triwulan = ($h['jenis_periode'] === 'Triwulan' || $h['jenis_periode'] === 'Triwulanan' || $h['mode_penilaian'] === 'Peer Assessment');
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
                        <div class="col-md-6">
                            <label class="form-label small fw-800 text-muted text-uppercase">Jenis Periode</label>
                            <select name="jenis_periode" id="edit_jenis_<?= $h['id_periode'] ?>" class="form-select border-0 bg-light fw-bold" onchange="togglePeriodeType(this, 'edit_<?= $h['id_periode'] ?>')">
                                <option value="Bulanan" <?= !$is_triwulan ? 'selected' : '' ?>>Penilaian Bulanan (PPI)</option>
                                <option value="Triwulanan" <?= $is_triwulan ? 'selected' : '' ?>>Penilaian Triwulan (Peer Assessment)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-800 text-muted text-uppercase">Tahun</label>
                            <input type="number" name="tahun" value="<?= $h['tahun'] ?>" class="form-control border-0 bg-light fw-bold">
                        </div>

                        <!-- Bulan Selector -->
                        <div class="col-12" id="edit_<?= $h['id_periode'] ?>_bulan_wrapper" style="<?= $is_triwulan ? 'display: none;' : 'display: block;' ?>">
                            <label class="form-label small fw-800 text-muted text-uppercase">Bulan Penilaian</label>
                            <select name="bulan" class="form-select border-0 bg-light fw-bold">
                                <?php foreach($indonesian_months as $m => $name): ?>
                                    <option value="<?= $m ?>" <?= $m == $h['bulan'] ? 'selected' : '' ?>><?= $name ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Triwulan Custom Name -->
                        <div class="col-12" id="edit_<?= $h['id_periode'] ?>_triwulan_wrapper" style="<?= $is_triwulan ? 'display: block;' : 'display: none;' ?>">
                            <div class="mb-0">
                                <label class="form-label small fw-800 text-muted text-uppercase">Nama Periode Triwulan (Bebas / Kustom)</label>
                                <input type="text" name="nama_sesi" value="<?= htmlspecialchars($h['nama_sesi'] ?? ($is_triwulan ? format_nama_periode($h) : '')) ?>" class="form-control border-0 bg-light fw-bold" placeholder="Contoh: Triwulan 3, Evaluasi Q3, dll.">
                                <small class="text-muted very-small">Nama ini yang akan ditampilkan di seluruh sistem untuk evaluasi triwulan ini.</small>
                            </div>
                        </div>

                        <!-- Info Mode Penilaian -->
                        <div class="col-12">
                            <label class="form-label small fw-800 text-muted text-uppercase">Mode Penilaian</label>
                            <input type="text" id="edit_<?= $h['id_periode'] ?>_mode_display" class="form-control border-0 bg-light fw-bold" value="<?= $is_triwulan ? 'Peer Assessment (Pengurus)' : 'Penilaian PPI (TIM PPI)' ?>" readonly>
                            <input type="hidden" name="mode_penilaian" id="edit_<?= $h['id_periode'] ?>_mode_val" value="<?= $is_triwulan ? 'Peer Assessment' : 'PPI' ?>">
                        </div>

                        <!-- Disiplin Wrapper (Bebas Pilih Bulan) -->
                        <!-- Disiplin Wrapper (Bulan yang Sudah Memiliki Data) -->
                        <div class="col-12" id="edit_<?= $h['id_periode'] ?>_disiplin_wrapper" style="<?= $is_triwulan ? 'display: block;' : 'display: none;' ?>">
                            <div class="p-3 bg-light rounded-3 border">
                                <label class="form-label small fw-800 text-dark text-uppercase mb-1">
                                    <i class="fas fa-tasks text-primary me-1"></i> Sumber Data Disiplin (Absensi & Kas)
                                </label>
                                <p class="text-muted very-small mb-2">Pilih bulan yang sudah ada data nilainya untuk dirata-ratakan pada disiplin periode ini:</p>
                                <div class="row g-2">
                                    <?php if (!empty($available_months)): ?>
                                        <?php foreach($available_months as $em): 
                                            $m_val = (int)$em['bulan'];
                                            $y_val = (int)$em['tahun'];
                                            $m_label = $indonesian_months[$m_val] . ' ' . $y_val;
                                        ?>
                                            <div class="col-6 col-sm-4">
                                                <div class="form-check p-2 border rounded bg-white">
                                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="bulan_disiplin[]" value="<?= $m_val ?>" id="edit_disiplin_<?= $h['id_periode'] ?>_<?= $m_val ?>" <?= in_array($m_val, $mapped_for_h) ? 'checked' : '' ?>>
                                                    <label class="form-check-label small fw-bold text-dark cursor-pointer" for="edit_disiplin_<?= $h['id_periode'] ?>_<?= $m_val ?>">
                                                        <?= $m_label ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="col-12 text-muted small p-2">
                                            <i class="fas fa-info-circle me-1 text-muted"></i> Belum ada bulan dengan riwayat data penilaian / kegiatan pada tahun kepengurusan ini.
                                        </div>
                                    <?php endif; ?>
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


