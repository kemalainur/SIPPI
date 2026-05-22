<?php
require_once '../config/database.php';
session_start();
check_login();

$title = "Dashboard Utama";
include '../layout/header.php';
include '../layout/sidebar.php';

// Fetch ACTIVE grand period
$active_p = get_active_kepengurusan();
$active_id = $active_p['id_kepengurusan'] ?? 0;

// Determine Dashboard View Mode
$role_name = $_SESSION['user']['nama_role'];
$is_admin_view = in_array($role_name, ['Super Admin', 'Sekjend', 'Bendum', 'PPI', 'Kabiro', 'Koorkam']);
$my_nokta = $_SESSION['user']['nokta'];

// Fetch Active Month (Penilaian)
$stmtPeriode = $pdo->prepare("SELECT * FROM tabel_periode WHERE status = 'aktif' AND kepengurusan_id = ? LIMIT 1");
$stmtPeriode->execute([$active_id]);
$periodeAktif = $stmtPeriode->fetch();

// 1. Manager Stats (If Admin)
if ($is_admin_view) {
    try {
        $stmtPengurus = $pdo->prepare("SELECT COUNT(*) FROM tabel_pengurus_jabatan j 
                                     JOIN tabel_pengurus p ON j.nokta = p.nokta 
                                     WHERE j.kepengurusan_id = ? AND p.angkatan != '2023'");
        $stmtPengurus->execute([$active_id]);
        $totalPengurus = $stmtPengurus->fetchColumn();

        $stmtBiro = $pdo->prepare("SELECT COUNT(*) FROM tabel_biro WHERE kepengurusan_id = ?");
        $stmtBiro->execute([$active_id]);
        $totalBiro = $stmtBiro->fetchColumn();

        $stmtAvg = $pdo->prepare("SELECT AVG(nilai_kpi_total) FROM tabel_nilai_kpi WHERE kepengurusan_id = ? AND bulan = ? AND tahun = ?");
        $stmtAvg->execute([$active_id, $periodeAktif['bulan'] ?? 0, $periodeAktif['tahun'] ?? 0]);
        $avgKPI = round($stmtAvg->fetchColumn() ?: 0, 2);

        $stmtInv = $pdo->query("SELECT SUM(jumlah) FROM tabel_inventaris");
        $totalInventaris = $stmtInv->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        $totalPengurus = $totalBiro = $totalInventaris = 0;
        $avgKPI = 0;
    }
}

// 2. Personal Metrics (For Everyone)
try {
    // Personal KPI Score (Latest)
    $stmtMyKPI = $pdo->prepare("SELECT * FROM tabel_nilai_kpi WHERE nokta = ? AND kepengurusan_id = ? ORDER BY tahun DESC, bulan DESC LIMIT 1");
    $stmtMyKPI->execute([$my_nokta, $active_id]);
    $myKPI = $stmtMyKPI->fetch();

    // Current Month Discipline Status
    if ($periodeAktif) {
        // Kas
        $stmtMyKas = $pdo->prepare("SELECT status_bayar FROM tabel_kas_pengurus WHERE nokta_pengurus = ? AND bulan = ? AND tahun = ?");
        $stmtMyKas->execute([$my_nokta, $periodeAktif['bulan'], $periodeAktif['tahun']]);
        $myKas = $stmtMyKas->fetchColumn() ?: 'belum';

        // Attendance %
        $stmtTotKeg = $pdo->prepare("SELECT COUNT(*) FROM tabel_kegiatan WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ?");
        $stmtTotKeg->execute([$periodeAktif['bulan'], $periodeAktif['tahun'], $active_id]);
        $totKeg = max(1, $stmtTotKeg->fetchColumn());

        $stmtMyHadir = $pdo->prepare("SELECT COUNT(*) FROM tabel_kehadiran WHERE nokta_pengurus = ? AND status_hadir = 'hadir' AND kegiatan_id IN (SELECT id_kegiatan FROM tabel_kegiatan WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ?)");
        $stmtMyHadir->execute([$my_nokta, $periodeAktif['bulan'], $periodeAktif['tahun'], $active_id]);
        $myHadir = round(($stmtMyHadir->fetchColumn() / $totKeg) * 100);
    } else {
        $myKas = 'N/A';
        $myHadir = 0;
    }
} catch (PDOException $e) {
    $myKPI = null;
    $myKas = 'Error';
    $myHadir = 0;
}

// 4. Universal Remaining Assessments Count (For all Raters except Super Admin)
if ($periodeAktif && $role_name != 'Super Admin') {
    $stmtRem = $pdo->prepare("SELECT COUNT(*) 
                             FROM tabel_pengurus p 
                             JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
                             JOIN tabel_role r ON j.role_id = r.id_role
                             LEFT JOIN tabel_penilaian tp ON p.nokta = tp.dinilai_nokta AND tp.penilai_nokta = ? AND tp.bulan = ? AND tp.tahun = ?
                             WHERE r.nama_role NOT IN ('PPI', 'Koorkam', 'Super Admin') 
                             AND p.nokta != ?
                             AND tp.id_penilaian IS NULL");
    $stmtRem->execute([$active_id, $my_nokta, $periodeAktif['bulan'], $periodeAktif['tahun'], $my_nokta]);
    $remAssess = $stmtRem->fetchColumn();
} else {
    $remAssess = 0;
}

$greeting = "Selamat Datang";
$time = date("H");
if ($time < 12)
    $greeting = "Selamat Pagi";
elseif ($time < 15)
    $greeting = "Selamat Siang";
elseif ($time < 18)
    $greeting = "Selamat Sore";
else
    $greeting = "Selamat Malam";
?>

<div id="content" class="fade-in">
    <!-- Top Header Bar -->
    <div class="row align-items-center mb-4 g-3">
        <div class="col-md-6">
            <h3 class="fw-800 text-dark mb-1"><?= $greeting ?>, <?= explode(' ', $_SESSION['user']['nama'])[0] ?>! 👋
            </h3>
        </div>
        <div class="col-md-6 text-md-end">
            <div
                class="d-inline-flex align-items-center p-2 bg-white rounded-pill shadow-sm border-light border px-4 h-100">
                <i class="fas fa-calendar-alt text-primary me-2"></i>
                <span class="small fw-800 text-dark"><?= $active_p['nama_periode'] ?? 'Tahun Belum Diatur' ?></span>
                <div class="vr mx-3" style="height: 20px; opacity: 0.1;"></div>
                <span
                    class="badge <?= $periodeAktif ? 'bg-brand-red-soft text-brand-red' : 'bg-slate-100 text-muted' ?> px-3 py-2 rounded-pill fw-bold">
                    <?= $periodeAktif ? '<i class="fas fa-check-circle me-1"></i> Bulan ' . $periodeAktif['bulan'] . ' Terbuka' : '<i class="fas fa-lock me-1"></i> Ditutup' ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Dashboard Content Rows -->
    <?php if ($is_admin_view): ?>
        <!-- Quick Personal KPI Card for Admins -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 bg-gradient-brand-red text-white">
                    <div class="card-body p-4 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="bg-white text-brand-red rounded-circle d-flex align-items-center justify-content-center me-3 shadow-lg"
                                style="width: 50px; height: 50px;">
                                <i class="fas fa-user-check fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-600 opacity-75">Performa Pribadi Saya</h6>
                                <h3 class="mb-0 fw-800 tracking-tight">Skor KPI:
                                    <?= number_format($myKPI['nilai_kpi_total'] ?? 0, 2) ?> / 4.0</h3>
                            </div>
                        </div>
                        <div class="text-end d-none d-md-block">
                            <span class="badge bg-white text-dark rounded-pill px-3 py-2 fw-bold small shadow-sm">
                                <i class="fas fa-calendar-day me-1"></i> Update: <?= date('M Y') ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MANAGER VIEW: Organization Focus -->
        <!-- Quick Stats Row -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 stats-card">
                    <div class="card-body p-4 position-relative">
                        <div class="d-flex align-items-center mb-4">
                            <div class="stats-icon bg-brand-red text-white shadow-lg me-3"
                                style="width: 54px; height: 54px; border-radius: 16px;">
                                <i class="fas fa-users-viewfinder fa-lg"></i>
                            </div>
                            <h6 class="text-muted fw-800 small text-uppercase mb-0 ls-1">Aktif Pengurus</h6>
                        </div>
                        <div class="d-flex align-items-baseline">
                            <h2 class="fw-800 text-dark display-6 mb-0"><?= $totalPengurus ?></h2>
                            <span
                                class="ms-3 badge bg-brand-red-soft text-brand-red rounded-pill px-3 py-1 fw-bold small">Periode
                                <?= date('Y') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 stats-card">
                    <div class="card-body p-4 position-relative">
                        <div class="d-flex align-items-center mb-4">
                            <div class="stats-icon bg-primary text-white shadow-lg me-3"
                                style="width: 54px; height: 54px; border-radius: 16px;">
                                <i class="fas fa-boxes fa-lg"></i>
                            </div>
                            <h6 class="text-muted fw-800 small text-uppercase mb-0 ls-1">Jumlah Inventaris</h6>
                        </div>
                        <div class="d-flex align-items-baseline">
                            <h2 class="fw-800 text-dark display-6 mb-0"><?= $totalInventaris ?></h2>
                            <span class="ms-3 text-muted small fw-600">Aset Terdaftar</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 bg-dark text-white stats-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-4">
                            <div class="stats-icon bg-white text-dark shadow-lg me-3"
                                style="width: 54px; height: 54px; border-radius: 16px;">
                                <i class="fas fa-chart-line fa-lg"></i>
                            </div>
                            <h6 class="text-white-50 fw-800 small text-uppercase mb-0 ls-1">Skor Rata-rata KPI</h6>
                        </div>
                        <div class="d-flex align-items-baseline mb-2">
                            <h2 class="fw-800 text-white display-6 mb-0"><?= number_format($avgKPI, 2) ?></h2>
                            <span class="ms-2 text-white-50 fw-600">/ 4.0</span>
                        </div>
                        <p class="mb-0 small text-brand-green fw-bold"><i class="fas fa-check-circle me-1"></i> Data
                            Real-time Organisasi</p>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- PERSONAL VIEW: Staff/Kadiv Focus -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <!-- Card 1: My Score -->
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 stats-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-4">
                            <div class="stats-icon bg-brand-red text-white shadow-lg me-3"
                                style="width: 50px; height: 50px; border-radius: 14px;">
                                <i class="fas fa-star fa-lg"></i>
                            </div>
                            <h6 class="text-muted fw-800 small text-uppercase mb-0 ls-1">Skor KPI Saya</h6>
                        </div>
                        <div class="d-flex align-items-baseline">
                            <h2 class="fw-800 text-dark display-6 mb-0">
                                <?= number_format($myKPI['nilai_kpi_total'] ?? 0, 2) ?></h2>
                            <span class="ms-2 text-muted fw-600">/ 4.0</span>
                        </div>
                        <p class="mb-0 text-muted small mt-2">Berdasarkan kalkulasi periode terakhir.</p>
                    </div>
                </div>
            </div>

            <!-- Card 2: Discipline Status -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 stats-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-4">
                            <div class="stats-icon bg-primary text-white shadow-lg me-3"
                                style="width: 50px; height: 50px; border-radius: 14px;">
                                <i class="fas fa-calendar-check fa-lg"></i>
                            </div>
                            <h6 class="text-muted fw-800 small text-uppercase mb-0 ls-1">Status Disiplin</h6>
                        </div>
                        <div class="d-flex flex-column gap-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small fw-600 text-muted">Kas Bulan Ini</span>
                                <span
                                    class="badge <?= $myKas == 'sudah' ? 'bg-brand-green-soft text-brand-green' : 'bg-danger-soft text-danger' ?> rounded-pill px-3 py-1">
                                    <?= strtoupper($myKas) ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small fw-600 text-muted">Presensi Aktif</span>
                                <span class="fw-800 text-dark"><?= $myHadir ?>%</span>
                            </div>
                        </div>
                        <div class="progress mt-3 bg-slate-100 shadow-none" style="height: 6px; border-radius: 10px;">
                            <div class="progress-bar bg-primary rounded-pill" style="width: <?= $myHadir ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Charts and Dynamic Side Row -->
    <div class="row g-4 mt-2">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                <div
                    class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-4 px-4 border-bottom border-light">
                    <h6 class="mb-0 fw-800 text-dark">
                        <?= $is_admin_view ? 'Tren Kinerja Kolektif' : 'Grafik Performa Saya' ?></h6>
                </div>
                <div class="card-body p-4">
                    <canvas id="kpiChart" height="280"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <?php if ($is_admin_view): ?>
                <!-- Leaderboard (Top 3) -->
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div
                        class="card-header bg-white border-0 py-4 px-4 border-bottom border-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-800 text-dark">Leaderboard (Top 3)</h6>
                        <i class="fas fa-crown text-amber-400"></i>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php
                            $stmtLeader = $pdo->prepare("SELECT p.nama, r.nama_role, n.nilai_kpi_total 
                                                       FROM tabel_nilai_kpi n
                                                       JOIN tabel_pengurus p ON n.nokta = p.nokta
                                                       JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND n.kepengurusan_id = j.kepengurusan_id
                                                       JOIN tabel_role r ON j.role_id = r.id_role
                                                       WHERE n.kepengurusan_id = ?
                                                       AND n.bulan = ? AND n.tahun = ?
                                                       AND r.nama_role NOT IN ('Super Admin', 'Sekjend', 'Bendum', 'PPI', 'Koorkam', 'Kabiro')
                                                       ORDER BY n.nilai_kpi_total DESC LIMIT 3");
                            $stmtLeader->execute([$active_id, $periodeAktif['bulan'] ?? 0, $periodeAktif['tahun'] ?? 0]);
                            $leaders = $stmtLeader->fetchAll();

                            if ($leaders):
                                foreach ($leaders as $rank => $lead):
                                    $colors = ['bg-warning', 'bg-secondary', 'bg-amber-600'];
                                    ?>
                                    <div class="list-group-item p-4 border-0 border-bottom-light transparency-hover">
                                        <div class="d-flex align-items-center">
                                            <div class="rank-badge <?= $colors[$rank] ?? 'bg-light' ?> text-white rounded-circle me-3 d-flex align-items-center justify-content-center fw-800 shadow-sm"
                                                style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                <?= $rank + 1 ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="fw-800 text-dark mb-0"><?= $lead['nama'] ?></h6>
                                                <small class="text-muted fw-600"><?= $lead['nama_role'] ?></small>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-800 text-brand-red"><?= number_format($lead['nilai_kpi_total'], 2) ?>
                                                </div>
                                                <small class="text-muted small ls-1 opacity-50">KPI SCORE</small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; else: ?>
                                <div class="p-5 text-center">
                                    <p class="text-muted small mb-0">Belum ada data.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Assessment Alert Card -->
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 <?= $remAssess > 0 ? 'border-amber border-2' : '' ?>"
                    style="background: linear-gradient(135deg, #fff 0%, #fff9f0 100%);">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-4">
                            <div class="stats-icon bg-amber-400 text-white shadow-lg me-3"
                                style="width: 50px; height: 50px; border-radius: 14px;">
                                <i class="fas fa-exclamation-circle fa-lg"></i>
                            </div>
                            <h6 class="text-muted fw-800 small text-uppercase mb-0 ls-1">Tugas Penilaian</h6>
                        </div>
                        <div class="mb-3">
                            <?php if ($remAssess > 0): ?>
                                <h2 class="fw-800 text-amber-600 display-6 mb-0"><?= $remAssess ?></h2>
                                <p class="text-muted small fw-600 mb-0">Orang belum Anda nilai bulan ini.</p>
                            <?php else: ?>
                                <h4 class="fw-800 text-brand-green mb-0"><i class="fas fa-check-circle me-2"></i>Selesai!</h4>
                                <p class="text-muted small fw-600 mb-0">Semua tugas penilaian tuntas.</p>
                            <?php endif; ?>
                        </div>
                        <?php if ($remAssess > 0): ?>
                            <a href="<?= base_url('ppi/penilaian_input.php') ?>"
                                class="btn btn-amber w-100 rounded-pill fw-800 mt-2 py-2">
                                Beri Penilaian <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div> <!-- End Right Col (col-lg-4) -->
    </div> <!-- End Main Row (row g-4 mt-2) -->
</div> <!-- End Content Wrapper -->

<style>
    .bg-light-soft {
        background-color: #f1f5f9;
        border: none;
    }

    .stats-icon {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .fw-800 {
        font-weight: 800;
    }

    .fw-600 {
        font-weight: 600;
    }

    .ls-1 {
        letter-spacing: 0.5px;
    }

    .rounded-4 {
        border-radius: 1.5rem !important;
    }

    .border-bottom-light {
        border-bottom: 1px solid #f1f5f9;
    }

    .transparency-hover:hover {
        background-color: #fcfdfe;
        transition: all 0.3s ease;
    }

    .stats-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.08) !important;
    }
</style>

<?php
// Prepare Plot Data for Chart.js
$plotLabels = [];
$plotData = [];

if ($is_admin_view) {
    // Trend Kolektif (Average of all members)
    $stmtTrend = $pdo->prepare("SELECT bulan, tahun, AVG(nilai_kpi_total) as avg_score 
                              FROM tabel_nilai_kpi 
                              WHERE kepengurusan_id = ? 
                              GROUP BY tahun, bulan 
                              ORDER BY tahun ASC, bulan ASC LIMIT 6");
    $stmtTrend->execute([$active_id]);
} else {
    // Trend Personal
    $stmtTrend = $pdo->prepare("SELECT bulan, tahun, nilai_kpi_total as avg_score 
                              FROM tabel_nilai_kpi 
                              WHERE nokta = ? AND kepengurusan_id = ? 
                              ORDER BY tahun ASC, bulan ASC LIMIT 6");
    $stmtTrend->execute([$my_nokta, $active_id]);
}

$trendResults = $stmtTrend->fetchAll();
foreach ($trendResults as $tr) {
    $plotLabels[] = $tr['bulan'] . '/' . substr($tr['tahun'], 2);
    $plotData[] = round($tr['avg_score'], 2);
}

// Fallback if no data
if (empty($plotData)) {
    $plotLabels = ['Data Kosong'];
    $plotData = [0];
}
?>

<script>
    const ctx = document.getElementById('kpiChart').getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(220, 38, 38, 0.25)'); // Red 600 soft
    gradient.addColorStop(1, 'rgba(220, 38, 38, 0.0)');

    const kpiChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($plotLabels) ?>,
            datasets: [{
                label: 'KPI Trend',
                data: <?= json_encode($plotData) ?>,
                borderColor: '#DC2626', // Red 600
                borderWidth: 5,
                tension: 0.45,
                pointRadius: 6,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#DC2626',
                pointBorderWidth: 3,
                fill: true,
                backgroundColor: gradient
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    padding: 12,
                    titleFont: { size: 14, weight: 'bold' },
                    bodyFont: { size: 13 },
                    cornerRadius: 8,
                    displayColors: false
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    min: 0,
                    max: 4.0,
                    ticks: { stepSize: 1, font: { weight: '600' } },
                    grid: { color: '#f1f5f9' }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { weight: '600' } }
                }
            }
        }
    });
</script>

<style>
    /* Additional specific styles for role-based cards */
    .border-amber {
        border-color: #fbbf24 !important;
    }

    .btn-amber {
        background-color: #fbbf24;
        color: #fff;
        border: none;
        transition: all 0.3s ease;
    }

    .btn-amber:hover {
        background-color: #f59e0b;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);
    }

    .text-amber-600 {
        color: #d97706;
    }
</style>

<?php include '../layout/footer.php'; ?>