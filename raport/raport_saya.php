<?php
require_once '../config/database.php';
session_start();
check_login();

$nokta = $_SESSION['user']['nokta'];
$role = $_SESSION['user']['nama_role'];
$is_admin = in_array($role, ['Super Admin', 'PPI', 'Koorkam']);
$view_nokta = ($is_admin && isset($_GET['nokta'])) ? $_GET['nokta'] : $nokta;

// Fetch member info if viewing someone else or if admin
$stmtM = $pdo->prepare("SELECT nama FROM tabel_pengurus WHERE nokta = ?");
$stmtM->execute([$view_nokta]);
$member_name = $stmtM->fetchColumn() ?: 'Pengurus';

$title = ($view_nokta == $nokta) ? "Grafik & Raport Saya" : "Raport: $member_name";
include '../layout/header.php';
include '../layout/sidebar.php';

$all_members = [];
if ($is_admin) {
    $all_members = $pdo->query("SELECT nokta, nama FROM tabel_pengurus WHERE (angkatan IS NULL OR angkatan != '2023') ORDER BY nama ASC")->fetchAll();
}

$active_p = get_active_kepengurusan();
$active_id = $active_p['id_kepengurusan'] ?? 0;

$stmtHistory = $pdo->prepare("SELECT k.bulan, k.tahun, k.nilai_attitude, k.nilai_komunikasi, k.nilai_disiplin, k.nilai_kpi_total,
                              p.jenis_periode, p.mode_penilaian, p.nama_sesi
                             FROM tabel_nilai_kpi k
                             LEFT JOIN tabel_periode p ON k.bulan = p.bulan AND k.tahun = p.tahun AND k.kepengurusan_id = p.kepengurusan_id
                             WHERE k.nokta = ? AND k.kepengurusan_id = ?
                             ORDER BY k.tahun ASC, k.bulan ASC");
$stmtHistory->execute([$view_nokta, $active_id]);
$history = $stmtHistory->fetchAll();

$view_bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : null;
$view_tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : null;

$latest = null;
if (!empty($history)) {
    if ($view_bulan && $view_tahun) {
        foreach($history as $h) {
            if ($h['bulan'] == $view_bulan && $h['tahun'] == $view_tahun) {
                $latest = $h;
                break;
            }
        }
    }
    if (!$latest) $latest = $history[count($history)-1];
}

$labels = [];
$dataAttitude = [];
$dataKomunikasi = [];
$dataDisiplin = [];
$dataTotal = [];

foreach($history as $h) {
    $labels[] = format_nama_periode($h);
    $dataAttitude[] = (float)$h['nilai_attitude'];
    $dataKomunikasi[] = (float)$h['nilai_komunikasi'];
    $dataDisiplin[] = (float)$h['nilai_disiplin'];
    $dataTotal[] = (float)$h['nilai_kpi_total'];
}

$detailAttendance = [];
$detailKas = null;
$detailIndicators = [];

if ($latest) {
    $is_latest_triwulan = (($latest['jenis_periode'] ?? '') === 'Triwulan' || ($latest['jenis_periode'] ?? '') === 'Triwulanan' || ($latest['mode_penilaian'] ?? '') === 'Peer Assessment');
    
    if ($is_latest_triwulan) {
        $stmtMap = $pdo->prepare("SELECT bulan_sumber, tahun_sumber FROM tabel_periode_disiplin_bulan 
                                  WHERE periode_id = (SELECT id_periode FROM tabel_periode WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ? LIMIT 1)");
        $stmtMap->execute([$latest['bulan'], $latest['tahun'], $active_id]);
        $mapped_months = $stmtMap->fetchAll(PDO::FETCH_ASSOC);
        
        $src_b_list = array_column($mapped_months, 'bulan_sumber');
        if (!empty($src_b_list)) {
            $inClause = implode(',', array_map('intval', $src_b_list));
            $stmtAtt = $pdo->prepare("SELECT k.nama_kegiatan, h.status_hadir 
                                     FROM tabel_kehadiran h 
                                     JOIN tabel_kegiatan k ON h.kegiatan_id = k.id_kegiatan 
                                     WHERE h.nokta_pengurus = ? AND k.bulan IN ($inClause) AND k.tahun = ?");
            $stmtAtt->execute([$view_nokta, $latest['tahun']]);
            $detailAttendance = $stmtAtt->fetchAll();
        }
        $stmtK = $pdo->prepare("SELECT * FROM tabel_kas_pengurus WHERE nokta_pengurus = ? AND tahun = ? ORDER BY bulan DESC LIMIT 1");
        $stmtK->execute([$view_nokta, $latest['tahun']]);
        $detailKas = $stmtK->fetch();
    } else {
        $stmtAtt = $pdo->prepare("SELECT k.nama_kegiatan, h.status_hadir 
                                 FROM tabel_kehadiran h 
                                 JOIN tabel_kegiatan k ON h.kegiatan_id = k.id_kegiatan 
                                 WHERE h.nokta_pengurus = ? AND k.bulan = ? AND k.tahun = ?");
        $stmtAtt->execute([$view_nokta, $latest['bulan'], $latest['tahun']]);
        $detailAttendance = $stmtAtt->fetchAll();

        $stmtK = $pdo->prepare("SELECT * FROM tabel_kas_pengurus WHERE nokta_pengurus = ? AND bulan = ? AND tahun = ?");
        $stmtK->execute([$view_nokta, $latest['bulan'], $latest['tahun']]);
        $detailKas = $stmtK->fetch();
    }

    $stmtInd = $pdo->prepare("SELECT i.nama_indikator, i.kategori, AVG(tp.skor) as avg_score 
                             FROM tabel_penilaian tp 
                             JOIN tabel_indikator i ON tp.indikator_id = i.id_indikator 
                             WHERE tp.dinilai_nokta = ? AND tp.bulan = ? AND tp.tahun = ?
                             GROUP BY i.id_indikator");
    $stmtInd->execute([$view_nokta, $latest['bulan'], $latest['tahun']]);
    $detailIndicators = $stmtInd->fetchAll();
}
?>

<div id="content" class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-brand-red mb-1"><?= ($view_nokta == $nokta) ? 'Performa & Raport Saya' : 'Performa: '.$member_name ?></h4>
        </div>
        <div class="d-flex gap-2">
            <?php if($is_admin): ?>
            <div class="dropdown">
                <button class="btn btn-outline-dark dropdown-toggle rounded-pill px-4 shadow-sm fw-600" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-search me-2"></i> Cari Pengurus
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 p-2 mt-2" style="max-height: 300px; overflow-y: auto; min-width: 250px;">
                    <li><h6 class="dropdown-header small text-muted text-uppercase">Pilih Pengurus</h6></li>
                    <?php foreach($all_members as $am): ?>
                    <li><a class="dropdown-item rounded-3 py-2 <?= $view_nokta == $am['nokta'] ? 'active' : '' ?>" href="?nokta=<?= $am['nokta'] ?>"><?= $am['nama'] ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
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
                <div class="card border-0 shadow-sm rounded-4 p-4 text-center bg-white h-100 d-flex flex-column">
                    <div class="flex-grow-1">
                        <div class="text-muted small text-uppercase fw-800 ls-1 mb-2">Attitude</div>
                        <h2 class="fw-800 text-rose mb-0"><?= number_format($latest['nilai_attitude'], 2) ?></h2>
                        <div class="progress mt-3 rounded-pill" style="height: 6px;">
                            <div class="progress-bar bg-rose" role="progressbar" style="width: <?= ($latest['nilai_attitude']/4)*100 ?>%"></div>
                        </div>
                    </div>
                    <button class="btn btn-rose w-100 rounded-pill mt-4 btn-sm fw-600 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPerformance">
                        <i class="fas fa-eye me-1"></i> Rincian Nilai
                    </button>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 p-4 text-center bg-white h-100 d-flex flex-column">
                    <div class="flex-grow-1">
                        <div class="text-muted small text-uppercase fw-800 ls-1 mb-2">Komunikasi</div>
                        <h2 class="fw-800 text-brand-red mb-0"><?= number_format($latest['nilai_komunikasi'], 2) ?></h2>
                        <div class="progress mt-3 rounded-pill" style="height: 6px;">
                            <div class="progress-bar bg-brand-red" role="progressbar" style="width: <?= ($latest['nilai_komunikasi']/4)*100 ?>%"></div>
                        </div>
                    </div>
                    <button class="btn btn-brand-red w-100 rounded-pill mt-4 btn-sm fw-600 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPerformance">
                        <i class="fas fa-eye me-1"></i> Rincian Nilai
                    </button>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 p-4 text-center bg-white h-100 d-flex flex-column">
                    <div class="flex-grow-1">
                        <div class="text-muted small text-uppercase fw-800 ls-1 mb-2">Kedisiplinan</div>
                        <h2 class="fw-800 text-primary mb-0"><?= number_format($latest['nilai_disiplin'], 2) ?></h2>
                        <div class="progress mt-3 rounded-pill" style="height: 6px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= ($latest['nilai_disiplin']/4)*100 ?>%"></div>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100 rounded-pill mt-4 btn-sm fw-600 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalDisiplin">
                        <i class="fas fa-search-plus me-1"></i> Lihat Rincian
                    </button>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card bg-gradient-emerald text-white border-0 shadow-lg rounded-4 p-4 text-center h-100 d-flex flex-column">
                    <div class="flex-grow-1">
                        <div class="text-white opacity-75 small text-uppercase fw-800 ls-1 mb-2">Total KPI</div>
                        <h2 class="fw-800 mb-0"><?= number_format($latest['nilai_kpi_total'] ?? 0, 2) ?></h2>
                        <div class="progress bg-white bg-opacity-20 mt-3 rounded-pill" style="height: 6px;">
                            <div class="progress-bar bg-white" role="progressbar" style="width: <?= (($latest['nilai_kpi_total'] ?? 0)/4)*100 ?>%"></div>
                        </div>
                    </div>
                    <button class="btn btn-white bg-white text-dark w-100 rounded-pill mt-4 btn-sm fw-800 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalBobot">
                        <i class="fas fa-calculator me-1"></i> Rumus Kalkulasi
                    </button>
                </div>
            </div>

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
                                            <div class="fw-800 text-dark"><?= format_nama_periode($h) ?></div>
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
                                            <a href="?nokta=<?= $view_nokta ?>&bulan=<?= $h['bulan'] ?>&tahun=<?= $h['tahun'] ?>" 
                                               class="btn btn-sm <?= ($latest['bulan']==$h['bulan'] && $latest['tahun']==$h['tahun']) ? 'btn-primary' : 'btn-outline-primary' ?> rounded-pill px-3">
                                                <i class="fas fa-search me-1"></i> Detail
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
/* Base Styles */
.bg-gradient-emerald { background: linear-gradient(135deg, #DC2626 0%, #10b981 100%); }
.bg-brand-green-soft { background-color: #FEF2F2; color: #DC2626; }
.text-brand-green { color: #DC2626; }
.bg-rose-soft { background-color: #F0FDF4; color: #16A34A; }
.text-rose { color: #16A34A; }
.bg-slate-50 { background-color: #f8fafc; }
.btn-rose { background: #16A34A; color: white; border: none; }
.btn-rose:hover { background: #15803d; color: white; }
.btn-brand-red { background: #DC2626; color: white; border: none; }
.btn-brand-red:hover { background: #b91c1c; color: white; }

.rounded-4 { border-radius: 1.25rem !important; }
.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.ls-1 { letter-spacing: 0.5px; }

@keyframes modalSlideUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal.show .modal-content {
    animation: modalSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.glass-modal {
    background: rgba(255, 255, 255, 0.85) !important;
    backdrop-filter: blur(16px) saturate(180%);
    -webkit-backdrop-filter: blur(16px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
}

.att-card {
    background: white;
    border: 1px solid #f1f5f9;
    padding: 1rem;
    border-radius: 1rem;
    transition: all 0.2s ease;
}

.att-card:hover { border-color: #e2e8f0; transform: scale(1.01); }

.score-badge {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-weight: 800;
}

.bg-success-soft { background: #ecfdf5 !important; color: #059669 !important; }
.bg-danger-soft { background: #fef2f2 !important; color: #dc2626 !important; }
.bg-warning-soft { background: #fffbeb !important; color: #d97706 !important; }
.bg-info-soft { background: #eff6ff !important; color: #2563eb !important; }

@media (max-width: 768px) {
    .modal-dialog-centered { margin: 1rem; }
    .display-6 { font-size: 2rem; }
}
</style>

<?php if($latest): ?>
<div class="modal fade" id="modalDisiplin" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content glass-modal border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 p-4 pb-1">
                <div class="d-flex align-items-center">
                    <div class="bg-primary text-white rounded-3 me-3 d-flex align-items-center justify-content-center shadow-lg" style="width: 48px; height: 48px;">
                        <i class="fas fa-calendar-check fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-800 text-dark mb-0">Detail Kedisiplinan</h5>
                        <p class="text-muted small mb-0">Data absensi & keuangan bulan ini.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 pt-4">
                <!-- Summary Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="p-3 bg-white border border-light rounded-4 shadow-sm h-100">
                            <h6 class="small text-muted mb-2 fw-700 text-uppercase ls-1">Status Keuangan</h6>
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle <?= ($detailKas['status_bayar'] ?? '') == 'sudah' ? 'bg-success' : 'bg-danger' ?> me-2" style="width: 12px; height: 12px;"></div>
                                    <span class="fw-800 text-dark"><?= strtoupper($detailKas['status_bayar'] ?? 'BELUM BAYAR') ?></span>
                                </div>
                                <span class="h5 mb-0 fw-800 text-primary">Rp <?= number_format($detailKas['nominal'] ?? 0, 0, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-white border border-light rounded-4 shadow-sm h-100">
                            <h6 class="small text-muted mb-2 fw-700 text-uppercase ls-1">Skor Disiplin Akhir</h6>
                            <div class="d-flex align-items-baseline">
                                <h3 class="fw-800 text-dark mb-0"><?= number_format($latest['nilai_disiplin'] ?? 0, 2) ?></h3>
                                <span class="ms-2 text-muted fw-600">/ 4.0</span>
                            </div>
                        </div>
                    </div>
                </div>

                <h6 class="fw-800 mb-3 text-dark d-flex align-items-center">
                    <i class="fas fa-clock text-primary me-2"></i> Log Kehadiran Digital
                </h6>
                
                <div class="row g-3">
                    <?php if($detailAttendance): foreach($detailAttendance as $da): 
                        $status = strtolower($da['status_hadir']);
                        if ($status == 'hadir') {
                            $badgeClass = 'bg-success-soft';
                        } elseif (strpos($status, 'telat') !== false) {
                            $badgeClass = 'bg-warning-soft';
                        } elseif (strpos($status, 'izin') !== false || strpos($status, 'sakit') !== false) {
                            $badgeClass = 'bg-info-soft';
                        } else {
                            $badgeClass = 'bg-danger-soft';
                        }
                    ?>
                    <div class="col-12 col-md-6">
                        <div class="att-card shadow-sm border-0 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-800 text-dark small mb-1"><?= $da['nama_kegiatan'] ?></div>
                                <div class="text-muted" style="font-size: 0.7rem;"><i class="fas fa-map-marker-alt me-1"></i> Lokasi: Sekretariat/Online</div>
                            </div>
                            <span class="badge <?= $badgeClass ?> rounded-pill px-3 py-2 fw-800" style="min-width: 80px; text-align: center;">
                                <?= strtoupper($da['status_hadir']) ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; else: ?>
                    <div class="col-12 text-center py-4 opacity-50">
                        <i class="fas fa-folder-open fa-2x mb-2"></i>
                        <p class="small mb-0">Belum ada log kehadiran di bulan ini.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPerformance" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content glass-modal border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 p-4 pb-1">
                <div class="d-flex align-items-center">
                    <div class="bg-brand-red text-white rounded-3 me-3 d-flex align-items-center justify-content-center shadow-lg" style="width: 48px; height: 48px;">
                        <i class="fas fa-users-viewfinder fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-800 text-dark mb-0">Detail Peer-Review</h5>
                        <p class="text-muted small mb-0">Rangkuman penilaian tim & rekan.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 pt-4">
                <div class="alert bg-brand-red-soft text-brand-red border-0 small mb-4 py-3 rounded-4">
                    <i class="fas fa-info-circle me-2"></i> Skor ini merupakan rata-rata akumulasi penilaian yang diberikan secara anonim oleh rekan sejawat Anda.
                </div>

                <div class="d-flex flex-column gap-3">
                    <?php if($detailIndicators): foreach($detailIndicators as $di): 
                        $score = (float)$di['avg_score'];
                        $color = $score >= 3.5 ? 'bg-success' : ($score >= 2.5 ? 'bg-warning' : 'bg-danger');
                        $softColor = $score >= 3.5 ? 'bg-success-soft text-success' : ($score >= 2.5 ? 'bg-warning-soft text-warning' : 'bg-danger-soft text-danger');
                    ?>
                    <div class="p-3 bg-white border border-light rounded-4 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h6 class="fw-800 text-dark mb-0"><?= $di['nama_indikator'] ?></h6>
                                <span class="badge <?= $softColor ?> rounded-pill" style="font-size: 0.6rem;"><?= strtoupper($di['kategori'] ?? 'KPI') ?></span>
                            </div>
                            <div class="text-end">
                                <span class="h5 fw-800 text-dark mb-0"><?= number_format($score, 2) ?></span>
                                <span class="small text-muted">/ 4.0</span>
                            </div>
                        </div>
                        <div class="progress rounded-pill bg-slate-100" style="height: 8px;">
                            <div class="progress-bar <?= $color ?> rounded-pill" style="width: <?= ($score/4)*100 ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; else: ?>
                    <div class="text-center py-5 opacity-50">
                        <i class="fas fa-users-slash fa-2x mb-2"></i>
                        <p class="small mb-0">Belum ada penilaian peer-review.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalBobot" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 p-4">
                <div class="d-flex align-items-center">
                    <div class="bg-dark text-white rounded-3 me-3 d-flex align-items-center justify-content-center shadow-lg" style="width: 48px; height: 48px;">
                        <i class="fas fa-calculator fa-lg"></i>
                    </div>
                    <h5 class="modal-title fw-800 text-dark mb-0">Rumus Kalkulasi KPI</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 pt-0">
                <p class="text-muted small mb-4">Total KPI dihitung secara otomatis dengan pembobotan berikut:</p>
                
                <div class="d-flex flex-column gap-3 mb-4">
                    <div class="d-flex align-items-center p-3 bg-white rounded-4 border border-light shadow-sm">
                        <div class="bg-rose text-white rounded-3 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="small fw-800 text-dark">Attitude</div>
                            <div class="progress mt-1 rounded-pill" style="height: 4px;">
                                <div class="progress-bar bg-rose" style="width: 40%"></div>
                            </div>
                        </div>
                        <div class="ms-3 h5 mb-0 fw-800 text-rose">40%</div>
                    </div>

                    <div class="d-flex align-items-center p-3 bg-white rounded-4 border border-light shadow-sm">
                        <div class="bg-brand-red text-white rounded-3 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="small fw-800 text-dark">Komunikasi</div>
                            <div class="progress mt-1 rounded-pill" style="height: 4px;">
                                <div class="progress-bar bg-brand-red" style="width: 30%"></div>
                            </div>
                        </div>
                        <div class="ms-3 h5 mb-0 fw-800 text-brand-red">30%</div>
                    </div>

                    <div class="d-flex align-items-center p-3 bg-white rounded-4 border border-light shadow-sm">
                        <div class="bg-primary text-white rounded-3 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fas fa-stopwatch"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="small fw-800 text-dark">Kedisiplinan</div>
                            <div class="progress mt-1 rounded-pill" style="height: 4px;">
                                <div class="progress-bar bg-primary" style="width: 30%"></div>
                            </div>
                        </div>
                        <div class="ms-3 h5 mb-0 fw-800 text-primary">30%</div>
                    </div>
                </div>

                <div class="p-3 bg-dark text-white rounded-4 shadow-lg">
                    <div class="small opacity-50 mb-1 fw-700 text-uppercase ls-1">Skor Akhir</div>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-equals me-2 opacity-50"></i>
                        <span class="fw-800 h4 mb-0"><?= number_format($latest['nilai_kpi_total'] ?? 0, 2) ?></span>
                        <div class="ms-auto small opacity-75">Sistem Terkalkulasi Digital</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../layout/footer.php'; ?>


