<?php
require_once '../config/database.php';
session_start();
check_permission('permission.view');

$title = "Daftar Permission";
include '../layout/header.php';
include '../layout/sidebar.php';

$message = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        check_permission('permission.create');
        $name = $_POST['nama_permission'];
        $modul = $_POST['modul'];
        $aksi = $_POST['aksi'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO tabel_permission (nama_permission, modul, aksi) VALUES (?, ?, ?)");
            if ($stmt->execute([$name, $modul, $aksi])) {
                $message = "Permission baru berhasil didaftarkan!";
            }
        } catch (Exception $e) {
            $error_msg = "Gagal menambah permission: " . $e->getMessage();
        }
    } elseif (isset($_POST['edit'])) {
        check_permission('permission.update');
        $id = $_POST['id_permission'];
        $name = $_POST['nama_permission'];
        $modul = $_POST['modul'];
        $aksi = $_POST['aksi'];
        
        try {
            $stmt = $pdo->prepare("UPDATE tabel_permission SET nama_permission = ?, modul = ?, aksi = ? WHERE id_permission = ?");
            if ($stmt->execute([$name, $modul, $aksi, $id])) {
                $message = "Permission berhasil diperbarui!";
            }
        } catch (Exception $e) {
            $error_msg = "Gagal memperbarui: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete'])) {
        check_permission('permission.delete');
        $id = $_POST['id_permission'];
        
        $stmt = $pdo->prepare("DELETE FROM tabel_permission WHERE id_permission = ?");
        if ($stmt->execute([$id])) {
            $message = "Permission berhasil dihapus!";
        }
    }
}

