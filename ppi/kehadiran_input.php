<?php
require_once '../config/database.php';
session_start();
check_login();
check_role(['Super Admin', 'PPI', 'Sekjend']);

$id_kegiatan = $_GET['id'] ?? null;
if (!$id_kegiatan) {
    header("Location: bulan_penilaian.php");
    exit;
}

$stmtKeg = $pdo->prepare("SELECT * FROM tabel_kegiatan WHERE id_kegiatan = ?");
$stmtKeg->execute([$id_kegiatan]);
$kegiatan = $stmtKeg->fetch();

if (!$kegiatan) {
    header("Location: bulan_penilaian.php");
    exit;
}

// Check if period is open
$stmtPeriode = $pdo->prepare("SELECT status FROM tabel_periode WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ?");
$stmtPeriode->execute([$kegiatan['bulan'], $kegiatan['tahun'], $kegiatan['kepengurusan_id']]);
$periode_status = $stmtPeriode->fetchColumn();

$is_closed = ($periode_status !== 'aktif');
$is_admin = in_array($_SESSION['user']['nama_role'], ['Super Admin', 'PPI']);

$title = "Input Kehadiran";
include '../layout/header.php';
include '../layout/sidebar.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $statuses = $_POST['status']; // Array: nokta => status
    foreach ($statuses as $nokta => $status) {
        // Check if exists
        $stmtCheck = $pdo->prepare("SELECT id FROM tabel_kehadiran WHERE kegiatan_id = ? AND nokta_pengurus = ?");
        $stmtCheck->execute([$id_kegiatan, $nokta]);
        $exists = $stmtCheck->fetch();

        if ($exists) {
            $stmtUpdate = $pdo->prepare("UPDATE tabel_kehadiran SET status_hadir = ? WHERE id = ?");
            $stmtUpdate->execute([$status, $exists['id']]);
        } else {
            $stmtInsert = $pdo->prepare("INSERT INTO tabel_kehadiran (kegiatan_id, nokta_pengurus, status_hadir) VALUES (?, ?, ?)");
            $stmtInsert->execute([$id_kegiatan, $nokta, $status]);
        }

        // AUTOMATION: Update KPI for this member
        update_kpi_member($nokta, $kegiatan['bulan'], $kegiatan['tahun'], $kegiatan['kepengurusan_id']);
    }
    $message = "Kehadiran berhasil disimpan!";
}

