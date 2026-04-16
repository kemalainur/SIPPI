<?php
require_once '../config/database.php';
session_start();
check_login();

$nokta = $_SESSION['user']['nokta'];
$role = $_SESSION['user']['nama_role'];

// Different views for different roles
// Kabiro sees their biro, Kadiv sees their divisi, Staff sees themselves.
// PPI/Koorkam can search for anyone?
// For now, let's focus on "Individual Raport" for the current logged-in user.

$title = "Grafik & Raport Saya";
include '../layout/header.php';
include '../layout/sidebar.php';

// Fetch ACTIVE grand period
$active_p = get_active_kepengurusan();
$active_id = $active_p['id_kepengurusan'] ?? 0;

// Fetch KPI History for Chart - Filtered by active period context
$stmtHistory = $pdo->prepare("SELECT bulan, tahun, nilai_attitude, nilai_komunikasi, nilai_disiplin, nilai_kpi_total 
                             FROM tabel_nilai_kpi 
                             WHERE nokta = ? AND kepengurusan_id = ?
                             ORDER BY tahun ASC, bulan ASC");
$stmtHistory->execute([$nokta, $active_id]);
$history = $stmtHistory->fetchAll();

// Fetch Latest Performance
$latest = !empty($history) ? $history[count($history)-1] : null;

$labels = [];
$dataAttitude = [];
$dataKomunikasi = [];
$dataDisiplin = [];
$dataTotal = [];

foreach($history as $h) {
    $labels[] = $h['bulan'] . '/' . $h['tahun'];
    $dataAttitude[] = (float)$h['nilai_attitude'];
    $dataKomunikasi[] = (float)$h['nilai_komunikasi'];
    $dataDisiplin[] = (float)$h['nilai_disiplin'];
    $dataTotal[] = (float)$h['nilai_kpi_total'];
}
?>

<<div id="content" class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-brand-red mb-1">Performa & Raport Saya</h4>
            <p class="text-muted small">Evaluasi komprehensif capaian kinerja organisasi Anda.</p>
        </div>
        <?php if($latest): ?>
        <a href="cetak_raport.php?bulan=<?= $latest['bulan'] ?>&tahun=<?= $latest['tahun'] ?>" 
           target="_blank" class="btn btn-primary d-flex align-items-center shadow-sm px-4">
            <i class="fas fa-print me-2"></i> Cetak Raport Terakhir
        </a>
        <?php endif; ?>
    </div>

    <?php if (!$latest): ?>
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white opacity-75">
            <div class="stats-icon bg-light text-muted mx-auto mb-4" style="width: 80px; height: 80px; border-radius: 25px;">
                <i class="fas fa-chart-line fa-3x"></i>
            </div>
            <h5 class="fw-800 text-dark">Data Belum Tersedia</h5>
            <p class="text-muted px-lg-5 mb-0">PPI belum melakukan kalkulasi nilai untuk akun Anda di periode ini. Silakan cek kembali setelah evaluasi bulanan ditutup.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <!-- Individual KPI Cards -->
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 p-4 text-center bg-white h-100">
                    <div class="text-muted small text-uppercase fw-800 ls-1 mb-2">Attitude</div>
                    <h2 class="fw-800 text-rose mb-0"><?= number_format($latest['nilai_attitude'], 2) ?></h2>
                    <div class="progress mt-3 rounded-pill" style="height: 6px;">
                        <div class="progress-bar bg-rose" role="progressbar" style="width: <?= ($latest['nilai_attitude']/4)*100 ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 p-4 text-center bg-white h-100">
                    <div class="text-muted small text-uppercase fw-800 ls-1 mb-2">Komunikasi</div>
                    <h2 class="fw-800 text-brand-red mb-0"><?= number_format($latest['nilai_komunikasi'], 2) ?></h2>
                    <div class="progress mt-3 rounded-pill" style="height: 6px;">
                        <div class="progress-bar bg-brand-red" role="progressbar" style="width: <?= ($latest['nilai_komunikasi']/4)*100 ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 p-4 text-center bg-white h-100">
                    <div class="text-muted small text-uppercase fw-800 ls-1 mb-2">Kedisiplinan</div>
                    <h2 class="fw-800 text-primary mb-0"><?= number_format($latest['nilai_disiplin'], 2) ?></h2>
                    <div class="progress mt-3 rounded-pill" style="height: 6px;">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= ($latest['nilai_disiplin']/4)*100 ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card bg-gradient-emerald text-white border-0 shadow-lg rounded-4 p-4 text-center h-100">
                    <div class="text-white opacity-75 small text-uppercase fw-800 ls-1 mb-2">Total KPI</div>
                    <h2 class="fw-800 mb-0"><?= number_format($latest['nilai_kpi_total'], 2) ?></h2>
                    <div class="progress bg-white bg-opacity-20 mt-3 rounded-pill" style="height: 6px;">
                        <div class="progress-bar bg-white" role="progressbar" style="width: <?= ($latest['nilai_kpi_total']/4)*100 ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Graphs -->
            <div class="col-lg-12">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white border-0 py-4 px-4">
                        <h6 class="mb-0 fw-800"><i class="fas fa-chart-area text-primary me-2"></i>Tren Kinerja Bulanan</h6>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div style="height: 300px; width: 100%;">
                            <canvas id="performaChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- History Table -->
            <div class="col-lg-12 mb-5">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white border-0 py-4 px-4">
                        <h6 class="mb-0 fw-800"><i class="fas fa-history text-primary me-2"></i>Riwayat Nilai KPI</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-slate-50 text-muted small text-uppercase fw-bold">
                                    <tr>
                                        <th class="ps-4 py-3">Bulan/Tahun</th>
                                        <th class="text-center">Attitude</th>
                                        <th class="text-center">Komunikasi</th>
                                        <th class="text-center">Disiplin</th>
                                        <th class="text-center">Total KPI</th>
                                        <th class="text-end pe-4" style="width: 100px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($history as $h): ?>
                                    <tr class="modern-row">
                                        <td class="ps-4">
                                            <div class="fw-800 text-dark"><?= $h['bulan'] ?> / <?= $h['tahun'] ?></div>
                                        </td>
                                        <td class="text-center fw-600 text-muted"><?= number_format($h['nilai_attitude'], 2) ?></td>
                                        <td class="text-center fw-600 text-muted"><?= number_format($h['nilai_komunikasi'], 2) ?></td>
                                        <td class="text-center fw-600 text-muted"><?= number_format($h['nilai_disiplin'], 2) ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-brand-green-soft text-brand-green px-3 py-1 rounded-pill small fw-bold">
                                                <?= number_format($h['nilai_kpi_total'], 2) ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="cetak_raport.php?bulan=<?= $h['bulan'] ?>&tahun=<?= $h['tahun'] ?>" 
                                               target="_blank" class="btn btn-icon btn-light-soft text-primary" title="PDF Raport">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
<?php if($latest): ?>
    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById('performaChart').getContext('2d');
        
        // Create gradients
        const gradTotal = ctx.createLinearGradient(0, 0, 0, 300);
        gradTotal.addColorStop(0, 'rgba(220, 38, 38, 0.2)');
        gradTotal.addColorStop(1, 'rgba(220, 38, 38, 0)');

        const performaChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [
                    {
                        label: 'Total KPI Score',
                        data: <?= json_encode($dataTotal) ?>,
                        borderColor: '#DC2626',
                        backgroundColor: gradTotal,
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#DC2626',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Kedisiplinan',
                        data: <?= json_encode($dataDisiplin) ?>,
                        borderColor: '#3b82f6',
                        backgroundColor: 'transparent',
                        borderDash: [5, 5],
                        borderWidth: 2,
                        tension: 0.4,
                        pointRadius: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            font: { family: 'Plus Jakarta Sans', weight: '600', size: 12 }
                        }
                    },
                    tooltip: {
                        padding: 12,
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleFont: { family: 'Plus Jakarta Sans', size: 13, weight: '700' },
                        bodyFont: { family: 'Plus Jakarta Sans', size: 12 },
                        cornerRadius: 8,
                        usePointStyle: true
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        max: 4,
                        grid: { borderDash: [5, 5], color: '#f1f5f9' },
                        ticks: { stepSize: 1, font: { family: 'Plus Jakarta Sans', weight: '600' } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Plus Jakarta Sans', weight: '600' } }
                    }
                }
            }
        });
    });
<?php endif; ?>
</script>

<style>
.bg-gradient-emerald { background: linear-gradient(135deg, #DC2626 0%, #10b981 100%); }
.bg-brand-green-soft { background-color: #FEF2F2; color: #DC2626; }
.text-brand-green { color: #DC2626; }
.bg-rose-soft { background-color: #F0FDF4; color: #16A34A; }
.text-rose { color: #16A34A; }
.bg-slate-50 { background-color: #f8fafc; }
.btn-light-soft { background-color: #f1f5f9; border: none; }
.btn-icon { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
.stats-icon { display: flex; align-items: center; justify-content: center; }
.ls-1 { letter-spacing: 0.5px; }
.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.modern-row:hover { background-color: #f9fafb; }
.rounded-4 { border-radius: 1.25rem !important; }
</style>

<?php include '../layout/footer.php'; ?>

<?php include '../layout/footer.php'; ?>


