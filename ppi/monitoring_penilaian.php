<?php
require_once '../config/database.php';
session_start();
check_login();
check_role(['Super Admin', 'PPI']);

// Active Grand Period
$active_p = get_active_kepengurusan();
if (!$active_p) {
    die("Error: Tahun Kepengurusan tidak aktif.");
}

// Active Month
$stmtAktif = $pdo->prepare("SELECT * FROM tabel_periode WHERE status = 'aktif' AND kepengurusan_id = ? LIMIT 1");
$stmtAktif->execute([$active_p['id_kepengurusan']]);
$active = $stmtAktif->fetch();

if (!$active) {
    $title = "Monitoring Penilaian";
    include '../layout/header.php';
    include '../layout/sidebar.php';
    echo '<div id="content" class="text-center mt-5">
            <div class="py-5">
                <i class="fas fa-calendar-times fa-4x text-muted opacity-25 mb-3"></i>
                <h4 class="fw-800 text-muted">Bulan Penilaian Belum Dibuka</h4>
                <p class="text-muted small">Silakan buka bulan penilaian terlebih dahulu di menu <a href="bulan_penilaian.php" class="text-primary fw-600">Bulan Penilaian</a>.</p>
            </div>
          </div>';
    include '../layout/footer.php';
    exit;
}

$bulan = $active['bulan'];
$tahun = $active['tahun'];

