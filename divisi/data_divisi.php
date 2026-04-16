<?php
require_once '../config/database.php';
session_start();
check_login();
check_role('Super Admin');

$title = "Kelola Divisi";
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
        $nama_divisi = $_POST['nama_divisi'];
        $biro_id = $_POST['biro_id'];
        $stmt = $pdo->prepare("INSERT INTO tabel_divisi (nama_divisi, biro_id, kepengurusan_id) VALUES (?, ?, ?)");
        if ($stmt->execute([$nama_divisi, $biro_id, $active_p['id_kepengurusan']])) {
            $message = "Divisi berhasil ditambahkan ke periode " . $active_p['nama_periode'];
        }
    } elseif (isset($_POST['edit'])) {
        $id_divisi = $_POST['id_divisi'];
        $nama_divisi = $_POST['nama_divisi'];
        $biro_id = $_POST['biro_id'];
        $stmt = $pdo->prepare("UPDATE tabel_divisi SET nama_divisi = ?, biro_id = ? WHERE id_divisi = ?");
        if ($stmt->execute([$nama_divisi, $biro_id, $id_divisi])) {
            $message = "Divisi berhasil diperbarui!";
        }
    } elseif (isset($_POST['delete'])) {
        $id_divisi = $_POST['id_divisi'];
        $stmt = $pdo->prepare("DELETE FROM tabel_divisi WHERE id_divisi = ?");
        if ($stmt->execute([$id_divisi])) {
            $message = "Divisi berhasil dihapus!";
        }
    }
}

// Fetch Divisi for ACTIVE PERIOD
$stmt = $pdo->prepare("SELECT d.*, b.nama_biro 
                       FROM tabel_divisi d 
                       JOIN tabel_biro b ON d.biro_id = b.id_biro 
                       WHERE d.kepengurusan_id = ? 
                       ORDER BY b.nama_biro, d.nama_divisi ASC");
$stmt->execute([$active_p['id_kepengurusan']]);
$divisis = $stmt->fetchAll();

// Fetch Biros for Dropdown (Filtered by period)
$stmtBiro = $pdo->prepare("SELECT * FROM tabel_biro WHERE kepengurusan_id = ? ORDER BY nama_biro ASC");
$stmtBiro->execute([$active_p['id_kepengurusan']]);
$biros = $stmtBiro->fetchAll();
?>

<div id="content" class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-brand-red mb-1">Manajemen Divisi</h4>
            <p class="text-muted small">Hubungkan unit teknis divisi ke biro dalam periode <span class="badge bg-primary px-2 rounded-pill"><?= $active_p['nama_periode'] ?></span>.</p>
        </div>
        <button class="btn btn-primary d-flex align-items-center shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus-circle me-2"></i> Tambah Divisi Baru
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
                            <th>Nama Divisi</th>
                            <th>Struktur Biro</th>
                            <th width="150" class="text-center pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($divisis): $n=1; foreach($divisis as $d): ?>
                        <tr class="modern-row">
                            <td class="ps-4 fw-600 text-muted"><?= str_pad($n++, 2, '0', STR_PAD_LEFT) ?></td>
                            <td>
                                <div class="d-flex align-items-center py-2">
                                    <div class="avatar-sm me-3 bg-brand-green-soft text-brand-green rounded-3 d-flex align-items-center justify-content-center fw-800">
                                        <i class="fas fa-sitemap small"></i>
                                    </div>
                                    <div class="fw-800 text-dark"><?= $d['nama_divisi'] ?></div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill small fw-bold">
                                    <i class="fas fa-building me-1"></i><?= $d['nama_biro'] ?>
                                </span>
                            </td>
                            <td class="text-center pe-4">
                                <div class="d-flex justify-content-center gap-1">
                                    <button class="btn btn-icon btn-light-soft text-primary" 
                                            data-bs-toggle="modal" data-bs-target="#editModal<?= $d['id_divisi'] ?>" title="Ubah Data">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-icon btn-light-soft text-danger" 
                                            data-bs-toggle="modal" data-bs-target="#deleteModal<?= $d['id_divisi'] ?>" title="Hapus">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Edit Modal -->
                        <div class="modal fade" id="editModal<?= $d['id_divisi'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow-lg" style="border-radius: 1.25rem;">
                                    <form method="POST">
                                        <div class="modal-header border-0 p-4 pb-0">
                                            <h5 class="modal-title fw-800 text-dark">Ubah Divisi</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <input type="hidden" name="id_divisi" value="<?= $d['id_divisi'] ?>">
                                            <div class="mb-3">
                                                <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Nama Divisi</label>
                                                <input type="text" name="nama_divisi" class="form-control form-control-lg bg-light border-0" value="<?= $d['nama_divisi'] ?>" required>
                                            </div>
                                            <div class="mb-0">
                                                <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Pilih Biro</label>
                                                <select name="biro_id" class="form-select bg-light border-0" required>
                                                    <?php foreach($biros as $b): ?>
                                                    <option value="<?= $b['id_biro'] ?>" <?= ($b['id_biro'] == $d['biro_id']) ? 'selected' : '' ?>><?= $b['nama_biro'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
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
                        <div class="modal fade" id="deleteModal<?= $d['id_divisi'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-sm modal-dialog-centered">
                                <div class="modal-content border-0 shadow-lg" style="border-radius: 1.25rem;">
                                    <form method="POST">
                                        <div class="modal-body p-4 text-center">
                                            <div class="stats-icon bg-danger-soft text-danger mx-auto mb-3" style="width: 60px; height: 60px; border-radius: 20px; font-size: 1.5rem;">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </div>
                                            <h5 class="fw-800 text-dark mb-2">Hapus Divisi?</h5>
                                            <p class="text-muted small mb-0 text-center">Tindakan ini tidak dapat dibatalkan.</p>
                                            <input type="hidden" name="id_divisi" value="<?= $d['id_divisi'] ?>">
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
                            <td colspan="4" class="text-center py-5">
                                <div class="opacity-50 text-center w-100 py-4">
                                    <i class="fas fa-sitemap fa-3x mb-3"></i>
                                    <h6 class="fw-bold">Belum ada data divisi terdaftar.</h6>
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
                    <h5 class="modal-title fw-800 text-dark">Tambah Divisi Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Nama Divisi</label>
                        <input type="text" name="nama_divisi" class="form-control form-control-lg bg-light border-0" placeholder="Contoh: Media Kreatif" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-800 text-muted ls-1 text-uppercase">Pilih Biro</label>
                        <select name="biro_id" class="form-select bg-light border-0" required>
                            <option value="">-- Pilih Biro Induk --</option>
                            <?php foreach($biros as $b): ?>
                                <option value="<?= $b['id_biro'] ?>"><?= $b['nama_biro'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 fw-600" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add" class="btn btn-primary px-4 fw-800 shadow-sm">Tambahkan Divisi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.bg-slate-50 { background-color: #f8fafc; }
.bg-brand-green-soft { background-color: #FEF2F2; color: #DC2626; }
.text-brand-green { color: #DC2626; }
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