// Fetch all members. Everyone usually attends activities.
$stmtMembers = $pdo->prepare("SELECT p.nokta, p.nama, j.jabatan, k.status_hadir 
                            FROM tabel_pengurus p 
                            JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
                            LEFT JOIN tabel_kehadiran k ON p.nokta = k.nokta_pengurus AND k.kegiatan_id = ?
                            ORDER BY p.nokta ASC");
$stmtMembers->execute([$kegiatan['kepengurusan_id'], $id_kegiatan]);
$members = $stmtMembers->fetchAll();
?>

<div id="content" class="fade-in">
    <div class="mb-4 d-flex justify-content-between align-items-center g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-brand-red mb-1">Presensi: <?= $kegiatan['nama_kegiatan'] ?></h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small mb-0">
                    <li class="breadcrumb-item"><a href="bulan_penilaian.php" class="text-primary text-decoration-none fw-600">Bulan Penilaian</a></li>
                    <li class="breadcrumb-item active fw-600">Input Kehadiran</li>
                </ol>
            </nav>
        </div>
        <a href="bulan_penilaian.php" class="btn btn-light-soft text-muted px-4 fw-600 shadow-sm border">
            <i class="fas fa-arrow-left me-2"></i> Kembali
        </a>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-success border-0 shadow-sm rounded-4 p-3 mb-4 d-flex align-items-center" role="alert">
        <i class="fas fa-check-circle fa-lg me-3 text-brand-green"></i>
        <div class="fw-600"><?= $message ?></div>
    </div>
    <?php endif; ?>

    <?php if ($is_closed): ?>
    <div class="alert alert-warning border-0 shadow-sm rounded-4 p-3 mb-4 d-flex align-items-center" role="alert">
        <i class="fas fa-lock fa-lg me-3"></i>
        <div class="fw-600">Bulan penilaian ini telah ditutup. Data presensi hanya dapat dilihat dan tidak dapat diubah.</div>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
            <div class="card-header bg-white border-0 py-4 px-4 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-800 text-dark">Daftar Kehadiran Pengurus</h6>
                <span class="badge bg-slate-100 text-slate-600 px-3 py-2 rounded-pill fw-bold small">Total: <?= count($members) ?> Orang</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-slate-50 text-muted small text-uppercase fw-bold">
                            <tr>
                                <th class="ps-4 py-3">Nama Pengurus</th>
                                <th class="text-center" width="350">Status Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($members as $m): ?>
                            <tr class="modern-row">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center py-2">
                                        <div class="avatar-sm me-3 bg-primary-soft text-primary rounded-circle d-flex align-items-center justify-content-center fw-800">
                                            <?= substr($m['nama'] ?? '-', 0, 1) ?>
                                        </div>
                                        <div>
                                            <div class="fw-800 text-dark mb-0"><?= $m['nama'] ?></div>
                                            <div class="text-muted small ls-1"><?= $m['nokta'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center pe-4">
                                    <div class="presence-toggle d-flex justify-content-center">
                                        <div class="btn-group rounded-pill overflow-hidden shadow-sm border" role="group">
                                            <input type="radio" class="btn-check" name="status[<?= $m['nokta'] ?>]" 
                                                   id="h_<?= $m['nokta'] ?>" value="hadir" 
                                                   <?= $m['status_hadir'] == 'hadir' ? 'checked' : '' ?> <?= $is_closed ? 'disabled' : '' ?> required>
                                            <label class="btn btn-outline-success btn-sm px-3 fw-800 border-0" for="h_<?= $m['nokta'] ?>">HADIR</label>
                    
                                            <input type="radio" class="btn-check" name="status[<?= $m['nokta'] ?>]" 
                                                   id="i_<?= $m['nokta'] ?>" value="izin" 
                                                   <?= $m['status_hadir'] == 'izin' ? 'checked' : '' ?> <?= $is_closed ? 'disabled' : '' ?>>
                                            <label class="btn btn-outline-info btn-sm px-3 fw-800 border-0" for="i_<?= $m['nokta'] ?>">IZIN</label>

                                            <input type="radio" class="btn-check" name="status[<?= $m['nokta'] ?>]" 
                                                   id="l_<?= $m['nokta'] ?>" value="telat" 
                                                   <?= $m['status_hadir'] == 'telat' ? 'checked' : '' ?> <?= $is_closed ? 'disabled' : '' ?>>
                                            <label class="btn btn-outline-warning btn-sm px-3 fw-800 border-0" for="l_<?= $m['nokta'] ?>">TELAT</label>
                    
                                            <input type="radio" class="btn-check" name="status[<?= $m['nokta'] ?>]" 
                                                   id="t_<?= $m['nokta'] ?>" value="alpa" 
                                                   <?= ($m['status_hadir'] == 'alpa' || !$m['status_hadir']) ? 'checked' : '' ?> <?= $is_closed ? 'disabled' : '' ?>>
                                            <label class="btn btn-outline-danger btn-sm px-3 fw-800 border-0" for="t_<?= $m['nokta'] ?>">ALPA</label>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white border-0 p-4 text-end border-top border-light">
                <?php if (!$is_closed): ?>
                <button type="submit" class="btn btn-primary px-5 fw-800 rounded-pill py-3 shadow-lg">
                    <i class="fas fa-save me-2"></i> Simpan Data Presensi
                </button>
                <?php else: ?>
                <button type="button" class="btn btn-secondary px-5 fw-800 rounded-pill py-3 shadow-lg" disabled>
                    <i class="fas fa-lock me-2"></i> Periode Terkunci
                </button>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<style>
.bg-slate-50 { background-color: #f8fafc; }
.bg-slate-100 { background-color: #f1f5f9; }
.text-slate-600 { color: #475569; }
.bg-primary-soft { background-color: rgba(220, 38, 38, 0.1); }
.text-brand-green { color: #DC2626; }
.btn-light-soft { background-color: #f1f5f9; border: none; }
.btn-outline-success { color: #16A34A; border-color: #16A34A; }
.btn-outline-success:hover, .btn-check:checked + label.btn-outline-success { background-color: #16A34A; color: #fff; }
.btn-outline-info { color: #0EA5E9; border-color: #0EA5E9; }
.btn-outline-info:hover, .btn-check:checked + label.btn-outline-info { background-color: #0EA5E9; color: #fff; }
.btn-check:checked + label.btn-outline-warning { background-color: #f59e0b; color: #fff; border-color: #f59e0b; }
.btn-check:checked + label.btn-outline-danger { background-color: #DC2626; color: #fff; border-color: #DC2626; }
.avatar-sm { width: 36px; height: 36px; border-radius: 10px; font-size: 0.8rem; }
.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.modern-row:hover { background-color: #f9fafb; }
.ls-1 { letter-spacing: 0.5px; }
.rounded-4 { border-radius: 1.5rem !important; }
.btn-group .btn { border-radius: 0; padding-top: 0.6rem; padding-bottom: 0.6rem; font-size: 0.7rem; }
</style>

<?php include '../layout/footer.php'; ?>


