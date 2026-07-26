<?php
require_once '../config/database.php';
session_start();
check_login();
check_permission('ppi.manage');

// Handle manual lock
if (isset($_GET['action']) && $_GET['action'] === 'lock') {
    unset($_SESSION['auth_konfigurasi_penilaian']);
    header("Location: konfigurasi_penilaian.php");
    exit;
}

// Fetch current password from DB
$stmtPass = $pdo->prepare("SELECT nilai FROM tabel_pengaturan_sistem WHERE kunci = 'password_konfigurasi_penilaian'");
$stmtPass->execute();
$db_password = $stmtPass->fetchColumn() ?: 'ppi123'; // fallback default

$error = '';
$message = '';

// Handle Password Validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_password'])) {
    $input_pass = $_POST['auth_password'];
    if ($input_pass === $db_password) {
        $_SESSION['auth_konfigurasi_penilaian'] = true;
        header("Location: konfigurasi_penilaian.php");
        exit;
    } else {
        $error = "Password akses salah! Silakan coba lagi.";
    }
}

// Check if authenticated (Super Admin bypasses the password gate automatically)
$is_super_admin = ($_SESSION['user']['nama_role'] === 'Super Admin');
$is_authenticated = $is_super_admin || (isset($_SESSION['auth_konfigurasi_penilaian']) && $_SESSION['auth_konfigurasi_penilaian'] === true);

// Active Kepengurusan & Period
$active_p = get_active_kepengurusan();
$active_id = $active_p['id_kepengurusan'] ?? 0;

$stmtAktif = $pdo->prepare("SELECT * FROM tabel_periode WHERE status = 'aktif' AND kepengurusan_id = ? LIMIT 1");
$stmtAktif->execute([$active_id]);
$active_period = $stmtAktif->fetch();

