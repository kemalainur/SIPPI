<?php
require_once '../config/database.php';
session_start();
check_login();
check_role(['Super Admin', 'Bendum']);

// Handle Update Status
if (isset($_GET['toggle'])) {
    $nokta = $_GET['toggle'];
    $status = $_GET['status'];
    $bulan_url = (int)($_GET['bulan'] ?? date('m'));
    $tahun_url = $_GET['tahun'] ?? date('Y');
    
    // Fetch ACTIVE period again if not yet fetched (or fetch earlier)
    $active_p = get_active_kepengurusan();
    if (!$active_p) {
        die("Error: Tidak ada Tahun Kepengurusan yang aktif.");
    }

    // Get nominal from period settings
    $stmtNom = $pdo->prepare("SELECT nominal FROM tabel_kas_periode_wajib WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ?");
    $stmtNom->execute([$bulan_url, $tahun_url, $active_p['id_kepengurusan']]);
    $nominal = $stmtNom->fetchColumn() ?: 0;

    // Check if record exists
    $stmtCheck = $pdo->prepare("SELECT id FROM tabel_kas_pengurus WHERE nokta_pengurus = ? AND bulan = ? AND tahun = ?");
    $stmtCheck->execute([$nokta, $bulan_url, $tahun_url]);
    $exists = $stmtCheck->fetch();

    if ($exists) {
        $stmtUpdate = $pdo->prepare("UPDATE tabel_kas_pengurus SET status_bayar = ?, tanggal_bayar = ?, nominal = ? WHERE id = ?");
        $tgl = ($status == 'sudah') ? date('Y-m-d') : null;
        $stmtUpdate->execute([$status, $tgl, $nominal, $exists['id']]);
    } else {
        $stmtInsert = $pdo->prepare("INSERT INTO tabel_kas_pengurus (nokta_pengurus, bulan, tahun, status_bayar, tanggal_bayar, nominal) VALUES (?, ?, ?, ?, ?, ?)");
        $tgl = ($status == 'sudah') ? date('Y-m-d') : null;
        $stmtInsert->execute([$nokta, $bulan_url, $tahun_url, $status, $tgl, $nominal]);
    }

    // AUTOMATION: Update KPI for this member
    update_kpi_member($nokta, $bulan_url, $tahun_url, $active_p['id_kepengurusan']);

    header("Location: status_kas_pengurus.php?bulan=$bulan_url&tahun=$tahun_url");
    exit;
}

$title = "Status Kas Pengurus";
include '../layout/header.php';
include '../layout/sidebar.php';

$bulan = (int)($_GET['bulan'] ?? date('m'));
$tahun = $_GET['tahun'] ?? date('Y');

// Fetch ACTIVE period
$active_p = get_active_kepengurusan();
if (!$active_p) {
    die("Error: Tidak ada Tahun Kepengurusan yang aktif.");
}

// Fetch members for active period
$query = "SELECT p.nokta, p.nama, j.jabatan, k.status_bayar, k.tanggal_bayar, k.nominal
          FROM tabel_pengurus p 
          JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
          LEFT JOIN tabel_kas_pengurus k ON p.nokta = k.nokta_pengurus 
          AND k.bulan = ? AND k.tahun = ?
          ORDER BY p.nokta ASC";
$stmt = $pdo->prepare($query);
$stmt->execute([$active_p['id_kepengurusan'], $bulan, $tahun]);
$members = $stmt->fetchAll();

// Fetch defined mandatory periods for the filter
$stmtMand = $pdo->prepare("SELECT bulan, tahun FROM tabel_kas_periode_wajib WHERE kepengurusan_id = ? ORDER BY tahun DESC, bulan DESC");
$stmtMand->execute([$active_p['id_kepengurusan']]);
$mandatories = $stmtMand->fetchAll();

$indonesian_months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$is_mandatory = false;
$current_nominal = 0;
$stmtCheckMand = $pdo->prepare("SELECT nominal FROM tabel_kas_periode_wajib WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ?");
$stmtCheckMand->execute([$bulan, $tahun, $active_p['id_kepengurusan']]);
$mand_data = $stmtCheckMand->fetch();
if ($mand_data) {
    $is_mandatory = true;
    $current_nominal = $mand_data['nominal'];
}
?>

