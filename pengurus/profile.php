<?php
require_once '../config/database.php';
session_start();
check_login();

$active_p = get_active_kepengurusan();
$active_id = $active_p['id_kepengurusan'] ?? 0;
$my_nokta = $_SESSION['user']['nokta'];

$message = '';
$error = '';

// Handle Profile Update Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no_hp = trim($_POST['no_hp']);
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    try {
        if (!empty($new_pass)) {
            if ($new_pass !== $confirm_pass) {
                $error = "Pembaruan gagal: Password baru dan konfirmasi password tidak cocok!";
            } else {
                $stmtUpdate = $pdo->prepare("UPDATE tabel_pengurus SET no_hp = ?, password = ? WHERE nokta = ?");
                $stmtUpdate->execute([$no_hp, $new_pass, $my_nokta]);
                $message = "Profil dan password Anda berhasil diperbarui!";
            }
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE tabel_pengurus SET no_hp = ? WHERE nokta = ?");
            $stmtUpdate->execute([$no_hp, $my_nokta]);
            $message = "Nomor telepon Anda berhasil diperbarui!";
        }
    } catch (Exception $e) {
        $error = "Gagal memperbarui profil: " . $e->getMessage();
    }
}

// Fetch current user details
$stmtUser = $pdo->prepare("SELECT p.*, j.jabatan, r.nama_role, b.nama_biro, d.nama_divisi 
                            FROM tabel_pengurus p 
                            LEFT JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ? 
                            LEFT JOIN tabel_role r ON j.role_id = r.id_role 
                            LEFT JOIN tabel_biro b ON j.biro_id = b.id_biro 
                            LEFT JOIN tabel_divisi d ON j.divisi_id = d.id_divisi 
                            WHERE p.nokta = ? LIMIT 1");
$stmtUser->execute([$active_id, $my_nokta]);
$user = $stmtUser->fetch();

if (!$user) {
    die("Error: Data pengurus tidak ditemukan.");
}

$title = "Profil Saya";
include '../layout/header.php';
include '../layout/sidebar.php';

$initials = strtoupper(substr($user['nama'] ?? '-', 0, 1));
?>

<div id="content" class="fade-in">
    <div class="mb-4">
        <h4 class="fw-800 text-brand-red mb-1">Pengaturan Profil Saya</h4>
        <p class="text-muted small">Kelola informasi kontak dan keamanan akun Anda sendiri secara mandiri.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show d-flex align-items-center rounded-3 p-3 mb-4" role="alert">
            <i class="fas fa-check-circle fa-lg me-3"></i>
            <div class="fw-600"><?= $message ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm alert-dismissible fade show d-flex align-items-center rounded-3 p-3 mb-4" role="alert">
            <i class="fas fa-exclamation-triangle fa-lg me-3"></i>
            <div class="fw-600"><?= $error ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-5">
        <!-- CARD INFO PROFIL -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden text-center p-4 h-100 bg-white">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <div class="avatar-lg bg-gradient-brand-red text-white rounded-circle d-flex align-items-center justify-content-center fw-800 shadow-md mb-3" style="width: 100px; height: 100px; font-size: 2.5rem;">
                        <?= $initials ?>
                    </div>
                    <h5 class="fw-800 text-dark mb-1"><?= htmlspecialchars($user['nama']) ?></h5>
                    <span class="badge bg-brand-red-soft text-brand-red px-3 py-2 rounded-pill fw-bold small mb-4">
                        <?= htmlspecialchars($user['nama_role'] ?? 'Pengurus') ?>
                    </span>

                    <hr class="w-100 mb-4" style="opacity: 0.1;">

                    <div class="w-100 text-start">
                        <div class="mb-3">
                            <span class="small text-muted fw-800 text-uppercase d-block ls-1 mb-1">Nomor KTA / Nokta</span>
                            <span class="text-dark fw-bold"><?= htmlspecialchars($user['nokta']) ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="small text-muted fw-800 text-uppercase d-block ls-1 mb-1">Angkatan</span>
                            <span class="text-dark fw-bold"><?= htmlspecialchars($user['angkatan'] ?? '-') ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="small text-muted fw-800 text-uppercase d-block ls-1 mb-1">Jabatan</span>
                            <span class="text-dark fw-bold"><?= htmlspecialchars($user['jabatan'] ?? '-') ?></span>
                        </div>
                        <?php if ($user['nama_biro']): ?>
                        <div class="mb-3">
                            <span class="small text-muted fw-800 text-uppercase d-block ls-1 mb-1">Biro</span>
                            <span class="text-dark fw-bold"><?= htmlspecialchars($user['nama_biro']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($user['nama_divisi']): ?>
                        <div class="mb-3">
                            <span class="small text-muted fw-800 text-uppercase d-block ls-1 mb-1">Divisi</span>
                            <span class="text-dark fw-bold"><?= htmlspecialchars($user['nama_divisi']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- FORM PENGATURAN -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 bg-white">
                <div class="card-header bg-white border-0 py-4 px-4 border-bottom border-light">
                    <h6 class="mb-0 fw-800 text-dark"><i class="fas fa-user-gear text-primary me-2"></i>Ubah Informasi Kontak & Sandi</h6>
                </div>
                <div class="card-body p-4 p-lg-5">
                    <form method="POST">
                        <div class="row g-4">
                            <!-- Nomor HP -->
                            <div class="col-12">
                                <label class="form-label small fw-800 text-muted text-uppercase ls-1">Nomor Telepon / WhatsApp</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="fas fa-phone text-muted"></i></span>
                                    <input type="text" name="no_hp" value="<?= htmlspecialchars($user['no_hp'] ?? '') ?>" class="form-control form-control-lg bg-light border-0" required placeholder="Contoh: 081234567890">
                                </div>
                                <div class="form-text small text-muted">Gunakan format angka saja (misal: 0812...).</div>
                            </div>

                            <div class="col-12">
                                <hr class="my-2" style="opacity: 0.1;">
                            </div>

                            <!-- Password Baru -->
                            <div class="col-md-6">
                                <label class="form-label small fw-800 text-muted text-uppercase ls-1">Password Baru</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="fas fa-lock text-muted"></i></span>
                                    <input type="password" name="new_password" class="form-control form-control-lg bg-light border-0" placeholder="Kosongkan jika tidak ingin diubah">
                                </div>
                            </div>

                            <!-- Konfirmasi Password Baru -->
                            <div class="col-md-6">
                                <label class="form-label small fw-800 text-muted text-uppercase ls-1">Konfirmasi Password Baru</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="fas fa-circle-check text-muted"></i></span>
                                    <input type="password" name="confirm_password" class="form-control form-control-lg bg-light border-0" placeholder="Masukkan ulang password baru">
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-5">
                            <a href="<?= base_url('dashboard/dashboard.php') ?>" class="btn btn-light-soft border px-4 rounded-pill fw-800 text-muted">Kembali</a>
                            <button type="submit" class="btn btn-primary px-5 rounded-pill fw-800 shadow-sm">
                                Simpan Perubahan <i class="fas fa-save ms-2"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bg-gradient-brand-red {
    background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%);
}
.btn-primary {
    background-color: #DC2626;
    border-color: #DC2626;
}
.btn-primary:hover, .btn-primary:focus {
    background-color: #B91C1C;
    border-color: #B91C1C;
}
</style>

<?php include '../layout/footer.php'; ?>
