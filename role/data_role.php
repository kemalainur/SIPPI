<?php
require_once '../config/database.php';
session_start();
check_permission('role.view');

$title = "Manajemen Role";
include '../layout/header.php';
include '../layout/sidebar.php';

$message = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_role'])) {
        check_permission('role.create');
        $nama_role = $_POST['nama_role'];
        $stmt = $pdo->prepare("INSERT INTO tabel_role (nama_role) VALUES (?)");
        if ($stmt->execute([$nama_role])) {
            $message = "Role baru berhasil ditambahkan!";
        }
    } elseif (isset($_POST['edit_role'])) {
        check_permission('role.update');
        $id_role = $_POST['id_role'];
        $nama_role = $_POST['nama_role'];
        $stmt = $pdo->prepare("UPDATE tabel_role SET nama_role = ? WHERE id_role = ?");
        if ($stmt->execute([$nama_role, $id_role])) {
            $message = "Nama role berhasil diperbarui!";
        }
    } elseif (isset($_POST['delete_role'])) {
        check_permission('role.delete');
        $id_role = $_POST['id_role'];
        
        // Prevent deleting core system roles
        if (in_array($id_role, [1, 2])) {
            $error_msg = "Role sistem bawaan (Super Admin / Sekjend) tidak boleh dihapus.";
        } else {
            // Check if there are users with this role
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM tabel_pengurus_jabatan WHERE role_id = ?");
            $stmtCheck->execute([$id_role]);
            if ($stmtCheck->fetchColumn() > 0) {
                $error_msg = "Role tidak dapat dihapus karena masih digunakan oleh pengurus.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM tabel_role WHERE id_role = ?");
                if ($stmt->execute([$id_role])) {
                    $message = "Role berhasil dihapus!";
                }
            }
        }
    } elseif (isset($_POST['save_permissions'])) {
        check_permission('role.update');
        $id_role = $_POST['id_role'];
        $permissions = $_POST['permissions'] ?? [];
        
        // Clear existing permissions
        $stmtClear = $pdo->prepare("DELETE FROM tabel_role_permission WHERE role_id = ?");
        $stmtClear->execute([$id_role]);
        
        // Insert new permissions
        if (!empty($permissions)) {
            $stmtInsert = $pdo->prepare("INSERT INTO tabel_role_permission (role_id, permission_id) VALUES (?, ?)");
            foreach ($permissions as $pid) {
                $stmtInsert->execute([$id_role, $pid]);
            }
        }
        $message = "Hak akses role berhasil diperbarui!";
    }
}

// Fetch all roles & permissions
$roles = $pdo->query("SELECT * FROM tabel_role ORDER BY id_role ASC")->fetchAll();
$all_permissions = $pdo->query("SELECT * FROM tabel_permission ORDER BY modul ASC, nama_permission ASC")->fetchAll();

$grouped_permissions = [];
foreach ($all_permissions as $p) {
    $grouped_permissions[$p['modul']][] = $p;
}

$role_permissions_map = [];
$role_perms_raw = $pdo->query("SELECT role_id, permission_id FROM tabel_role_permission")->fetchAll();
foreach ($role_perms_raw as $rp) {
    $role_permissions_map[$rp['role_id']][] = $rp['permission_id'];
}
?>

