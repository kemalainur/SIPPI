<?php
require_once '../config/database.php';
session_start();
check_login();
check_role(['Super Admin', 'Bendum', 'PPI']);

$title = "Pengaturan Kas Baru";
include '../layout/header.php';
include '../layout/sidebar.php';

$message = '';
$active_p = get_active_kepengurusan();

if (!$active_p) {
    die("Error: Tidak ada Tahun Kepengurusan yang aktif.");
}

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_periode'])) {
        $bulan = $_POST['bulan'];
        $tahun = $_POST['tahun'];
        $nominal = $_POST['nominal'];

        try {
            $stmt = $pdo->prepare("INSERT INTO tabel_kas_periode_wajib (kepengurusan_id, bulan, tahun, nominal) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$active_p['id_kepengurusan'], $bulan, $tahun, $nominal])) {
                $message = "Periode kas berhasil ditambahkan!";
            }
        } catch (PDOException $e) {
            $message = "Gagal: Periode ini mungkin sudah ada.";
        }
    } elseif (isset($_POST['delete_periode'])) {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM tabel_kas_periode_wajib WHERE id = ?");
        if ($stmt->execute([$id])) {
            $message = "Periode kas berhasil dihapus.";
        }
    }
}

// Fetch mandatory periods
$stmt = $pdo->prepare("SELECT * FROM tabel_kas_periode_wajib WHERE kepengurusan_id = ? ORDER BY tahun DESC, bulan DESC");
$stmt->execute([$active_p['id_kepengurusan']]);
$mandatories = $stmt->fetchAll();

$indonesian_months = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
];
?>

<div id="content" class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-brand-red mb-1">Konfigurasi Tagihan Kas</h4>
            <p class="text-muted small mb-0">Atur kewajiban iuran bulanan untuk seluruh pengurus periode <span
                    class="badge bg-primary-soft text-primary px-2 rounded-pill"><?= $active_p['nama_periode'] ?></span>.
            </p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show d-flex align-items-center rounded-3 p-3 mb-4"
            role="alert">
            <i class="fas fa-check-circle fa-lg me-3"></i>
            <div><?= $message ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Form Tagihan Baru -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-0 py-4 px-4 border-bottom border-light">
                    <h6 class="mb-0 fw-800 text-dark"><i class="fas fa-calendar-plus text-primary me-2"></i>Buka Tagihan
                        Baru</h6>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label small fw-800 text-muted text-uppercase ls-1">Bulan
                                Penilaian</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i
                                        class="fas fa-calendar-alt text-muted"></i></span>
                                <select name="bulan" class="form-select form-select-lg bg-light border-0" required>
                                    <?php foreach ($indonesian_months as $m => $name): ?>
                                        <option value="<?= $m ?>" <?= $m == date('n') ? 'selected' : '' ?>><?= $name ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-800 text-muted text-uppercase ls-1">Tahun Berjalan</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i
                                        class="fas fa-history text-muted"></i></span>
                                <input type="number" name="tahun" value="<?= date('Y') ?>"
                                    class="form-control form-control-lg bg-light border-0" required>
                            </div>
                        </div>
                        <div class="mb-5">
                            <label class="form-label small fw-800 text-muted text-uppercase ls-1">Nominal Iuran
                                (Rp)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0 fw-800 text-muted">Rp</span>
                                <input type="number" name="nominal" value="10000"
                                    class="form-control form-control-lg bg-light border-0 fw-800" placeholder="10.000"
                                    required>
                            </div>
                        </div>
                        <button type="submit" name="add_periode"
                            class="btn btn-primary w-100 fw-800 py-3 rounded-pill shadow-lg mt-2">
                            <i class="fas fa-check-circle me-2"></i> Aktifkan Tagihan
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Daftar Tagihan Aktif -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                <div class="card-header bg-white border-0 py-4 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-800 text-dark">Daftar Tagihan Terbit</h6>
                    <span class="badge bg-slate-100 text-slate-600 px-3 py-2 rounded-pill fw-bold small">Total:
                        <?= count($mandatories) ?> Periode</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-slate-50 text-muted small text-uppercase fw-bold">
                                <tr>
                                    <th class="ps-4 py-3">Bulan & Tahun</th>
                                    <th>Nominal Tagihan</th>
                                    <th class="text-center pe-4" style="width: 120px;">Kendali</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($mandatories):
                                    foreach ($mandatories as $m): ?>
                                        <tr class="modern-row">
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center py-2">
                                                    <div
                                                        class="avatar-sm me-3 bg-brand-green-soft text-brand-green rounded-3 d-flex align-items-center justify-content-center fw-800">
                                                        <?= substr($indonesian_months[$m['bulan']], 0, 3) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-800 text-dark mb-0">
                                                            <?= $indonesian_months[$m['bulan']] ?></div>
                                                        <div class="text-muted small ls-1">Tahun <?= $m['tahun'] ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-800 text-primary">Rp
                                                    <?= number_format($m['nominal'], 0, ',', '.') ?></div>
                                                <div class="text-muted small" style="font-size: 0.65rem;">Wajib bayar seluruh
                                                    pengurus</div>
                                            </td>
                                            <td class="text-center pe-4">
                                                <form method="POST" class="d-inline"
                                                    onsubmit="return confirm('Hapus tagihan ini? Data pembayaran yang sudah diinput tidak akan hilang namun tidak akan dihitung di KPI.')">
                                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                                    <button type="submit" name="delete_periode"
                                                        class="btn btn-icon btn-light-soft text-danger" title="Hapus Tagihan">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-5">
                                            <div class="py-4">
                                                <i class="fas fa-calendar-times fa-3x text-muted opacity-25 mb-3"></i>
                                                <h6 class="text-muted fw-bold">Belum ada tagihan kas yang diatur.</h6>
                                                <p class="text-muted small">Mulai dengan menambahkan periode tagihan di
                                                    sebelah kiri.</p>
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
    </div>
</div>

<style>
    .bg-light {
        background-color: #f8fafc !important;
    }

    .bg-slate-50 {
        background-color: #f8fafc;
    }

    .bg-slate-100 {
        background-color: #f1f5f9;
    }

    .text-slate-600 {
        color: #475569;
    }

    .bg-primary-soft {
        background-color: rgba(220, 38, 38, 0.1);
    }

    .bg-brand-green-soft {
        background-color: #FEF2F2;
        color: #DC2626;
    }

    .text-brand-green {
        color: #DC2626;
    }

    .btn-light-soft {
        background-color: #f1f5f9;
        border: none;
    }

    .btn-icon {
        width: 32px;
        height: 32px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        font-size: 0.9rem;
    }

    .avatar-sm {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        font-size: 0.75rem;
    }

    .fw-800 {
        font-weight: 800;
    }

    .modern-row:hover {
        background-color: #f9fafb;
    }

    .ls-1 {
        letter-spacing: 0.5px;
    }

    .rounded-4 {
        border-radius: 1.25rem !important;
    }

    .form-control-lg,
    .form-select-lg {
        font-size: 0.95rem;
        font-weight: 600;
        padding: 0.9rem 1.25rem;
        border-radius: 12px;
    }

    .text-info-soft {
        color: #0891b2;
        font-weight: 600;
    }

    .input-group-text {
        border-top-left-radius: 12px !important;
        border-bottom-left-radius: 12px !important;
    }
</style>

<?php include '../layout/footer.php'; ?>