// Fetch all members who SHOULD rate in THIS PERIOD
$stmtMonitoring = $pdo->prepare("SELECT p.nokta, p.nama, j.jabatan, r.nama_role,
                                 (SELECT COUNT(DISTINCT dinilai_nokta) FROM tabel_penilaian WHERE penilai_nokta = p.nokta AND bulan = ? AND tahun = ?) as total_dinilai
                                 FROM tabel_pengurus p
                                 JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
                                 JOIN tabel_role r ON j.role_id = r.id_role
                                 WHERE r.nama_role != 'Super Admin'
                                 ORDER BY p.nama ASC");
$stmtMonitoring->execute([$bulan, $tahun, $active_p['id_kepengurusan']]);
$monitoring = $stmtMonitoring->fetchAll();

// Count total possible ratees (everyone except Super Admin)
$stmtRatees = $pdo->prepare("SELECT COUNT(*) FROM tabel_pengurus_jabatan j 
                             JOIN tabel_role r ON j.role_id = r.id_role
                             WHERE j.kepengurusan_id = ? 
                             AND r.nama_role != 'Super Admin'");
$stmtRatees->execute([$active_p['id_kepengurusan']]);
$countTotalPengurus = (int)$stmtRatees->fetchColumn();

// Process each rater with target (Total Pengurus - 1)
$monitoring = array_map(function($m) use ($countTotalPengurus) {
    $m['target'] = max(0, $countTotalPengurus - 1);
    $m['is_done'] = $m['total_dinilai'] >= $m['target'];
    return $m;
}, $monitoring);

$completed_count = count(array_filter($monitoring, fn($m) => $m['is_done']));
$pending_count = count($monitoring) - $completed_count;

$title = "Monitoring Penilaian";
include '../layout/header.php';
include '../layout/sidebar.php';
?>

<div id="content" class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-brand-red mb-1">Monitoring Partisipasi Penilaian</h4>
            <p class="text-muted small mb-0">Memantau progres penilaian sejawat periode <span class="fw-800 text-dark"><?= $bulan ?>/<?= $tahun ?></span></p>
        </div>
    </div>

    <!-- Stats Summary Ribbon -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="avatar-sm bg-primary-soft text-primary rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 42px; height: 42px;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-600">Total Penilai</div>
                        <div class="fw-800 text-dark"><?= count($monitoring) ?> <span class="text-muted small fw-600">Pengurus</span></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white text-brand-green">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="avatar-sm bg-brand-green-soft text-brand-green rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 42px; height: 42px;">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-600">Sudah Selesai</div>
                        <div class="fw-800 text-dark"><?= $completed_count ?> <span class="text-muted small fw-600">Pengurus</span></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white text-danger">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="avatar-sm bg-danger-soft text-danger rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 42px; height: 42px;">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-600">Belum Selesai</div>
                        <div class="fw-800 text-dark"><?= $pending_count ?> <span class="text-muted small fw-600">Pengurus</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 py-3 d-flex align-items-center mb-4">
            <i class="fas fa-check-circle me-3 fa-lg text-brand-green"></i>
            <div class="fw-600"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-slate-50 text-muted small text-uppercase fw-bold">
                        <tr>
                            <th class="ps-4 py-3">Nama Pengurus</th>
                            <th>Jabatan & Role</th>
                            <th class="text-center" width="280">Progres Partisipasi</th>
                            <th class="text-center pe-4" style="width: 180px;">Status Akurat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($monitoring): foreach($monitoring as $m): 
                            $percent = $m['target'] > 0 ? min(100, ($m['total_dinilai'] / $m['target']) * 100) : 100;
                            $is_done = $m['is_done'];
                        ?>
                        <tr class="modern-row <?= $is_done ? 'done-row' : '' ?>">
                            <td class="ps-4">
                                <div class="d-flex align-items-center py-2">
                                    <div class="avatar-md me-3 bg-primary-soft text-primary rounded-circle d-flex align-items-center justify-content-center fw-800">
                                        <?= substr($m['nama'] ?? '-', 0, 1) ?>
                                    </div>
                                    <div class="fw-800 text-dark mb-0"><?= $m['nama'] ?></div>
                                </div>
                            </td>
                            <td>
                                <div class="small fw-800 text-dark"><?= $m['jabatan'] ?></div>
                                <div class="text-muted small ls-1"><i class="fas fa-tag me-1 opacity-50"></i><?= $m['nama_role'] ?></div>
                            </td>
                            <td class="text-center px-4">
                                <div class="d-flex align-items-center">
                                    <div class="progress flex-grow-1 shadow-none bg-slate-100 me-2" style="height: 8px; border-radius: 10px;">
                                        <div class="progress-bar <?= $is_done ? 'bg-brand-red' : 'bg-primary' ?> rounded-pill" role="progressbar" 
                                             style="width: <?= $percent ?>%"></div>
                                    </div>
                                    <span class="small fw-800 text-dark"><?= $m['total_dinilai'] ?>/<?= $m['target'] ?></span>
                                </div>
                            </td>
                            <td class="text-center pe-4">
                                <?php if($is_done): ?>
                                    <span class="badge bg-brand-green-soft text-brand-green px-3 py-2 rounded-pill fw-bold" style="font-size: 0.65rem;">
                                        <i class="fas fa-check-circle me-1"></i> KOMPLIT
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-soft text-danger px-3 py-2 rounded-pill fw-bold" style="font-size: 0.65rem;">
                                        <i class="fas fa-clock me-1"></i> PROGRES
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted fw-bold">Belum ada pengurus yang wajib menilai.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- KPI Results Table -->
    <div class="mb-4 mt-5">
        <h4 class="fw-800 text-brand-red mb-1">Hasil Kalkulasi KPI</h4>
        <p class="text-muted small mb-0">Nilai akhir yang tersimpan di sistem untuk periode <span class="fw-800 text-dark"><?= $bulan ?>/<?= $tahun ?></span></p>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-slate-50 text-muted small text-uppercase fw-bold">
                        <tr>
                            <th class="ps-4 py-3">Nama Pengurus</th>
                            <th class="text-center">Attitude</th>
                            <th class="text-center">Komunikasi</th>
                            <th class="text-center">Disiplin</th>
                            <th class="text-center pe-4" style="width: 150px;">KPI TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $stmtResults = $pdo->prepare("SELECT p.nama, r.nama_role, n.* 
                                                     FROM tabel_nilai_kpi n
                                                     JOIN tabel_pengurus p ON n.nokta = p.nokta
                                                     JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND n.kepengurusan_id = j.kepengurusan_id
                                                     JOIN tabel_role r ON j.role_id = r.id_role
                                                     WHERE n.bulan = ? AND n.tahun = ? AND n.kepengurusan_id = ?
                                                     ORDER BY n.nilai_kpi_total DESC");
                        $stmtResults->execute([$bulan, $tahun, $active_p['id_kepengurusan']]);
                        $results = $stmtResults->fetchAll();

                        if($results): foreach($results as $r): 
                        ?>
                        <tr class="modern-row">
                            <td class="ps-4 py-3">
                                <div class="fw-800 text-dark mb-0"><?= $r['nama'] ?></div>
                                <div class="text-muted small ls-1"><?= $r['nama_role'] ?></div>
                            </td>
                            <td class="text-center fw-800 text-muted"><?= number_format($r['nilai_attitude'], 2) ?></td>
                            <td class="text-center fw-800 text-muted"><?= number_format($r['nilai_komunikasi'], 2) ?></td>
                            <td class="text-center fw-800 text-muted"><?= number_format($r['nilai_disiplin'], 2) ?></td>
                            <td class="text-center pe-4">
                                <div class="badge bg-brand-red text-white px-3 py-2 rounded-pill fw-bold" style="font-size: 0.85rem; width: 100px;">
                                    <?= number_format($r['nilai_kpi_total'], 2) ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted fw-bold">Penilaian bulan ini belum dikalkulasi.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.bg-slate-50 { background-color: #f8fafc; }
.bg-slate-100 { background-color: #f1f5f9; }
.bg-primary-soft { background-color: rgba(220, 38, 38, 0.1); }
.bg-brand-green-soft { background-color: #FEF2F2; color: #DC2626; }
.text-brand-green { color: #DC2626; }
.bg-brand-red { background-color: #DC2626; }
.bg-danger-soft { background-color: #F0FDF4; color: #16A34A; }
.avatar-sm { width: 34px; height: 34px; border-radius: 10px; }
.avatar-md { width: 42px; height: 42px; border-radius: 14px; font-size: 1.1rem; }
.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.modern-row:hover { background-color: #f9fafb; }
.done-row { background-color: #fbfdfc; }
.ls-1 { letter-spacing: 0.5px; }
.rounded-4 { border-radius: 1.5rem !important; }
</style>

<?php include '../layout/footer.php'; ?>