<div id="content" class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-brand-red mb-1">Manajemen Role & Hak Akses</h4>
            <p class="text-muted small">Atur tingkatan level hak akses secara dinamis dan aman.</p>
        </div>
        <?php if (has_permission('role.create')): ?>
        <button class="btn btn-primary d-flex align-items-center shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#addRoleModal">
            <i class="fas fa-plus-circle me-2"></i> Tambah Role Baru
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
                            <th>Nama Role Tingkatan</th>
                            <th class="text-center">Jumlah Permission</th>
                            <th class="text-center pe-4" style="width: 250px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($roles as $r): 
                            $assigned_perms = $role_permissions_map[$r['id_role']] ?? [];
                        ?>
                        <tr class="modern-row">
                            <td class="ps-4 fw-600 text-muted">#<?= $r['id_role'] ?></td>
                            <td>
                                <div class="d-flex align-items-center py-2">
                                    <div class="avatar-sm me-3 bg-primary-soft text-primary rounded-3 d-flex align-items-center justify-content-center fw-800">
                                        <i class="fas fa-user-shield small"></i>
                                    </div>
                                    <div class="fw-800 text-dark"><?= htmlspecialchars($r['nama_role']) ?></div>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-primary border px-3 py-1 rounded-pill small fw-bold">
                                    <?= count($assigned_perms) ?> Aktif
                                </span>
                            </td>
                            <td class="text-center pe-4">
                                <div class="d-flex justify-content-center gap-2">
                                    <?php if (has_permission('role.update')): ?>
                                    <button class="btn btn-sm btn-outline-primary px-3 rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#permModal<?= $r['id_role'] ?>">
                                        <i class="fas fa-key me-1"></i> Hak Akses
                                    </button>
                                    <button class="btn btn-icon btn-light-soft text-primary" data-bs-toggle="modal" data-bs-target="#editRoleModal<?= $r['id_role'] ?>" title="Ubah Nama">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (has_permission('role.delete') && !in_array($r['id_role'], [1, 2])): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Hapus role ini? Pengurus yang menggunakan role ini akan kehilangan akses.')">
                                        <input type="hidden" name="id_role" value="<?= $r['id_role'] ?>">
                                        <button type="submit" name="delete_role" class="btn btn-icon btn-light-soft text-danger" title="Hapus Role">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Definitions - MUST be placed outside table containers to avoid backdrop glitches -->

<!-- Add Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1.5rem;">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="modal-title fw-800 text-dark">Tambah Role Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-0">
                        <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Nama Role</label>
                        <input type="text" name="nama_role" class="form-control form-control-lg bg-light border-0" placeholder="Contoh: Humas" required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 fw-600" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add_role" class="btn btn-primary px-4 fw-800 shadow-sm">Simpan Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach($roles as $r): 
    $assigned_perms = $role_permissions_map[$r['id_role']] ?? [];
?>
<!-- Edit Name Modal -->
<div class="modal fade" id="editRoleModal<?= $r['id_role'] ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1.25rem;">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="modal-title fw-800 text-dark">Ubah Nama Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id_role" value="<?= $r['id_role'] ?>">
                    <div class="mb-0">
                        <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Nama Role</label>
                        <input type="text" name="nama_role" class="form-control form-control-lg bg-light border-0" value="<?= htmlspecialchars($r['nama_role']) ?>" required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 fw-600" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit_role" class="btn btn-primary px-4 fw-800 shadow-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Manage Permissions Modal -->
<div class="modal fade" id="permModal<?= $r['id_role'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1.5rem;">
            <form method="POST" style="display:flex;flex-direction:column;overflow:hidden;max-height:inherit;">
                <div class="modal-header border-0 p-4 pb-0">
                    <div>
                        <h5 class="modal-title fw-800 text-dark">Mengatur Hak Akses</h5>
                        <p class="text-muted small mb-0">Role: <span class="text-primary fw-bold"><?= htmlspecialchars($r['nama_role']) ?></span></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id_role" value="<?= $r['id_role'] ?>">
                    
                    <?php if (empty($grouped_permissions)): ?>
                        <p class="text-center text-muted">Belum ada daftar permission yang dibuat.</p>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($grouped_permissions as $module => $perms): ?>
                                <div class="col-md-6">
                                    <div class="card border-light shadow-none bg-light p-3 rounded-4 h-100">
                                        <h6 class="fw-800 text-muted small text-uppercase mb-3 ls-1 border-bottom pb-2">
                                            <i class="fas fa-folder me-2 text-primary"></i>Modul: <?= htmlspecialchars(ucfirst($module)) ?>
                                        </h6>
                                        <div class="d-flex flex-column gap-2">
                                            <?php foreach ($perms as $p): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= $p['id_permission'] ?>" id="perm_<?= $r['id_role'] ?>_<?= $p['id_permission'] ?>" <?= in_array($p['id_permission'], $assigned_perms) ? 'checked' : '' ?>>
                                                    <label class="form-check-label small fw-600 text-dark" for="perm_<?= $r['id_role'] ?>_<?= $p['id_permission'] ?>">
                                                        <?= htmlspecialchars($p['nama_permission']) ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 fw-600" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="save_permissions" class="btn btn-primary px-4 fw-800 shadow-sm">Simpan Hak Akses</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

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
