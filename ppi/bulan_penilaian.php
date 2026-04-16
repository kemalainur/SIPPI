<?php
require_once '../config/database.php';
session_start();
check_login();
check_role(['Super Admin', 'PPI']);

$title = "Periode & Kegiatan";
include '../layout/header.php';
include '../layout/sidebar.php';

$message = '';

// Fetch ACTIVE Tahun Kepengurusan
$active_p = get_active_kepengurusan();
if (!$active_p) {
    die("Error: Tidak ada Tahun Kepengurusan yang aktif.");
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['open_periode'])) {
        $bulan = $_POST['bulan'];
        $tahun = $_POST['tahun'];
        try {
            $stmt = $pdo->prepare("INSERT INTO tabel_periode (bulan, tahun, status, kepengurusan_id) VALUES (?, ?, 'aktif', ?)");
            $stmt->execute([$bulan, $tahun, $active_p['id_kepengurusan']]);
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
        $id = $_POST['id_periode'];
        $bulan = $_POST['bulan'];
        $tahun = $_POST['tahun'];
        $stmt = $pdo->prepare("UPDATE tabel_periode SET bulan = ?, tahun = ? WHERE id_periode = ?");
        $stmt->execute([$bulan, $tahun, $id]);
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

// Fetch ACTIVE Bulan Penilaian
$stmtAktif = $pdo->prepare("SELECT * FROM tabel_periode WHERE status = 'aktif' AND kepengurusan_id = ? LIMIT 1");
$stmtAktif->execute([$active_p['id_kepengurusan']]);
$active = $stmtAktif->fetch();

// Fetch ALL Bulan Penilaian for History Table (from current year)
$stmtHistory = $pdo->prepare("SELECT * FROM tabel_periode WHERE kepengurusan_id = ? ORDER BY tahun DESC, bulan DESC");
$stmtHistory->execute([$active_p['id_kepengurusan']]);
$history = $stmtHistory->fetchAll();

// Fetch Activities for the SELECTED/ACTIVE Bulan
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
        <!-- Status Bulan Aktif -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h6 class="mb-0 fw-800"><i class="fas fa-bullseye text-primary me-2"></i>Status Bulan Aktif</h6>
                </div>
                <div class="card-body p-4">
                    <?php if ($active): ?>
                        <div class="bg-gradient-emerald p-4 rounded-4 text-white text-center shadow-sm mb-4">
                            <h2 class="fw-800 mb-0"><?= $indonesian_months[(int)$active['bulan']] ?></h2>
                            <p class="mb-0 opacity-75 fw-600 ls-1"><?= $active['tahun'] ?></p>
                            <div class="mt-3">
                                <span class="badge bg-white bg-opacity-20 px-3 py-1 rounded-pill small fw-bold">SEDANG BERJALAN</span>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
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

        <!-- Daftar Kegiatan & Riwayat -->
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
                                    </tr>

                                    <!-- Modals for this activity -->
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
                                                <p class="text-muted small">Tindakan ini akan menghapus seluruh data kehadiran yang sudah diinput untuk kegiatan ini.</p>
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
                                <?php endforeach; ?>
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

            <!-- Riwayat Bulan -->
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
                                    <th class="text-center">Status Arus</th>
                                    <th class="text-center pe-4">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($history): ?>
                                    <?php foreach($history as $h): ?>
                                        <tr class="modern-row">
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center py-1">
                                                    <div class="avatar-sm me-3 <?= $h['status'] == 'aktif' ? 'bg-brand-green-soft text-brand-green' : 'bg-slate-100 text-muted' ?> rounded-3 d-flex align-items-center justify-content-center fw-800">
                                                        <i class="fas fa-calendar-check"></i>
                                                    </div>
                                                    <div class="fw-800 text-dark"><?= $indonesian_months[(int)$h['bulan']] ?> <?= $h['tahun'] ?></div>
                                                </div>
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
                                    <tr><td colspan="3" class="text-center py-4 text-muted small">Belum ada riwayat.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- All Modals Compiled -->
<div class="modal fade" id="openModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="modal-title fw-800">Buka Bulan Penilaian</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Pilih Bulan</label>
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
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" name="open_periode" class="btn btn-primary w-100 fw-800 py-2 rounded-3 shadow-sm">Aktifkan Bulan Ini</button>
                </div>
            </form>
        </div>
    </div>
</div>

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

<?php foreach($history as $h): ?>
<div class="modal fade" id="editModal<?= $h['id_periode'] ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h6 class="fw-800">Ubah Bulan</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id_periode" value="<?= $h['id_periode'] ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-800 text-muted text-uppercase">Bulan</label>
                        <select name="bulan" class="form-select border-0 bg-light">
                            <?php foreach($indonesian_months as $m => $name): ?>
                                <option value="<?= $m ?>" <?= $m == $h['bulan'] ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-800 text-muted text-uppercase">Tahun</label>
                        <input type="number" name="tahun" value="<?= $h['tahun'] ?>" class="form-control border-0 bg-light">
                    </div>
                    <button type="submit" name="edit_bulan" class="btn btn-primary w-100 fw-800 shadow-sm">Simpan</button>
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

<style>
.bg-gradient-emerald { background: linear-gradient(135deg, #DC2626 0%, #10b981 100%); }
.bg-primary-soft { background-color: rgba(220, 38, 38, 0.1); color: #DC2626; }
.bg-slate-50 { background-color: #f8fafc; }
.bg-brand-green-soft { background-color: #FEF2F2; color: #DC2626; }
.text-brand-green { color: #DC2626; }
.bg-danger-soft { background-color: #F0FDF4; color: #16A34A; }
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


