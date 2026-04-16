<?php
require_once '../config/database.php';
session_start();
check_login();

$me = $_SESSION['user']['nokta'];
$active_p = get_active_kepengurusan();

if (!$active_p) {
    die("Error: Tidak ada Tahun Kepengurusan yang aktif.");
}

$title = "Rekap Kas Saya";
include '../layout/header.php';
include '../layout/sidebar.php';

// Fetch all monthly status for this user in this period
$stmtHistory = $pdo->prepare("SELECT v.*, k.status_bayar, k.tanggal_bayar, k.nominal as nominal_bayar
                             FROM tabel_kas_periode_wajib v
                             LEFT JOIN tabel_kas_pengurus k ON v.bulan = k.bulan AND v.tahun = k.tahun AND k.nokta_pengurus = ?
                             WHERE v.kepengurusan_id = ?
                             ORDER BY v.tahun ASC, v.bulan ASC");
$stmtHistory->execute([$me, $active_p['id_kepengurusan']]);
$history = $stmtHistory->fetchAll();

$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
?>

<div id="content" class="fade-in">
    <div class="mb-4 d-flex justify-content-between align-items-center g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-brand-red mb-1">Rekap Kas Saya</h4>
            <p class="text-muted small mb-0">Memantau riwayat iuran Anda pada periode <span class="fw-800 text-dark"><?= $active_p['nama_periode'] ?></span></p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-white shadow-sm border-light rounded-pill px-3 fw-800">
                <i class="fas fa-print me-2 text-muted"></i> Cetak Bukti
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <?php
        $totalPaid = 0;
        $totalOwed = 0;
        foreach($history as $h) {
            if(($h['status_bayar'] ?? '') == 'sudah') $totalPaid += $h['nominal_bayar'];
            else $totalOwed += $h['nominal'];
        }
        ?>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 bg-brand-red text-white">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0 fw-800 opacity-75 small text-uppercase ls-1">Total Terbayar</h6>
                        <i class="fas fa-wallet opacity-50"></i>
                    </div>
                    <h2 class="fw-800 mb-0">Rp <?= number_format($totalPaid, 0, ',', '.') ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white border-light border">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0 fw-800 text-muted small text-uppercase ls-1">Sisa Kewajiban</h6>
                        <i class="fas fa-hand-holding-usd text-primary opacity-50"></i>
                    </div>
                    <h2 class="fw-800 text-dark mb-0">Rp <?= number_format($totalOwed, 0, ',', '.') ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment List -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-slate-50 text-muted small text-uppercase fw-bold">
                        <tr>
                            <th class="ps-4 py-3">Bulan</th>
                            <th class="text-center">Wajib Bayar</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Tanggal Bayar</th>
                            <th class="text-end pe-4">Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($history): foreach($history as $h): 
                            $isPaid = ($h['status_bayar'] ?? '') == 'sudah';
                        ?>
                        <tr class="modern-row <?= $isPaid ? 'opacity-100' : 'opacity-75' ?>">
                            <td class="ps-4 py-3">
                                <div class="fw-800 text-dark"><?= $monthNames[$h['bulan']] ?></div>
                                <div class="text-muted small"><?= $h['tahun'] ?></div>
                            </td>
                            <td class="text-center">
                                <span class="fw-800 text-muted small">Rp <?= number_format($h['nominal'], 0, ',', '.') ?></span>
                            </td>
                            <td class="text-center">
                                <?php if($isPaid): ?>
                                    <span class="badge bg-brand-green-soft text-brand-green px-3 py-2 rounded-pill fw-bold">
                                        <i class="fas fa-check-circle me-1"></i> LUNAS
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-soft text-danger px-3 py-2 rounded-pill fw-bold">
                                        <i class="fas fa-clock me-1"></i> TUNGGAKAN
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="small fw-600 text-dark"><?= $isPaid ? date('d M Y', strtotime($h['tanggal_bayar'])) : '--' ?></span>
                            </td>
                            <td class="text-end pe-4">
                                <span class="fw-800 <?= $isPaid ? 'text-brand-green' : 'text-danger' ?>">
                                    Rp <?= number_format($isPaid ? $h['nominal_bayar'] : $h['nominal'], 0, ',', '.') ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <i class="fas fa-receipt fa-3x text-muted opacity-10 mb-3"></i>
                                <p class="text-muted small fw-600">Belum ada pengaturan kewajiban kas untuk periode ini.</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.bg-danger-soft { background-color: #F0FDF4; color: #16A34A; }
.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.ls-1 { letter-spacing: 0.5px; }
.rounded-4 { border-radius: 1.5rem !important; }
.bg-slate-50 { background-color: #f8fafc; }
.modern-row:hover { background-color: #fcfdfe; }
</style>

<?php include '../layout/footer.php'; ?>


