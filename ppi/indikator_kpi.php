<?php
require_once '../config/database.php';
session_start();
check_login();
check_permission('kpi.manage');


$title = "Kelola Indikator KPI";
include '../layout/header.php';
include '../layout/sidebar.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $nama = $_POST['nama_indikator'];
        $kategori = $_POST['kategori'];
        $stmt = $pdo->prepare("INSERT INTO tabel_indikator (nama_indikator, kategori) VALUES (?, ?)");
        if ($stmt->execute([$nama, $kategori])) {
            $message = "Indikator berhasil ditambahkan!";
        }
    } elseif (isset($_POST['delete'])) {
        $id = $_POST['id_indikator'];
        $stmt = $pdo->prepare("DELETE FROM tabel_indikator WHERE id_indikator = ?");
        $stmt->execute([$id]);
        $message = "Indikator berhasil dihapus!";
    }
}

$indicators = $pdo->query("SELECT * FROM tabel_indikator ORDER BY kategori, id_indikator ASC")->fetchAll();
?>

<div id="content" class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-brand-red mb-1">Indikator Penilaian KPI</h4>
            <p class="text-muted small">Kelola parameter penilaian sikap dan koordinasi pengurus.</p>
        </div>
        <button class="btn btn-primary d-flex align-items-center shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus-circle me-2"></i> Tambah Parameter
        </button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show d-flex align-items-center rounded-3 p-3 mb-4" role="alert">
            <i class="fas fa-check-circle fa-lg me-3"></i>
            <div><?= $message ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Attitude Card -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                <div class="card-header bg-white border-0 py-3 px-4 d-flex align-items-center">
                    <div class="stats-icon bg-rose-soft text-rose me-3" style="width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h6 class="mb-0 fw-800 text-dark">Kategori: Attitude & Perilaku</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush border-top border-light">
                        <?php $found_a = false; foreach($indicators as $i): if($i['kategori'] == 'attitude'): $found_a = true; ?>
                        <div class="list-group-item modern-row d-flex justify-content-between align-items-center py-3 px-4 border-light">
                            <span class="fw-600 text-dark-50"><?= $i['nama_indikator'] ?></span>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Hapus indikator ini dari kriteria penilaian?')">
                                <input type="hidden" name="id_indikator" value="<?= $i['id_indikator'] ?>">
                                <button type="submit" name="delete" class="btn btn-icon btn-light-soft text-danger">
                                    <i class="fas fa-trash-alt small"></i>
                                </button>
                            </form>
                        </div>
                        <?php endif; endforeach; ?>
                        <?php if(!$found_a): ?>
                            <div class="text-center py-5 opacity-50">
                                <i class="fas fa-list fa-2x mb-2"></i>
                                <p class="small fw-bold mb-0">Belum ada indikator ditambahkan.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                <div class="card-header bg-white border-0 py-3 px-4 d-flex align-items-center">
                    <div class="stats-icon bg-brand-green-soft text-brand-green me-3" style="width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h6 class="mb-0 fw-800 text-dark">Kategori: Komunikasi & Koordinasi</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush border-top border-light">
                        <?php $found_k = false; foreach($indicators as $i): if($i['kategori'] == 'komunikasi'): $found_k = true; ?>
                        <div class="list-group-item modern-row d-flex justify-content-between align-items-center py-3 px-4 border-light">
                            <span class="fw-600 text-dark-50"><?= $i['nama_indikator'] ?></span>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Hapus indikator ini dari kriteria penilaian?')">
                                <input type="hidden" name="id_indikator" value="<?= $i['id_indikator'] ?>">
                                <button type="submit" name="delete" class="btn btn-icon btn-light-soft text-danger">
                                    <i class="fas fa-trash-alt small"></i>
                                </button>
                            </form>
                        </div>
                        <?php endif; endforeach; ?>
                        <?php if(!$found_k): ?>
                            <div class="text-center py-5 opacity-50">
                                <i class="fas fa-list fa-2x mb-2"></i>
                                <p class="small fw-bold mb-0">Belum ada indikator ditambahkan.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1.5rem;">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="modal-title fw-800 text-dark">Tambah Indikator Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Pilih Kategori</label>
                        <select name="kategori" class="form-select form-select-lg bg-light border-0" required>
                            <option value="attitude">Sikap / Attitude (PPI)</option>
                            <option value="komunikasi">Komunikasi / Koordinasi (Biro/Divisi)</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Nama Indikator</label>
                        <input type="text" name="nama_indikator" class="form-control form-control-lg bg-light border-0" placeholder="Contoh: Disiplin waktu rapat" required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 fw-600" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add" class="btn btn-primary px-4 fw-800 shadow-sm">Simpan Indikator</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.bg-rose-soft { background-color: #F0FDF4; color: #16A34A; }
.text-rose { color: #16A34A; }
.bg-brand-green-soft { background-color: #FEF2F2; color: #DC2626; }
.text-brand-green { color: #DC2626; }
.btn-light-soft { background-color: #f1f5f9; border: none; }
.btn-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.btn-icon:hover { background-color: #fee2e2; }
.ls-1 { letter-spacing: 0.5px; }
.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.modern-row:hover { background-color: #f9fafb; }
.rounded-4 { border-radius: 1.25rem !important; }
</style>

<?php include '../layout/footer.php'; ?>

<?php include '../layout/footer.php'; ?>


