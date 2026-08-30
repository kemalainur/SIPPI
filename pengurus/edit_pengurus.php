<?php
require_once '../config/database.php';
session_start();
check_login();
check_permission('pengurus.update');


$title = "Ubah Pengurus";
$error = '';
$success = '';

if (!isset($_GET['nokta'])) {
    header("Location: data_pengurus.php");
    exit;
}

$nokta = $_GET['nokta'];
$context_period_id = $_GET['context_period'] ?? '';

if (!$context_period_id) {
    $active_p = get_active_kepengurusan();
    $context_period_id = $active_p['id_kepengurusan'] ?? '';
}

$stmt = $pdo->prepare("SELECT p.*, j.biro_id, j.divisi_id, j.role_id, j.jabatan, r.nama_role 
                        FROM tabel_pengurus p 
                        LEFT JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
                        LEFT JOIN tabel_role r ON j.role_id = r.id_role
                        WHERE p.nokta = ?");
$stmt->execute([$context_period_id, $nokta]);
$p = $stmt->fetch();

if (!$p) {
    header("Location: data_pengurus.php");
    exit;
}

// SECURITY: Only Super Admin can edit Super Admin
if ($p['nama_role'] === 'Super Admin' && $_SESSION['user']['nama_role'] !== 'Super Admin') {
    $_SESSION['error'] = "Akses Ditolak: Data dan password Super Admin hanya dapat diubah oleh Super Admin sendiri.";
    header("Location: data_pengurus.php");
    exit;
}

$biros = $pdo->prepare("SELECT * FROM tabel_biro WHERE kepengurusan_id = ? ORDER BY nama_biro ASC");
$biros->execute([$context_period_id]);
$biros = $biros->fetchAll();

$divisis = $pdo->prepare("SELECT * FROM tabel_divisi WHERE kepengurusan_id = ? ORDER BY nama_divisi ASC");
$divisis->execute([$context_period_id]);
$divisis = $divisis->fetchAll();

$roles = $pdo->query("SELECT MIN(id_role) AS id_role, nama_role FROM tabel_role GROUP BY nama_role ORDER BY id_role ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $_POST['nama'];
    $angkatan = $_POST['angkatan'];
    $no_hp = $_POST['no_hp'];
    $jabatan = $_POST['jabatan'];
    $biro_id = $_POST['biro_id'] ?: null;
    $divisi_id = $_POST['divisi_id'] ?: null;
    $role_id = $_POST['role_id'];
    $password = $_POST['password'];

    try {
        $pdo->beginTransaction();

        // 1. Update Static Info
        if (!empty($password)) {
            $stmtStatic = $pdo->prepare("UPDATE tabel_pengurus SET nama=?, angkatan=?, no_hp=?, password=? WHERE nokta=?");
            $stmtStatic->execute([$nama, $angkatan, $no_hp, $password, $nokta]);
        } else {
            $stmtStatic = $pdo->prepare("UPDATE tabel_pengurus SET nama=?, angkatan=?, no_hp=? WHERE nokta=?");
            $stmtStatic->execute([$nama, $angkatan, $no_hp, $nokta]);
        }

        $stmtRoleCheck = $pdo->prepare("SELECT nama_role FROM tabel_role WHERE id_role = ?");
        $stmtRoleCheck->execute([$role_id]);
        $new_role_name = $stmtRoleCheck->fetchColumn();

        if ($_SESSION['user']['nama_role'] == 'Sekjend' && $new_role_name == 'Super Admin') {
            throw new Exception("Sekjend tidak diperbolehkan memberikan akses Super Admin.");
        }

        $stmtJabatan = $pdo->prepare("INSERT INTO tabel_pengurus_jabatan 
                                      (nokta, kepengurusan_id, biro_id, divisi_id, role_id, jabatan) 
                                      VALUES (?, ?, ?, ?, ?, ?)
                                      ON DUPLICATE KEY UPDATE biro_id=?, divisi_id=?, role_id=?, jabatan=?");
        $stmtJabatan->execute([
            $nokta, $context_period_id, $biro_id, $divisi_id, $role_id, $jabatan,
            $biro_id, $divisi_id, $role_id, $jabatan
        ]);

        $pdo->commit();
        $_SESSION['success'] = "Data pengurus berhasil diperbarui!";
        header("Location: data_pengurus.php?period=$context_period_id");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Gagal memperbarui data: " . $e->getMessage();
    }
}
include '../layout/header.php';
include '../layout/sidebar.php';
?>

<div id="content" class="fade-in">
    <div class="mb-4 d-flex justify-content-between align-items-center g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-brand-red mb-1">Perbarui Data Pengurus</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small mb-0">
                    <li class="breadcrumb-item"><a href="data_pengurus.php" class="text-primary text-decoration-none fw-600">Manajemen Pengurus</a></li>
                    <li class="breadcrumb-item active fw-600">Ubah Data: <?= $p['nama'] ?></li>
                </ol>
            </nav>
        </div>
        <a href="data_pengurus.php?period=<?= $context_period_id ?>" class="btn btn-light-soft text-muted px-4 fw-600 shadow-sm border">
            <i class="fas fa-arrow-left me-2"></i> Kembali
        </a>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger border-0 shadow-sm rounded-3 p-3 mb-4 d-flex align-items-center" role="alert">
        <i class="fas fa-exclamation-triangle me-3"></i>
        <div><?= $error ?></div>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-header bg-white border-0 py-4 px-4 border-bottom border-light d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-800 text-dark"><i class="fas fa-user-edit text-primary me-2"></i>Form Pengeditan Anggota</h6>
            <span class="badge bg-brand-green-soft text-brand-green px-3 py-2 rounded-pill fw-bold small">KTA: <?= $p['nokta'] ?></span>
        </div>
        <div class="card-body p-4 p-lg-5">
            <form method="POST">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-800 text-muted text-uppercase ls-1">Nama Lengkap</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="fas fa-user-circle text-muted"></i></span>
                            <input type="text" name="nama" value="<?= $p['nama'] ?>" class="form-control form-control-lg bg-light border-0" required placeholder="Masukkan Nama Lengkap">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-800 text-muted text-uppercase ls-1">Angkatan</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="fas fa-graduation-cap text-muted"></i></span>
                            <input type="text" name="angkatan" value="<?= $p['angkatan'] ?>" class="form-control form-control-lg bg-light border-0" required placeholder="Contoh: 2024">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-800 text-muted text-uppercase ls-1">No. HP / WhatsApp</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="fab fa-whatsapp text-muted"></i></span>
                            <input type="text" name="no_hp" value="<?= $p['no_hp'] ?>" class="form-control form-control-lg bg-light border-0" placeholder="Nomor Aktif">
                        </div>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label small fw-800 text-muted text-uppercase ls-1">Ganti Password (Opsional)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="fas fa-key text-muted"></i></span>
                            <input type="password" name="password" class="form-control form-control-lg bg-light border-0" placeholder="Kosongkan jika tidak ingin ganti">
                        </div>
                    </div>

                    <div class="col-12 mt-5">
                        <h6 class="fw-800 text-dark mb-4 border-bottom pb-2">Informasi Struktur & Jabatan</h6>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-800 text-muted text-uppercase ls-1">Jabatan Spesifik</label>
                        <input type="text" name="jabatan" value="<?= $p['jabatan'] ?>" class="form-control form-control-lg bg-light border-0" required placeholder="Contoh: Staff Divisi Media">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-800 text-muted text-uppercase ls-1">Role Sistem (Akses)</label>
                        <select name="role_id" class="form-select form-select-lg bg-light border-0" required>
                            <option value="">-- Pilih Akses Role --</option>
                            <?php foreach($roles as $r): 
                                // Hide Super Admin role from Sekjend
                                if ($_SESSION['user']['nama_role'] == 'Sekjend' && $r['nama_role'] == 'Super Admin') continue;
                            ?>
                            <option value="<?= $r['id_role'] ?>" <?= ($r['id_role'] == $p['role_id']) ? 'selected' : '' ?>><?= $r['nama_role'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-800 text-muted text-uppercase ls-1">Struktur Biro</label>
                        <select name="biro_id" class="form-select form-select-lg bg-light border-0">
                            <option value="">-- Tanpa Biro (PPI/Inti) --</option>
                            <?php foreach($biros as $b): ?>
                            <option value="<?= $b['id_biro'] ?>" <?= ($b['id_biro'] == $p['biro_id']) ? 'selected' : '' ?>><?= $b['nama_biro'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-800 text-muted text-uppercase ls-1">Unit Divisi</label>
                        <select name="divisi_id" class="form-select form-select-lg bg-light border-0">
                            <option value="">-- Pilih Divisi (Opsional) --</option>
                            <?php foreach($divisis as $d): ?>
                            <option value="<?= $d['id_divisi'] ?>" <?= ($d['id_divisi'] == $p['divisi_id']) ? 'selected' : '' ?>><?= $d['nama_divisi'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mt-5 pt-4 d-flex gap-3 justify-content-end border-top border-light">
                    <a href="data_pengurus.php" class="btn btn-light px-5 fw-800 rounded-pill py-3">Batal Perubahan</a>
                    <button type="submit" class="btn btn-primary px-5 fw-800 rounded-pill py-3 shadow-lg">
                        <i class="fas fa-save me-2"></i> Perbarui Data Pengurus
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.bg-light { background-color: #f8fafc !important; }
.btn-light-soft { background-color: #f1f5f9; border: none; }
.bg-brand-green-soft { background-color: #FEF2F2; color: #DC2626; }
.text-brand-green { color: #DC2626; }
.ls-1 { letter-spacing: 0.5px; }
.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.rounded-4 { border-radius: 1.5rem !important; }
.form-control-lg, .form-select-lg { font-size: 0.95rem; font-weight: 600; padding: 0.9rem 1.25rem; border-radius: 12px; }
.input-group-text { border-top-left-radius: 12px !important; border-bottom-left-radius: 12px !important; }
.form-control { border-top-right-radius: 12px !important; border-bottom-right-radius: 12px !important; }
</style>

<?php include '../layout/footer.php'; ?>

<?php include '../layout/footer.php'; ?>


