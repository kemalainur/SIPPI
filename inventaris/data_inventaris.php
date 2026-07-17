<?php
require_once '../config/database.php';
session_start();
check_permission('inventaris.view');

$title = "Kelola Inventaris";
include '../layout/header.php';
include '../layout/sidebar.php';

$message = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        check_permission('inventaris.create');
        $nama = $_POST['nama_barang'];
        $jumlah = $_POST['jumlah'];
        $kondisi = $_POST['kondisi'];
        $ket = $_POST['keterangan'];
        
        $stmt = $pdo->prepare("INSERT INTO tabel_inventaris (nama_barang, jumlah, kondisi, keterangan) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$nama, $jumlah, $kondisi, $ket])) {
            $message = "Barang berhasil ditambahkan!";
        }
    } elseif (isset($_POST['edit'])) {
        check_permission('inventaris.update');
        $id = $_POST['id_inventaris'];
        $nama = $_POST['nama_barang'];
        $jumlah = $_POST['jumlah'];
        $kondisi = $_POST['kondisi'];
        $ket = $_POST['keterangan'];
        
        $stmt = $pdo->prepare("UPDATE tabel_inventaris SET nama_barang = ?, jumlah = ?, kondisi = ?, keterangan = ? WHERE id_inventaris = ?");
        if ($stmt->execute([$nama, $jumlah, $kondisi, $ket, $id])) {
            $message = "Barang berhasil diperbarui!";
        }
    } elseif (isset($_POST['delete'])) {
        check_permission('inventaris.delete');
        $id = $_POST['id_inventaris'];
        
        $stmt = $pdo->prepare("DELETE FROM tabel_inventaris WHERE id_inventaris = ?");
        if ($stmt->execute([$id])) {
            $message = "Barang berhasil dihapus!";
        }
    }
}

// Search & Pagination Logic
$search = $_GET['q'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$params = [];
$countQuery = "SELECT COUNT(*) FROM tabel_inventaris WHERE 1=1";
$query = "SELECT * FROM tabel_inventaris WHERE 1=1";

if ($search !== '') {
    $countQuery .= " AND nama_barang LIKE ?";
    $query .= " AND nama_barang LIKE ?";
    $params[] = "%$search%";
}

$query .= " ORDER BY nama_barang ASC LIMIT $limit OFFSET $offset";

// Count total items
$stmtCount = $pdo->prepare($countQuery);
$stmtCount->execute($params);
$total_items = $stmtCount->fetchColumn();
$total_pages = ceil($total_items / $limit);

// Fetch items
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll();
?>

<div id="content" class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-brand-red mb-1">Inventaris Organisasi</h4>
        </div>
        <?php if (has_permission('inventaris.create')): ?>
        <button class="btn btn-primary d-flex align-items-center shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus-circle me-2"></i> Tambah Barang Baru
        </button>
        <?php endif; ?>
    </div>

    <!-- Search Form -->
    <div class="row mb-4 g-3 align-items-center">
        <div class="col-md-6">
            <form method="GET" class="d-flex gap-2">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0 bg-white" placeholder="Cari barang..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <button type="submit" class="btn btn-primary px-4 fw-bold">Cari</button>
                <?php if ($search !== ''): ?>
                    <a href="data_inventaris.php" class="btn btn-light border px-3 fw-bold text-muted d-flex align-items-center"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>
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
                            <?php if (has_permission('inventaris.update') || has_permission('inventaris.delete')): ?>
                            <th class="text-center pe-4" style="width: 150px;">Aksi</th>
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
                                    <div class="fw-800 text-dark"><?= htmlspecialchars($i['nama_barang']) ?></div>
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
                            <td><span class="text-muted small fw-500"><?= htmlspecialchars($i['keterangan'] ?? '') ?: '-' ?></span></td>
                            <?php if (has_permission('inventaris.update') || has_permission('inventaris.delete')): ?>
                            <td class="text-center pe-4">
                                <div class="d-flex justify-content-center gap-2">
                                    <?php if (has_permission('inventaris.update')): ?>
                                    <button class="btn btn-icon btn-light-soft text-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $i['id_inventaris'] ?>" title="Ubah Aset">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (has_permission('inventaris.delete')): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Hapus barang ini dari database inventaris?')">
                                        <input type="hidden" name="id_inventaris" value="<?= $i['id_inventaris'] ?>">
                                        <button type="submit" name="delete" class="btn btn-icon btn-light-soft text-danger" title="Hapus Aset">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
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

            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white border-0 p-4 d-flex justify-content-between align-items-center border-top">
                <span class="small text-muted fw-600">Menampilkan <?= count($items) ?> dari <?= $total_items ?> barang</span>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link px-3 py-2 rounded-start-pill" href="?q=<?= urlencode($search) ?>&page=<?= $page - 1 ?>">Sebelumnya</a>
                        </li>
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <li class="page-item <?= ($page == $p) ? 'active' : '' ?>">
                            <a class="page-link px-3 py-2" href="?q=<?= urlencode($search) ?>&page=<?= $p ?>"><?= $p ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link px-3 py-2 rounded-end-pill" href="?q=<?= urlencode($search) ?>&page=<?= $page + 1 ?>">Selanjutnya</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Add Modal -->
<?php if (has_permission('inventaris.create')): ?>
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
<?php endif; ?>

<!-- Edit Modals Definitions -->
<?php if (has_permission('inventaris.update') && $items): ?>
    <?php foreach($items as $i): ?>
    <div class="modal fade" id="editModal<?= $i['id_inventaris'] ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 1.5rem;">
                <form method="POST">
                    <div class="modal-header border-0 p-4 pb-0">
                        <h5 class="modal-title fw-800 text-dark">Ubah Data Aset</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="id_inventaris" value="<?= $i['id_inventaris'] ?>">
                        <div class="mb-3">
                            <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Nama Barang</label>
                            <input type="text" name="nama_barang" class="form-control form-control-lg bg-light border-0" value="<?= htmlspecialchars($i['nama_barang']) ?>" required>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Jumlah</label>
                                <input type="number" name="jumlah" class="form-control form-control-lg bg-light border-0" value="<?= $i['jumlah'] ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Kondisi</label>
                                <select name="kondisi" class="form-select form-select-lg bg-light border-0">
                                    <option value="Baik" <?= ($i['kondisi'] == 'Baik') ? 'selected' : '' ?>>Baik</option>
                                    <option value="Rusak Ringan" <?= ($i['kondisi'] == 'Rusak Ringan') ? 'selected' : '' ?>>Rusak Ringan</option>
                                    <option value="Rusak Berat" <?= ($i['kondisi'] == 'Rusak Berat') ? 'selected' : '' ?>>Rusak Berat</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Keterangan Tambahan</label>
                            <textarea name="keterangan" class="form-control bg-light border-0" rows="3"><?= htmlspecialchars($i['keterangan'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="button" class="btn btn-light px-4 fw-600" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="edit" class="btn btn-primary px-4 fw-800 shadow-sm">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

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