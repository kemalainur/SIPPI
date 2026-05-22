<?php
require_once '../config/database.php';
session_start();
check_login();
check_role(['Super Admin', 'Sekjend', 'PPI']);

$title = "Tambah Pengurus";
$error = '';
$success = '';

// Fetch ACTIVE period
$active_p = get_active_kepengurusan();
if (!$active_p) {
    die("Error: Tidak ada Tahun Kepengurusan yang aktif. Silakan hubungi Super Admin.");
}

// Fetch related data FILTERED by active period
$biros = $pdo->prepare("SELECT * FROM tabel_biro WHERE kepengurusan_id = ? ORDER BY nama_biro ASC");
$biros->execute([$active_p['id_kepengurusan']]);
$biros = $biros->fetchAll();

$divisis = $pdo->prepare("SELECT * FROM tabel_divisi WHERE kepengurusan_id = ? ORDER BY nama_divisi ASC");
$divisis->execute([$active_p['id_kepengurusan']]);
$divisis = $divisis->fetchAll();

$roles = $pdo->query("SELECT * FROM tabel_role ORDER BY id_role ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nokta = $_POST['nokta'];
    $nama = $_POST['nama'];
    $angkatan = $_POST['angkatan'];
    $no_hp = $_POST['no_hp'];
    $jabatan = $_POST['jabatan'];
    $biro_id = $_POST['biro_id'] ?: null;
    $divisi_id = $_POST['divisi_id'] ?: null;
    $role_id = $_POST['role_id'];
    $password = $_POST['password'];

    // SECURITY CHECK: Sekjend cannot create Super Admin
    $stmtRoleCheck = $pdo->prepare("SELECT nama_role FROM tabel_role WHERE id_role = ?");
    $stmtRoleCheck->execute([$role_id]);
    $new_role_name = $stmtRoleCheck->fetchColumn();

    if ($_SESSION['user']['nama_role'] == 'Sekjend' && $new_role_name == 'Super Admin') {
        $error = "Akses Ditolak: Sekjend tidak diperbolehkan membuat akun dengan level Super Admin.";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Save Static Info (ignore if exists, but we usually check)
            $stmtStatic = $pdo->prepare("INSERT INTO tabel_pengurus (nokta, nama, angkatan, no_hp, password) 
                                         VALUES (?, ?, ?, ?, ?) 
                                         ON DUPLICATE KEY UPDATE nama=?, angkatan=?, no_hp=?, password=?");
            $stmtStatic->execute([$nokta, $nama, $angkatan, $no_hp, $password, $nama, $angkatan, $no_hp, $password]);

            // 2. Save Position for Active Period
            $stmtJabatan = $pdo->prepare("INSERT INTO tabel_pengurus_jabatan 
                                          (nokta, kepengurusan_id, biro_id, divisi_id, role_id, jabatan) 
                                          VALUES (?, ?, ?, ?, ?, ?)
                                          ON DUPLICATE KEY UPDATE biro_id=?, divisi_id=?, role_id=?, jabatan=?");
            $stmtJabatan->execute([
                $nokta,
                $active_p['id_kepengurusan'],
                $biro_id,
                $divisi_id,
                $role_id,
                $jabatan,
                $biro_id,
                $divisi_id,
                $role_id,
                $jabatan
            ]);

            $pdo->commit();
            $_SESSION['success'] = "Pengurus berhasil ditambahkan/diperbarui di periode aktif!";
            header("Location: data_pengurus.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}
include '../layout/header.php';
include '../layout/sidebar.php';
?>

<div id="content" class="fade-in">
    <div class="mb-4 d-flex justify-content-between align-items-center g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-brand-red mb-1">Tambah Pengurus Baru</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small mb-0">
                    <li class="breadcrumb-item"><a href="data_pengurus.php"
                            class="text-primary text-decoration-none fw-600">Manajemen Pengurus</a></li>
                    <li class="breadcrumb-item active fw-600">Entri Data Baru</li>
                </ol>
            </nav>
        </div>
        <a href="data_pengurus.php" class="btn btn-light-soft text-muted px-4 fw-600 shadow-sm border">
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
        <div class="card-header bg-white border-0 py-4 px-4 border-bottom border-light">
            <h6 class="mb-0 fw-800 text-dark"><i class="fas fa-id-card text-primary me-2"></i>Informasi Data Keanggotaan
            </h6>
        </div>
        <div class="card-body p-4 p-lg-5">
            <form method="POST">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-800 text-muted text-uppercase ls-1">Nomor KTA (NOKTA)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i
                                    class="fas fa-id-badge text-muted"></i></span>
                            <input type="text" name="nokta" id="nokta"
                                class="form-control form-control-lg bg-light border-0" required
                                placeholder="Contoh: 07-23106">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-800 text-muted text-uppercase ls-1">Nama Lengkap</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i
                                    class="fas fa-user-circle text-muted"></i></span>
                            <input type="text" name="nama" class="form-control form-control-lg bg-light border-0"
                                required placeholder="">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-800 text-muted text-uppercase ls-1">Angkatan</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i
                                    class="fas fa-graduation-cap text-muted"></i></span>
                            <input type="text" name="angkatan" class="form-control form-control-lg bg-light border-0"
                                required placeholder="Contoh: 2024">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-800 text-muted text-uppercase ls-1">No. HP / WhatsApp</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i
                                    class="fab fa-whatsapp text-muted"></i></span>
                            <input type="text" name="no_hp" class="form-control form-control-lg bg-light border-0"
                                placeholder="08xxxxxxxxxx">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-800 text-muted text-uppercase ls-1">Login Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i
                                    class="fas fa-key text-muted"></i></span>
                            <input type="password" name="password"
                                class="form-control form-control-lg bg-light border-0" required
                                placeholder="Minimal 6 karakter">
                        </div>
                    </div>

                    <div class="col-12 mt-5">
                        <h6 class="fw-800 text-dark mb-4 border-bottom pb-2">Informasi Struktur & Jabatan</h6>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-800 text-muted text-uppercase ls-1">Jabatan Spesifik</label>
                        <input type="text" name="jabatan" class="form-control form-control-lg bg-light border-0"
                            required placeholder="Contoh: Staff Divisi Penerbitan">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-800 text-muted text-uppercase ls-1">Role Sistem
                            (Akses)</label>
                        <select name="role_id" class="form-select form-select-lg bg-light border-0" required>
                            <option value="">-- Pilih Level Akses --</option>
                            <?php foreach ($roles as $r): 
                                // Hide Super Admin role from Sekjend
                                if ($_SESSION['user']['nama_role'] == 'Sekjend' && $r['nama_role'] == 'Super Admin') continue;
                            ?>
                                <option value="<?= $r['id_role'] ?>"><?= $r['nama_role'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-info-soft" style="font-size: 0.75rem;">Role 'Staff' diperuntukkan bagi
                            anggota divisi.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-800 text-muted text-uppercase ls-1">Struktur Biro</label>
                        <select name="biro_id" class="form-select form-select-lg bg-light border-0">
                            <option value="">-- Tanpa Biro (Internal PPI/Inti) --</option>
                            <?php foreach ($biros as $b): ?>
                                <option value="<?= $b['id_biro'] ?>"><?= $b['nama_biro'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-800 text-muted text-uppercase ls-1">Unit Divisi</label>
                        <select name="divisi_id" class="form-select form-select-lg bg-light border-0">
                            <option value="">-- Pilih Divisi (Bila Sudah Ditetapkan) --</option>
                            <?php foreach ($divisis as $d): ?>
                                <option value="<?= $d['id_divisi'] ?>"><?= $d['nama_divisi'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mt-5 pt-4 d-flex gap-3 justify-content-end border-top border-light">
                    <a href="data_pengurus.php" class="btn btn-light px-5 fw-800 rounded-pill py-3">Batal</a>
                    <button type="submit" class="btn btn-primary px-5 fw-800 rounded-pill py-3 shadow-lg">
                        <i class="fas fa-save me-2"></i> Simpan Data Pengurus
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .bg-light {
        background-color: #f8fafc !important;
    }

    .btn-light-soft {
        background-color: #f1f5f9;
        border: none;
    }

    .ls-1 {
        letter-spacing: 0.5px;
    }

    .fw-800 {
        font-weight: 800;
    }

    .fw-600 {
        font-weight: 600;
    }

    .rounded-4 {
        border-radius: 1.5rem !important;
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

    .form-control {
        border-top-right-radius: 12px !important;
        border-bottom-right-radius: 12px !important;
    }
</style>

<?php include '../layout/footer.php'; ?>

<?php include '../layout/footer.php'; ?>