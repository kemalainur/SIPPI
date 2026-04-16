<?php
require_once '../config/database.php';
session_start();
check_login();
check_role(['Super Admin', 'Bendum', 'Sekjend', 'Koorkam', 'PPI', 'Kabiro', 'Kadiv', 'Staff']);

$title = "Laporan Kas Organisasi";
include '../layout/header.php';
include '../layout/sidebar.php';

// Calculate Today's Summary (Iuran + Umum)
$stmtIuran = $pdo->query("SELECT SUM(nominal) FROM tabel_kas_pengurus WHERE status_bayar = 'sudah'");
$totalIuran = $stmtIuran->fetchColumn() ?: 0;

$stmtUmum = $pdo->query("SELECT 
    SUM(CASE WHEN jenis = 'masuk' THEN jumlah ELSE 0 END) as total_masuk,
    SUM(CASE WHEN jenis = 'keluar' THEN jumlah ELSE 0 END) as total_keluar
    FROM tabel_kas_umum");
$umum = $stmtUmum->fetch();

$totalMasuk = $totalIuran + ($umum['total_masuk'] ?? 0);
$totalKeluar = $umum['total_keluar'] ?? 0;
$saldo = $totalMasuk - $totalKeluar;

// Fetch Combined Transactions (Iuran & Umum)
$queryTransactions = "
    (SELECT k.tanggal_bayar as tanggal, CONCAT('Penerimaan Kas: ', p.nama, ' (Bulan ', k.bulan, ')') as keterangan, 'masuk' as jenis, k.nominal as jumlah, 'iuran' as source
     FROM tabel_kas_pengurus k
     JOIN tabel_pengurus p ON k.nokta_pengurus = p.nokta
     WHERE k.status_bayar = 'sudah' AND k.nominal > 0)
    UNION ALL
    (SELECT tanggal, keterangan, jenis, jumlah, 'umum' as source
     FROM tabel_kas_umum)
    ORDER BY tanggal DESC
";
$transactions = $pdo->query($queryTransactions)->fetchAll();
?>

<div id="content" class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-brand-red">Laporan Kas</h4>
            <p class="text-muted small">Ringkasan arus kas masuk dan keluar.</p>
        </div>
        <?php if (in_array($_SESSION['user']['nama_role'], ['Bendum', 'Super Admin', 'PPI'])): ?>
            <div class="d-flex gap-2">
                <a href="kas_masuk.php" class="btn btn-primary shadow-sm btn-sm px-3">
                    <i class="fas fa-plus-circle me-1"></i> Kas Masuk
                </a>
                <a href="kas_keluar.php" class="btn btn-danger shadow-sm btn-sm px-3">
                    <i class="fas fa-minus-circle me-1"></i> Kas Keluar
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm overflow-hidden bg-gradient-emerald text-white">
                <div class="card-body p-4 position-relative">
                    <div class="d-flex align-items-center mb-2">
                        <div class="stats-icon bg-white bg-opacity-20 text-white me-3"
                            style="width: 32px; height: 32px; border-radius: 8px;">
                            <i class="fas fa-arrow-down small"></i>
                        </div>
                        <h6 class="text-white-50 fw-bold small text-uppercase mb-0 ls-1">Kas Masuk</h6>
                    </div>
                    <h3 class="fw-800 mb-0">Rp <?= number_format($totalMasuk, 0, ',', '.') ?></h3>
                    <div class="stats-bg-icon text-white opacity-10"><i class="fas fa-plus-circle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm overflow-hidden bg-gradient-rose text-white">
                <div class="card-body p-4 position-relative">
                    <div class="d-flex align-items-center mb-2">
                        <div class="stats-icon bg-white bg-opacity-20 text-white me-3"
                            style="width: 32px; height: 32px; border-radius: 8px;">
                            <i class="fas fa-arrow-up small"></i>
                        </div>
                        <h6 class="text-white-50 fw-bold small text-uppercase mb-0 ls-1">Kas Keluar</h6>
                    </div>
                    <h3 class="fw-800 mb-0">Rp <?= number_format($totalKeluar, 0, ',', '.') ?></h3>
                    <div class="stats-bg-icon text-white opacity-10"><i class="fas fa-minus-circle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm overflow-hidden bg-dark text-white shadow-lg"
                style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
                <div class="card-body p-4 position-relative">
                    <div class="d-flex align-items-center mb-2">
                        <div class="stats-icon bg-white bg-opacity-10 text-white me-3"
                            style="width: 32px; height: 32px; border-radius: 8px;">
                            <i class="fas fa-wallet small"></i>
                        </div>
                        <h6 class="text-white-50 fw-bold small text-uppercase mb-0 ls-1">Total</h6>
                    </div>
                    <h3 class="fw-800 mb-0 text-amber-400">Rp <?= number_format($saldo, 0, ',', '.') ?></h3>
                    <div class="stats-bg-icon text-white opacity-5"><i class="fas fa-coins"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header border-0 bg-white d-flex justify-content-between align-items-center py-3 px-4">
            <h5 class="mb-0 fw-800">Riwayat Transaksi</h5>
            <div class="dropdown">
                <button class="btn btn-light-soft btn-sm fw-bold dropdown-toggle" data-bs-toggle="dropdown">
                    Filter Waktu
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-slate-50 text-muted small text-uppercase fw-bold">
                        <tr>
                            <th class="ps-4 py-3">Waktu & Tanggal</th>
                            <th>Deskripsi Transaksi</th>
                            <th class="text-center">Status Arus</th>
                            <th class="text-end pe-4" style="width: 200px;">Jumlah Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($transactions):
                            foreach ($transactions as $t): ?>
                                <tr class="modern-row">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div
                                                class="avatar-sm me-3 <?= $t['jenis'] == 'masuk' ? 'bg-brand-green-soft text-brand-green' : 'bg-danger-soft text-danger' ?> rounded-3 d-flex align-items-center justify-content-center fw-bold">
                                                <?= date('d', strtotime($t['tanggal'])) ?>
                                            </div>
                                            <div class="small fw-600"><?= date('M Y', strtotime($t['tanggal'])) ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-600 text-dark small"><?= $t['keterangan'] ?></div>
                                        <div class="text-muted" style="font-size: 0.7rem;"><i
                                                class="far fa-clock me-1"></i>08:00 WIB</div>
                                    </td>
                                    <td class="text-center">
                                        <span
                                            class="badge <?= $t['jenis'] == 'masuk' ? 'bg-brand-green-soft text-brand-green' : 'bg-danger-soft text-danger' ?> px-3 py-1 rounded-pill small fw-bold">
                                            <i
                                                class="fas <?= $t['jenis'] == 'masuk' ? 'fa-caret-down' : 'fa-caret-up' ?> me-1"></i>
                                            <?= ucfirst($t['jenis']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <span class="fw-800 <?= $t['jenis'] == 'masuk' ? 'text-primary' : 'text-danger' ?>"
                                            style="font-size: 0.95rem;">
                                            <?= $t['jenis'] == 'masuk' ? '+' : '-' ?> Rp
                                            <?= number_format($t['jumlah'], 0, ',', '.') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-5">
                                    <div class="py-4 opacity-50">
                                        <i class="fas fa-receipt fa-3x mb-3"></i>
                                        <p class="fw-bold">Belum ada catatan transaksi kas.</p>
                                    </div>
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
    .bg-gradient-emerald {
        background: linear-gradient(135deg, #DC2626 0%, #10b981 100%);
    }

    .bg-gradient-rose {
        background: linear-gradient(135deg, #16A34A 0%, #f43f5e 100%);
    }

    .bg-brand-green-soft {
        background-color: #FEF2F2;
        color: #DC2626;
    }

    .text-brand-green {
        color: #DC2626;
    }

    .bg-danger-soft {
        background-color: #F0FDF4;
        color: #16A34A;
    }

    .btn-light-soft {
        background-color: #f1f5f9;
        border: none;
    }

    .avatar-sm {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        font-size: 0.85rem;
    }

    .stats-icon {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .stats-bg-icon {
        position: absolute;
        right: -10px;
        bottom: -20px;
        font-size: 5rem;
        color: rgba(0, 0, 0, 0.03);
        transform: rotate(-15deg);
    }

    .ls-1 {
        letter-spacing: 0.5px;
    }

    .fw-800 {
        font-weight: 800;
    }

    .fw-600 {
        font-weight: 600;
    }

    .modern-row:hover {
        background-color: #f9fafb;
    }

    .rounded-4 {
        border-radius: 1.25rem !important;
    }

    .text-amber-400 {
        color: #fbbf24 !important;
    }
</style>

<?php include '../layout/footer.php'; ?>