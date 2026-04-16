<?php
require_once '../config/database.php';
session_start();
check_login();

$title = "Kelola Inventaris";
include '../layout/header.php';
include '../layout/sidebar.php';

$message = '';
$role = $_SESSION['user']['nama_role'];

// Handle CRUD (Only Admin/Sekjend)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($role, ['Super Admin', 'Sekjend'])) {
    if (isset($_POST['add'])) {
        $nama = $_POST['nama_barang'];
        $jumlah = $_POST['jumlah'];
        $kondisi = $_POST['kondisi'];
        $ket = $_POST['keterangan'];
        $stmt = $pdo->prepare("INSERT INTO tabel_inventaris (nama_barang, jumlah, kondisi, keterangan) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$nama, $jumlah, $kondisi, $ket])) {
            $message = "Barang berhasil ditambahkan!";
        }
    } elseif (isset($_POST['delete'])) {
        $id = $_POST['id_inventaris'];
        $stmt = $pdo->prepare("DELETE FROM tabel_inventaris WHERE id_inventaris = ?");
        $stmt->execute([$id]);
        $message = "Barang berhasil dihapus!";
    }
}

$items = $pdo->query("SELECT * FROM tabel_inventaris ORDER BY nama_barang ASC")->fetchAll();
?>

<div id="content" class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-brand-red mb-1">Inventaris Organisasi</h4>
        </div>
        <?php if (in_array($role, ['Super Admin', 'Sekjend'])): ?>
        <button class="btn btn-primary d-flex align-items-center shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus-circle me-2"></i> Tambah Barang Baru
        </button>
        <?php endif; ?>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show d-flex align-items-center rounded-3 p-3 mb-4" role="alert">
            <i class="fas fa-check-circle fa-lg me-3"></i>
            <div><?= $message ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-slate-50 text-muted small text-uppercase fw-bold">
                        <tr>
                            <th class="ps-4 py-3">Nama Barang & Detail</th>
                            <th class="text-center">Jumlah Unit</th>
                            <th class="text-center">Status Kondisi</th>
                            <th>Keterangan Aset</th>
                            <?php if (in_array($role, ['Super Admin', 'Sekjend'])): ?>
                            <th class="text-center pe-4" style="width: 100px;">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($items): foreach($items as $i): ?>
                        <tr class="modern-row">
                            <td class="ps-4">
                                <div class="d-flex align-items-center py-2">
                                    <div class="avatar-sm me-3 bg-primary-soft text-primary rounded-3 d-flex align-items-center justify-content-center fw-800">
                                        <i class="fas fa-box-open small"></i>
                                    </div>
                                    <div class="fw-800 text-dark"><?= $i['nama_barang'] ?></div>
                                </div>
                            </td>
                            <td class="text-center fw-600 text-muted"><?= $i['jumlah'] ?> <span class="small opacity-50">PCS</span></td>
                            <td class="text-center">
                                <?php if($i['kondisi'] == 'Baik'): ?>
                                    <span class="badge bg-brand-green-soft text-brand-green px-3 py-1 rounded-pill small fw-bold">
                                        <i class="fas fa-check-circle me-1"></i>Baik
                                    </span>
                                <?php elseif($i['kondisi'] == 'Rusak Ringan'): ?>
                                    <span class="badge bg-warning-soft text-warning px-3 py-1 rounded-pill small fw-bold">
                                        <i class="fas fa-exclamation-circle me-1"></i>Rusak Ringan
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-soft text-danger px-3 py-1 rounded-pill small fw-bold">
                                        <i class="fas fa-times-circle me-1"></i>Rusak Berat
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><span class="text-muted small fw-500"><?= $i['keterangan'] ?: '-' ?></span></td>
                            <?php if (in_array($role, ['Super Admin', 'Sekjend'])): ?>
                            <td class="text-center pe-4">
                                <form method="POST" class="d-inline" onsubmit="return confirm('Hapus barang ini dari database inventaris?')">
                                    <input type="hidden" name="id_inventaris" value="<?= $i['id_inventaris'] ?>">
                                    <button type="submit" name="delete" class="btn btn-icon btn-light-soft text-danger" title="Hapus Aset">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="opacity-50 text-center w-100 py-4">
                                    <i class="fas fa-boxes fa-3x mb-3 text-muted"></i>
                                    <h6 class="text-muted fw-bold">Belum ada aset terdaftar di inventaris.</h6>
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

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1.5rem;">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="modal-title fw-800 text-dark">Tambah Aset Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Nama Barang</label>
                        <input type="text" name="nama_barang" class="form-control form-control-lg bg-light border-0" placeholder="Contoh: Printer EPSON L3110" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Jumlah</label>
                            <input type="number" name="jumlah" class="form-control form-control-lg bg-light border-0" placeholder="0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Kondisi</label>
                            <select name="kondisi" class="form-select form-select-lg bg-light border-0">
                                <option value="Baik">Baik</option>
                                <option value="Rusak Ringan">Rusak Ringan</option>
                                <option value="Rusak Berat">Rusak Berat</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Keterangan Tambahan</label>
                        <textarea name="keterangan" class="form-control bg-light border-0" rows="3" placeholder="Contoh: Lokasi penyimpanan, tahun beli, dll."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 fw-600" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add" class="btn btn-primary px-4 fw-800 shadow-sm">Simpan Inventaris</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.bg-slate-50 { background-color: #f8fafc; }
.bg-brand-green-soft { background-color: #FEF2F2; color: #DC2626; }
.text-brand-green { color: #DC2626; }
.bg-warning-soft { background-color: #fffbeb; color: #d97706; }
.text-warning { color: #d97706; }
.bg-primary-soft { background-color: rgba(220, 38, 38, 0.1); }
.bg-danger-soft { background-color: #F0FDF4; color: #16A34A; }
.btn-light-soft { background-color: #f1f5f9; border: none; }
.btn-icon { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
.avatar-sm { width: 32px; height: 32px; font-size: 0.9rem; }
.stats-icon { display: flex; align-items: center; justify-content: center; }
.ls-1 { letter-spacing: 0.5px; }
.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.modern-row:hover { background-color: #f9fafb; }
.rounded-4 { border-radius: 1.25rem !important; }
</style>

<?php include '../layout/footer.php'; ?>

<?php include '../layout/footer.php'; ?>


