<?php
require_once '../config/database.php';
session_start();
check_login();
check_role('Super Admin');

$title = "Kelola Biro";
include '../layout/header.php';
include '../layout/sidebar.php';

$message = '';

// Fetch ACTIVE period
$active_p = get_active_kepengurusan();
if (!$active_p) {
    die("Error: Tidak ada Tahun Kepengurusan yang aktif. Silakan hubungi Super Admin.");
}

// Handle CRUD Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $nama_biro = $_POST['nama_biro'];
        $stmt = $pdo->prepare("INSERT INTO tabel_biro (nama_biro, kepengurusan_id) VALUES (?, ?)");
        if ($stmt->execute([$nama_biro, $active_p['id_kepengurusan']])) {
            $message = "Biro berhasil ditambahkan ke periode " . $active_p['nama_periode'];
        }
    } elseif (isset($_POST['edit'])) {
        $id_biro = $_POST['id_biro'];
        $nama_biro = $_POST['nama_biro'];
        $stmt = $pdo->prepare("UPDATE tabel_biro SET nama_biro = ? WHERE id_biro = ?");
        if ($stmt->execute([$nama_biro, $id_biro])) {
            $message = "Biro berhasil diperbarui!";
        }
    } elseif (isset($_POST['delete'])) {
        $id_biro = $_POST['id_biro'];
        $stmt = $pdo->prepare("DELETE FROM tabel_biro WHERE id_biro = ?");
        if ($stmt->execute([$id_biro])) {
            $message = "Biro berhasil dihapus!";
        }
    }
}

// Fetch Biros for ACTIVE PERIOD
$stmt = $pdo->prepare("SELECT * FROM tabel_biro WHERE kepengurusan_id = ? ORDER BY nama_biro ASC");
$stmt->execute([$active_p['id_kepengurusan']]);
$biros = $stmt->fetchAll();
?>

<div id="content" class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-800 text-brand-red mb-1">Manajemen Biro</h4>
            <p class="text-muted small">Periode Aktif: <span class="badge bg-primary px-2 rounded-pill"><?= $active_p['nama_periode'] ?></span></p>
        </div>
        <button class="btn btn-primary d-flex align-items-center shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus-circle me-2"></i> Tambah Biro Baru
        </button>
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
                            <th width="80" class="ps-4 py-3">No</th>
                            <th>Nama Biro Organisasi</th>
                            <th width="200" class="text-center pe-4">Kendali Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($biros): $n=1; foreach($biros as $b): ?>
                        <tr class="modern-row">
                            <td class="ps-4 fw-600 text-muted"><?= str_pad($n++, 2, '0', STR_PAD_LEFT) ?></td>
                            <td>
                                <div class="d-flex align-items-center py-2">
                                    <div class="avatar-sm me-3 bg-primary-soft text-primary rounded-3 d-flex align-items-center justify-content-center fw-800">
                                        <i class="fas fa-building small"></i>
                                    </div>
                                    <div class="fw-800 text-dark"><?= $b['nama_biro'] ?></div>
                                </div>
                            </td>
                            <td class="text-center pe-4">
                                <div class="d-flex justify-content-center gap-2">
                                    <button class="btn btn-icon btn-light-soft text-primary" 
                                            data-bs-toggle="modal" data-bs-target="#editModal<?= $b['id_biro'] ?>" title="Ubah Nama">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-icon btn-light-soft text-danger" 
                                            data-bs-toggle="modal" data-bs-target="#deleteModal<?= $b['id_biro'] ?>" title="Hapus Biro">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Edit Modal -->
                        <div class="modal fade" id="editModal<?= $b['id_biro'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow-lg" style="border-radius: 1.25rem;">
                                    <form method="POST">
                                        <div class="modal-header border-0 p-4 pb-0">
                                            <h5 class="modal-title fw-800 text-dark">Ubah Nama Biro</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <input type="hidden" name="id_biro" value="<?= $b['id_biro'] ?>">
                                            <div class="mb-0">
                                                <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Nama Biro</label>
                                                <input type="text" name="nama_biro" class="form-control form-control-lg bg-light border-0" value="<?= $b['nama_biro'] ?>" required>
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

                        <!-- Delete Modal -->
                        <div class="modal fade" id="deleteModal<?= $b['id_biro'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered modal-sm">
                                <div class="modal-content border-0 shadow-lg" style="border-radius: 1.25rem;">
                                    <form method="POST">
                                        <div class="modal-body p-4 text-center">
                                            <div class="stats-icon bg-danger-soft text-danger mx-auto mb-3" style="width: 60px; height: 60px; border-radius: 20px; font-size: 1.5rem;">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </div>
                                            <h5 class="fw-800 text-dark mb-2">Hapus Biro?</h5>
                                            <p class="text-muted small mb-0 text-center">Tindakan ini tidak dapat dibatalkan dan akan menghapus seluruh data yang terkait.</p>
                                            <input type="hidden" name="id_biro" value="<?= $b['id_biro'] ?>">
                                        </div>
                                        <div class="modal-footer border-0 p-4 pt-0 d-flex gap-2">
                                            <button type="button" class="btn btn-light flex-fill fw-600" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" name="delete" class="btn btn-danger flex-fill fw-800 shadow-sm">Ya, Hapus</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="3" class="text-center py-5">
                                <div class="py-4 opacity-50">
                                    <i class="fas fa-building fa-3x mb-3"></i>
                                    <p class="fw-bold">Belum ada data biro di kepengurusan ini.</p>
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
                    <h5 class="modal-title fw-800 text-dark">Tambah Biro Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-0">
                        <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Nama Biro Organisasi</label>
                        <input type="text" name="nama_biro" class="form-control form-control-lg bg-light border-0" placeholder="Contoh: Biro Infokom" required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 fw-600" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add" class="btn btn-primary px-4 fw-800 shadow-sm">Tambahkan Biro</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.bg-slate-50 { background-color: #f8fafc; }
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