<div id="content" class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-brand-red mb-1">Status Pembayaran Kas</h4>
            <p class="text-muted small mb-0">Kelola iuran anggota untuk periode <span class="fw-800 text-dark"><?= $indonesian_months[$bulan] ?> <?= $tahun ?></span></p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <div class="dropdown">
                <button class="btn btn-light-soft border text-muted btn-sm fw-800 dropdown-toggle px-3 py-2" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-calendar-alt me-2"></i>Cek Periode Lain
                </button>
                <ul class="dropdown-menu shadow-lg border-0 rounded-3">
                    <li class="dropdown-header small text-uppercase ls-1 fw-800">Tagihan Terbit</li>
                    <?php foreach($mandatories as $ma): ?>
                    <li><a class="dropdown-item small <?= ($ma['bulan'] == $bulan && $ma['tahun'] == $tahun) ? 'active bg-primary' : '' ?> fw-600" href="?bulan=<?= $ma['bulan'] ?>&tahun=<?= $ma['tahun'] ?>">
                        <?= $indonesian_months[$ma['bulan']] ?> <?= $ma['tahun'] ?>
                    </a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <a href="pengatur_kas.php" class="btn btn-primary btn-sm px-3 py-2 fw-800 shadow-sm">
                <i class="fas fa-cog me-1"></i> Atur Tagihan
            </a>
        </div>
    </div>

    <!-- Stats Summary Ribbon -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="avatar-sm bg-primary-soft text-primary rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 42px; height: 42px;">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-600">Nominal Iuran</div>
                        <div class="fw-800 text-dark">Rp <?= number_format($current_nominal, 0, ',', '.') ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white text-brand-green">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="avatar-sm bg-brand-green-soft text-brand-green rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 42px; height: 42px;">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <?php 
                        $paid_count = count(array_filter($members, fn($m) => ($m['status_bayar'] ?? '') == 'sudah'));
                    ?>
                    <div>
                        <div class="text-muted small fw-600">Sudah Lunas</div>
                        <div class="fw-800 text-dark"><?= $paid_count ?> <span class="text-muted small fw-600">Pengurus</span></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white text-danger">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="avatar-sm bg-danger-soft text-danger rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 42px; height: 42px;">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-600">Belum Bayar</div>
                        <div class="fw-800 text-dark"><?= count($members) - $paid_count ?> <span class="text-muted small fw-600">Pengurus</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$is_mandatory): ?>
    <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 mb-4 d-flex align-items-center">
        <i class="fas fa-exclamation-circle fa-2x me-3"></i>
        <div>
            <h6 class="fw-800 mb-1">Periode Belum Diaktifkan</h6>
            <p class="small mb-0">Bulan <b><?= $indonesian_months[$bulan] ?> <?= $tahun ?></b> belum terdaftar sebagai periode wajib kas. Tombol aksi dinonaktifkan sementara.</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-slate-50 text-muted small text-uppercase fw-bold">
                        <tr>
                            <th class="ps-4 py-3">Nama Pengurus</th>
                            <th>Jabatan</th>
                            <th class="text-center">Status Bayar</th>
                            <th class="text-center">Tanggal Bayar</th>
                            <th class="text-center pe-4" style="width: 180px;">Aksi Cepat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($members): foreach($members as $m): 
                            $is_paid = ($m['status_bayar'] ?? '') == 'sudah';
                        ?>
                        <tr class="modern-row <?= $is_paid ? 'paid-row' : '' ?>">
                            <td class="ps-4">
                                <div class="d-flex align-items-center py-2">
                                    <div class="avatar-md me-3 bg-primary-soft text-primary rounded-circle d-flex align-items-center justify-content-center fw-800">
                                        <?= substr($m['nama'] ?? '-', 0, 1) ?>
                                    </div>
                                    <div>
                                        <div class="fw-800 text-dark mb-0"><?= $m['nama'] ?></div>
                                        <div class="text-muted small ls-1"><?= $m['nokta'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="text-muted small fw-600"><?= $m['jabatan'] ?? 'Anggota' ?></span>
                            </td>
                            <td class="text-center">
                                <?php if($is_paid): ?>
                                    <span class="badge bg-brand-green-soft text-brand-green px-3 py-2 rounded-pill fw-bold">
                                        <i class="fas fa-check-circle me-1"></i> LUNAS
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-soft text-danger px-3 py-2 rounded-pill fw-bold">
                                        <i class="fas fa-times-circle me-1"></i> BELUM BAYAR
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($m['tanggal_bayar']): ?>
                                    <div class="fw-600 small text-dark"><?= date('d M Y', strtotime($m['tanggal_bayar'])) ?></div>
                                <?php else: ?>
                                    <span class="text-muted opacity-25">--</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center pe-4">
                                <?php if ($is_mandatory): ?>
                                    <?php if($is_paid): ?>
                                        <a href="?toggle=<?= $m['nokta'] ?>&status=belum&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" 
                                           class="btn btn-light-soft text-muted btn-sm fw-800 rounded-pill px-3 py-2" title="Batalkan Pembayaran">
                                            <i class="fas fa-undo me-1"></i> Batal
                                        </a>
                                    <?php else: ?>
                                        <a href="?toggle=<?= $m['nokta'] ?>&status=sudah&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" 
                                           class="btn btn-brand-red-soft text-brand-green btn-sm fw-800 rounded-pill px-3 py-2 shadow-sm" title="Tandai Sudah Bayar">
                                            <i class="fas fa-check me-1"></i> Tandai Lunas
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="btn btn-light disabled btn-sm rounded-pill px-3 opacity-50"><i class="fas fa-lock me-1"></i> Terkunci</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="py-4">
                                    <i class="fas fa-users-slash fa-3x text-muted opacity-25 mb-3"></i>
                                    <h6 class="text-muted fw-bold">Belum ada pengurus di kepengurusan ini.</h6>
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
.bg-light-soft { background-color: #f8fafc; }
.bg-slate-50 { background-color: #f8fafc; }
.bg-primary-soft { background-color: rgba(220, 38, 38, 0.1); }
.bg-brand-green-soft { background-color: #FEF2F2; color: #DC2626; }
.text-brand-green { color: #DC2626; }
.bg-danger-soft { background-color: #F0FDF4; color: #16A34A; }
.btn-light-soft { background-color: #f1f5f9; border: none; }
.avatar-sm { width: 34px; height: 34px; border-radius: 8px; font-size: 0.8rem; }
.avatar-md { width: 40px; height: 40px; border-radius: 12px; font-size: 1rem; }
.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.modern-row:hover { background-color: #f9fafb; }
.paid-row { background-color: #fbfdfc; }
.ls-1 { letter-spacing: 0.5px; }
.rounded-4 { border-radius: 1.25rem !important; }
.dropdown-item.active { font-weight: 800; }
</style>

<?php include '../layout/footer.php'; ?>


