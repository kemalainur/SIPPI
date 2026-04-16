<?php
require_once '../config/database.php';
session_start();
check_login();
check_role(['Super Admin', 'Bendum', 'PPI']);

$title = "Input Kas Keluar";
include '../layout/header.php';
include '../layout/sidebar.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal = $_POST['tanggal'];
    $keterangan = $_POST['keterangan'];
    $jumlah = $_POST['jumlah'];

    $stmt = $pdo->prepare("INSERT INTO tabel_kas_umum (tanggal, keterangan, jenis, jumlah) VALUES (?, ?, 'keluar', ?)");
    if ($stmt->execute([$tanggal, $keterangan, $jumlah])) {
        $success = "Data kas keluar berhasil disimpan!";
    } else {
        $error = "Terjadi kesalahan saat menyimpan data.";
    }
}
?>

<div id="content" class="fade-in">
    <div class="mb-4 d-flex justify-content-between align-items-center g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-sippi-red mb-1">Catat Kas Keluar</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small mb-0">
                    <li class="breadcrumb-item"><a href="laporan_kas.php" class="text-primary text-decoration-none fw-600">Laporan Kas</a></li>
                    <li class="breadcrumb-item active fw-600 text-danger">Entri Kas Keluar</li>
                </ol>
            </nav>
        </div>
        <a href="laporan_kas.php" class="btn btn-light-soft text-muted px-4 fw-600 shadow-sm border">
            <i class="fas fa-arrow-left me-2"></i> Kembali
        </a>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success border-0 shadow-sm rounded-3 p-3 mb-4 d-flex align-items-center" role="alert">
        <i class="fas fa-check-circle fa-lg me-3"></i>
        <div><?= $success ?></div>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-header bg-white border-0 py-4 px-4 border-bottom border-light">
            <h6 class="mb-0 fw-800 text-dark"><i class="fas fa-minus-circle text-danger me-2"></i>Informasi Pengeluaran Dana</h6>
        </div>
        <div class="card-body p-4 p-lg-5">
            <form method="POST">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-800 text-muted text-uppercase ls-1">Tanggal Transaksi</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="fas fa-calendar-alt text-muted"></i></span>
                            <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" class="form-control form-control-lg bg-light border-0" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-800 text-muted text-uppercase ls-1">Jumlah Nominal (Rp)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0 fw-800 text-muted">Rp</span>
                            <input type="number" name="jumlah" class="form-control form-control-lg bg-light border-0 fw-800" placeholder="0" required>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-800 text-muted text-uppercase ls-1">Keterangan / Keperluan</label>
                        <textarea name="keterangan" class="form-control form-control-lg bg-light border-0" rows="4" placeholder="Contoh: Pembelian atribut organisasi atau konsumsi rapat..." required></textarea>
                    </div>
                </div>

                <div class="mt-5 pt-4 d-flex gap-3 justify-content-end border-top border-light">
                    <a href="laporan_kas.php" class="btn btn-light px-5 fw-800 rounded-pill py-3">Batal</a>
                    <button type="submit" class="btn btn-danger px-5 fw-800 rounded-pill py-3 shadow-lg">
                        <i class="fas fa-save me-2"></i> Simpan Transaksi Keluar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.bg-light { background-color: #f8fafc !important; }
.btn-light-soft { background-color: #f1f5f9; border: none; }
.ls-1 { letter-spacing: 0.5px; }
.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.rounded-4 { border-radius: 1.5rem !important; }
.form-control-lg { font-size: 0.95rem; font-weight: 600; padding: 0.9rem 1.25rem; border-radius: 12px; }
.input-group-text { border-top-left-radius: 12px !important; border-bottom-left-radius: 12px !important; }
</style>

<?php include '../layout/footer.php'; ?>