// Search and Pagination Logic
$search = $_GET['q'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$params = [];
$countQuery = "SELECT COUNT(*) FROM tabel_permission WHERE 1=1";
$query = "SELECT * FROM tabel_permission WHERE 1=1";

if ($search !== '') {
    $countQuery .= " AND (nama_permission LIKE ? OR modul LIKE ?)";
    $query .= " AND (nama_permission LIKE ? OR modul LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY modul ASC, nama_permission ASC LIMIT $limit OFFSET $offset";

// Count total items
$stmtCount = $pdo->prepare($countQuery);
$stmtCount->execute($params);
$total_items = $stmtCount->fetchColumn();
$total_pages = ceil($total_items / $limit);

// Fetch items
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$permissions = $stmt->fetchAll();
?>

<div id="content" class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-brand-red mb-1">Daftar Sistem Permission</h4>
            <p class="text-muted small">Daftar hak akses granular untuk modul dan tindakan dalam sistem.</p>
        </div>
        <?php if (has_permission('permission.create')): ?>
        <button class="btn btn-primary d-flex align-items-center shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#addPermModal">
            <i class="fas fa-plus-circle me-2"></i> Tambah Permission Baru
        </button>
        <?php endif; ?>
    </div>

    <!-- Search Form -->
    <div class="row mb-4 g-3 align-items-center">
        <div class="col-md-6">
            <form method="GET" class="d-flex gap-2">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0 bg-white" placeholder="Cari permission / modul..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <button type="submit" class="btn btn-primary px-4 fw-bold">Cari</button>
                <?php if ($search !== ''): ?>
                    <a href="data_permission.php" class="btn btn-light border px-3 fw-bold text-muted d-flex align-items-center"><i class="fas fa-times"></i></a>
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

    <?php if ($error_msg): ?>
        <div class="alert alert-danger border-0 shadow-sm alert-dismissible fade show d-flex align-items-center rounded-3 p-3 mb-4" role="alert">
            <i class="fas fa-exclamation-triangle fa-lg me-3"></i>
            <div><?= $error_msg ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-slate-50 text-muted small text-uppercase fw-bold">
                        <tr>
                            <th width="80" class="ps-4 py-3">ID</th>
                            <th>Nama Kunci Permission</th>
                            <th>Modul</th>
                            <th>Aksi</th>
                            <?php if (has_permission('permission.update') || has_permission('permission.delete')): ?>
                            <th class="text-center pe-4" style="width: 150px;">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($permissions): foreach($permissions as $p): ?>
                        <tr class="modern-row">
                            <td class="ps-4 fw-600 text-muted">#<?= $p['id_permission'] ?></td>
                            <td>
                                <div class="fw-800 text-dark"><?= htmlspecialchars($p['nama_permission']) ?></div>
                            </td>
                            <td>
                                <span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill small fw-bold">
                                    <i class="fas fa-folder me-1"></i><?= htmlspecialchars($p['modul']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-muted border px-3 py-1 rounded-pill small fw-bold">
                                    <?= htmlspecialchars($p['aksi']) ?>
                                </span>
                            </td>
                            <?php if (has_permission('permission.update') || has_permission('permission.delete')): ?>
                            <td class="text-center pe-4">
                                <div class="d-flex justify-content-center gap-2">
                                    <?php if (has_permission('permission.update')): ?>
                                    <button class="btn btn-icon btn-light-soft text-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $p['id_permission'] ?>" title="Ubah">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (has_permission('permission.delete')): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Hapus permission ini? Tindakan ini akan mencabut hak akses ini dari seluruh role.')">
                                        <input type="hidden" name="id_permission" value="<?= $p['id_permission'] ?>">
                                        <button type="submit" name="delete" class="btn btn-icon btn-light-soft text-danger" title="Hapus">
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
                                    <i class="fas fa-key fa-3x mb-3 text-muted"></i>
                                    <h6 class="text-muted fw-bold">Belum ada data permission terdaftar.</h6>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white border-0 p-4 d-flex justify-content-between align-items-center border-top">
                <span class="small text-muted fw-600">Menampilkan <?= count($permissions) ?> dari <?= $total_items ?> permission</span>
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

<!-- Modal Definitions - MUST be placed outside table containers to avoid backdrop glitches -->

<!-- Add Modal -->
<div class="modal fade" id="addPermModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1.5rem;">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="modal-title fw-800 text-dark">Tambah Permission Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Nama Kunci Permission</label>
                        <input type="text" name="nama_permission" class="form-control form-control-lg bg-light border-0" placeholder="Contoh: pengurus.create" required>
                    </div>
                    <div class="row g-3 mb-0">
                        <div class="col-6">
                            <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Modul</label>
                            <input type="text" name="modul" class="form-control form-control-lg bg-light border-0" placeholder="Contoh: pengurus" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Aksi</label>
                            <input type="text" name="aksi" class="form-control form-control-lg bg-light border-0" placeholder="Contoh: create" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 fw-600" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add" class="btn btn-primary px-4 fw-800 shadow-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modals -->
<?php if (has_permission('permission.update')): ?>
    <?php foreach($permissions as $p): ?>
    <div class="modal fade" id="editModal<?= $p['id_permission'] ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 1.25rem;">
                <form method="POST">
                    <div class="modal-header border-0 p-4 pb-0">
                        <h5 class="modal-title fw-800 text-dark">Ubah Permission</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="id_permission" value="<?= $p['id_permission'] ?>">
                        <div class="mb-3">
                            <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Nama Permission</label>
                            <input type="text" name="nama_permission" class="form-control form-control-lg bg-light border-0" value="<?= htmlspecialchars($p['nama_permission']) ?>" required>
                        </div>
                        <div class="row g-3 mb-0">
                            <div class="col-6">
                                <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Modul</label>
                                <input type="text" name="modul" class="form-control form-control-lg bg-light border-0" value="<?= htmlspecialchars($p['modul']) ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Aksi</label>
                                <input type="text" name="aksi" class="form-control form-control-lg bg-light border-0" value="<?= htmlspecialchars($p['aksi']) ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="button" class="btn btn-light px-4 fw-600" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="edit" class="btn btn-primary px-4 fw-800 shadow-sm">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<style>
.bg-slate-50 { background-color: #f8fafc; }
.bg-primary-soft { background-color: rgba(220, 38, 38, 0.1); }
.btn-light-soft { background-color: #f1f5f9; border: none; }
.btn-icon { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
.avatar-sm { width: 32px; height: 32px; font-size: 0.9rem; }
.ls-1 { letter-spacing: 0.5px; }
.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.modern-row:hover { background-color: #f9fafb; }
.rounded-4 { border-radius: 1.25rem !important; }
</style>

<?php include '../layout/footer.php'; ?>