// If authenticated, handle action requests
if ($is_authenticated) {

    // Handle Password Change (Super Admin Only)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
        if ($is_super_admin) {
            $new_pass = trim($_POST['new_password']);
            if (empty($new_pass)) {
                $error = "Password baru tidak boleh kosong.";
            } else {
                $stmtUpdatePass = $pdo->prepare("UPDATE tabel_pengaturan_sistem SET nilai = ? WHERE kunci = 'password_konfigurasi_penilaian'");
                if ($stmtUpdatePass->execute([$new_pass])) {
                    $db_password = $new_pass;
                    $message = "Password akses konfigurasi berhasil diperbarui!";
                } else {
                    $error = "Gagal memperbarui password.";
                }
            }
        } else {
            $error = "Hanya Super Admin yang dapat mengubah password.";
        }
    }

    // Handle Save Configuration
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
        if (!$active_period) {
            $error = "Gagal: Tidak ada bulan penilaian yang aktif.";
        } else {
            $bulan = $active_period['bulan'];
            $tahun = $active_period['tahun'];
            $penilai_list = $_POST['penilai'] ?? [];
            $dinilai_list = $_POST['dinilai'] ?? [];

            try {
                $pdo->beginTransaction();

                // Clear existing config for this month/year/kepengurusan
                $stmtClear = $pdo->prepare("DELETE FROM tabel_konfigurasi_penilaian 
                                            WHERE kepengurusan_id = ? AND bulan = ? AND tahun = ?");
                $stmtClear->execute([$active_id, $bulan, $tahun]);

                // Insert penilai list
                if (!empty($penilai_list)) {
                    $stmtInsert = $pdo->prepare("INSERT INTO tabel_konfigurasi_penilaian 
                                                 (kepengurusan_id, bulan, tahun, nokta, tipe) VALUES (?, ?, ?, ?, 'penilai')");
                    foreach ($penilai_list as $nokta) {
                        $stmtInsert->execute([$active_id, $bulan, $tahun, $nokta]);
                    }
                }

                // Insert dinilai list
                if (!empty($dinilai_list)) {
                    $stmtInsert = $pdo->prepare("INSERT INTO tabel_konfigurasi_penilaian 
                                                 (kepengurusan_id, bulan, tahun, nokta, tipe) VALUES (?, ?, ?, ?, 'dinilai')");
                    foreach ($dinilai_list as $nokta) {
                        $stmtInsert->execute([$active_id, $bulan, $tahun, $nokta]);
                    }
                }

                $pdo->commit();
                $message = "Konfigurasi Peer Assessment berhasil disimpan untuk bulan {$bulan}/{$tahun}!";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Terjadi kesalahan saat menyimpan: " . $e->getMessage();
            }
        }
    }
}

// Fetch all active members in current kepengurusan (except Super Admin)
$members = [];
if ($active_id) {
    $stmtMembers = $pdo->prepare("SELECT p.nokta, p.nama, j.jabatan, r.nama_role 
                                  FROM tabel_pengurus p 
                                  JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
                                  JOIN tabel_role r ON j.role_id = r.id_role
                                  WHERE r.nama_role NOT IN ('Super Admin', 'PJnas', 'PJNas')
                                  AND (p.angkatan IS NULL OR p.angkatan != '2023')
                                  ORDER BY p.nama ASC");
    $stmtMembers->execute([$active_id]);
    $members = $stmtMembers->fetchAll();
}

// Fetch current active configuration for penilai/dinilai
$configured_penilai = [];
$configured_dinilai = [];
if ($active_period) {
    $stmtGetConfig = $pdo->prepare("SELECT nokta, tipe FROM tabel_konfigurasi_penilaian 
                                    WHERE kepengurusan_id = ? AND bulan = ? AND tahun = ?");
    $stmtGetConfig->execute([$active_id, $active_period['bulan'], $active_period['tahun']]);
    $current_configs = $stmtGetConfig->fetchAll();

    foreach ($current_configs as $cfg) {
        if ($cfg['tipe'] === 'penilai') {
            $configured_penilai[] = $cfg['nokta'];
        } elseif ($cfg['tipe'] === 'dinilai') {
            $configured_dinilai[] = $cfg['nokta'];
        }
    }
}

$title = "Konfigurasi Peer Assessment";
include '../layout/header.php';
include '../layout/sidebar.php';
?>

<div id="content" class="fade-in">
    <?php if (!$is_authenticated): ?>
        <!-- GERBANG PASSWORD -->
        <div class="d-flex align-items-center justify-content-center" style="min-height: calc(100vh - 120px);">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden" style="max-width: 450px; width: 100%;">
                <div class="card-header bg-gradient-brand-red text-white text-center py-4 border-0">
                    <div class="mb-2"><i class="fas fa-lock fa-3x"></i></div>
                    <h5 class="fw-800 mb-0">Halaman Dilindungi</h5>
                    <p class="small opacity-75 mb-0 mt-1">Masukkan password akses untuk melanjutkan.</p>
                </div>
                <div class="card-body p-4 bg-white">
                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 rounded-3 py-2 small d-flex align-items-center mb-3">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <div><?= $error ?></div>
                        </div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label small fw-800 text-muted text-uppercase ls-1">Password Konfigurasi</label>
                            <input type="password" name="auth_password" class="form-control form-control-lg bg-light border-0" placeholder="Masukkan password" required autocomplete="current-password">
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="verify_password" class="btn btn-primary btn-lg fw-800 rounded-pill shadow-sm">
                                Buka Akses <i class="fas fa-unlock-keyhole ms-1"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- HALAMAN KONFIGURASI UTAMA (AUTHENTICATED) -->
        <div class="d-flex justify-content-between align-items-center mb-4 g-3 flex-wrap">
            <div>
                <h4 class="fw-800 text-brand-red mb-1">Konfigurasi Peer Assessment</h4>
                <div class="small text-muted">
                    Atur siapa saja yang menjadi penilai dan siapa saja yang akan dinilai pada periode aktif.
                </div>
            </div>
            <div class="d-flex gap-2">
                <?php if ($is_super_admin): ?>
                    <button class="btn btn-warning d-flex align-items-center shadow-sm px-3 fw-800 text-white" data-bs-toggle="modal" data-bs-target="#changePassModal">
                        <i class="fas fa-key me-2"></i> Ubah Password Akses
                    </button>
                <?php endif; ?>
                <a href="?action=lock" class="btn btn-dark d-flex align-items-center shadow-sm px-3 fw-800">
                    <i class="fas fa-lock me-2"></i> Kunci Halaman
                </a>
            </div>
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

        <?php if (!$active_period): ?>
            <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 text-center">
                <i class="fas fa-exclamation-circle fa-3x mb-3 text-warning"></i>
                <h5 class="fw-800">Bulan Penilaian Belum Aktif</h5>
                <p class="text-muted mb-3">Tidak ada bulan penilaian yang aktif untuk tahun kepengurusan ini. Silakan buka bulan penilaian baru terlebih dahulu.</p>
                <a href="<?= base_url('ppi/bulan_penilaian.php') ?>" class="btn btn-primary rounded-pill px-4 fw-800">
                    Buka Bulan Penilaian <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        <?php else: ?>
            <!-- Form Konfigurasi Penilaian -->
            <form method="POST" id="configForm">
                <input type="hidden" name="save_config" value="1">
                
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-gradient-brand-red text-white">
                    <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-white text-brand-red rounded-circle d-flex align-items-center justify-content-center me-3 shadow" style="width: 50px; height: 50px;">
                                <i class="fas fa-calendar-day fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-600 opacity-75">Periode Penilaian Aktif</h6>
                                <h3 class="mb-0 fw-800">Bulan <?= $active_period['bulan'] ?> / Tahun <?= $active_period['tahun'] ?></h3>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-white text-brand-red rounded-pill px-3 py-2 fw-bold small shadow-sm">
                                <i class="fas fa-check-circle me-1"></i> Terbuka
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Info Alert: Defaults -->
                <div class="alert alert-info border-0 rounded-4 bg-white shadow-sm p-3 mb-4 d-flex align-items-center">
                    <i class="fas fa-info-circle fa-lg text-primary me-3"></i>
                    <div class="small text-dark">
                        <strong>Informasi:</strong> Jika Anda tidak mencentang siapa pun pada salah satu kategori (kosong), sistem akan menganggap <strong>seluruh anggota aktif</strong> (selain Super Admin) dapat melakukan tindakan tersebut secara default.
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white border-0 p-0 border-bottom border-light">
                        <ul class="nav nav-tabs nav-fill border-0" id="configTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active py-3 fw-800 border-0 border-bottom border-3 text-uppercase" id="penilai-tab" data-bs-toggle="tab" data-bs-target="#penilai-pane" type="button" role="tab" aria-controls="penilai-pane" aria-selected="true">
                                    <i class="fas fa-user-pen me-2 text-primary"></i> 1. Penilai (Yang Bisa Menilai)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link py-3 fw-800 border-0 border-bottom border-3 text-uppercase" id="dinilai-tab" data-bs-toggle="tab" data-bs-target="#dinilai-pane" type="button" role="tab" aria-controls="dinilai-pane" aria-selected="false">
                                    <i class="fas fa-user-check me-2 text-success"></i> 2. Yang Dinilai (Bisa Dievaluasi)
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="card-body p-4 bg-white">
                        <div class="tab-content" id="configTabsContent">
                            <!-- TAB 1: PENILAI -->
                            <div class="tab-pane fade show active" id="penilai-pane" role="tabpanel" aria-labelledby="penilai-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap g-2">
                                    <h6 class="fw-800 text-dark mb-0">Siapa saja yang berhak memberikan penilaian bulan ini?</h6>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" onclick="toggleAllCheckboxes('penilai', true)">Pilih Semua</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="toggleAllCheckboxes('penilai', false)">Kosongkan</button>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <?php if ($members): foreach ($members as $m): 
                                        $isChecked = in_array($m['nokta'], $configured_penilai) ? 'checked' : '';
                                    ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="card border border-light-soft rounded-3 shadow-none hover-shadow-sm p-3 position-relative select-card">
                                                <div class="form-check d-flex align-items-center justify-content-between w-100">
                                                    <div class="d-flex align-items-center">
                                                        <input class="form-check-input check-penilai me-3" type="checkbox" name="penilai[]" value="<?= $m['nokta'] ?>" id="penilai_<?= $m['nokta'] ?>" <?= $isChecked ?> style="width: 20px; height: 20px;">
                                                        <label class="form-check-label" for="penilai_<?= $m['nokta'] ?>">
                                                            <strong class="text-dark d-block"><?= htmlspecialchars($m['nama']) ?></strong>
                                                            <span class="text-muted small"><?= htmlspecialchars($m['jabatan']) ?></span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; else: ?>
                                        <div class="col-12 text-center py-4">
                                            <p class="text-muted mb-0">Tidak ada pengurus aktif.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- TAB 2: YANG DINILAI -->
                            <div class="tab-pane fade" id="dinilai-pane" role="tabpanel" aria-labelledby="dinilai-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap g-2">
                                    <h6 class="fw-800 text-dark mb-0">Siapa saja yang akan dievaluasi/dinilai kinerjanya bulan ini?</h6>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-success btn-sm rounded-pill px-3" onclick="toggleAllCheckboxes('dinilai', true)">Pilih Semua</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="toggleAllCheckboxes('dinilai', false)">Kosongkan</button>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <?php if ($members): foreach ($members as $m): 
                                        $isChecked = in_array($m['nokta'], $configured_dinilai) ? 'checked' : '';
                                    ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="card border border-light-soft rounded-3 shadow-none hover-shadow-sm p-3 position-relative select-card">
                                                <div class="form-check d-flex align-items-center justify-content-between w-100">
                                                    <div class="d-flex align-items-center">
                                                        <input class="form-check-input check-dinilai me-3" type="checkbox" name="dinilai[]" value="<?= $m['nokta'] ?>" id="dinilai_<?= $m['nokta'] ?>" <?= $isChecked ?> style="width: 20px; height: 20px;">
                                                        <label class="form-check-label" for="dinilai_<?= $m['nokta'] ?>">
                                                            <strong class="text-dark d-block"><?= htmlspecialchars($m['nama']) ?></strong>
                                                            <span class="text-muted small"><?= htmlspecialchars($m['jabatan']) ?></span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; else: ?>
                                        <div class="col-12 text-center py-4">
                                            <p class="text-muted mb-0">Tidak ada pengurus aktif.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Action -->
                    <div class="card-footer bg-light py-4 px-4 border-0 d-flex justify-content-end gap-2">
                        <a href="<?= base_url('dashboard/dashboard.php') ?>" class="btn btn-light-soft border px-4 rounded-pill fw-800 text-muted">Kembali</a>
                        <button type="submit" class="btn btn-primary px-5 rounded-pill fw-800 shadow-sm">
                            Simpan Konfigurasi <i class="fas fa-save ms-1"></i>
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($is_authenticated && $is_super_admin): ?>
    <!-- MODAL UBAH PASSWORD (SUPER ADMIN ONLY) -->
    <div class="modal fade" id="changePassModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-800 text-dark"><i class="fas fa-key text-warning me-2"></i>Ubah Password Akses Modul</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <p class="text-muted small">Ubah password yang digunakan oleh Tim PPI/Super Admin untuk membuka akses konfigurasi peer assessment.</p>
                        <div class="mb-3 bg-light p-3 rounded-3 border-start border-warning border-3">
                            <span class="small text-muted fw-800 text-uppercase d-block ls-1 mb-1">Password Saat Ini:</span>
                            <code class="fs-5 fw-bold text-dark"><?= htmlspecialchars($db_password) ?></code>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-800 text-muted text-uppercase ls-1">Password Baru</label>
                            <input type="text" name="new_password" class="form-control form-control-lg bg-light border-0" placeholder="Masukkan password baru" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light-soft text-muted px-4 rounded-pill fw-800" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="change_password" class="btn btn-warning text-white px-4 rounded-pill fw-800">
                            Simpan Perubahan <i class="fas fa-check-circle ms-1"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function toggleAllCheckboxes(type, status) {
    const checkboxes = document.querySelectorAll('.check-' + type);
    checkboxes.forEach(chk => {
        chk.checked = status;
    });
}
</script>

<style>
.hover-shadow-sm:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    border-color: rgba(0, 0, 0, 0.1) !important;
}
.select-card {
    transition: all 0.2s ease-in-out;
}
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
.nav-tabs .nav-link {
    color: #475569;
    border-bottom: 3px solid transparent;
}
.nav-tabs .nav-link:hover {
    color: #0F172A;
    border-color: #E2E8F0;
}
.nav-tabs .nav-link.active {
    color: #DC2626 !important;
    border-bottom-color: #DC2626 !important;
    font-weight: 800;
}
</style>

<?php include '../layout/footer.php'; ?>
