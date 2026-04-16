<?php
require_once '../config/database.php';
session_start();
check_login();
check_role(['Super Admin', 'PPI']);

$title = "Kelola Tahun Kepengurusan";
include '../layout/header.php';
include '../layout/sidebar.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_period'])) {
        $nama = $_POST['nama_periode'];
        $copy_from = $_POST['copy_from'] ?? null;
        $copy_year = $_POST['copy_year'] ?? ''; // Newest angkatan to copy

        try {
            $pdo->beginTransaction();

            // 1. Create the new period as 'arsip' first
            $stmt = $pdo->prepare("INSERT INTO tabel_kepengurusan (nama_periode, status) VALUES (?, 'arsip')");
            $stmt->execute([$nama]);
            $new_id = $pdo->lastInsertId();

            if ($copy_from) {
                // 2. Copy Biros
                $stmtBiro = $pdo->prepare("SELECT id_biro, nama_biro FROM tabel_biro WHERE kepengurusan_id = ?");
                $stmtBiro->execute([$copy_from]);
                $old_biros = $stmtBiro->fetchAll();

                foreach ($old_biros as $ob) {
                    $stmtInsBiro = $pdo->prepare("INSERT INTO tabel_biro (nama_biro, kepengurusan_id) VALUES (?, ?)");
                    $stmtInsBiro->execute([$ob['nama_biro'], $new_id]);
                    $new_biro_id = $pdo->lastInsertId();

                    // 3. Copy Divisions for this Biro
                    $stmtDiv = $pdo->prepare("SELECT nama_divisi FROM tabel_divisi WHERE biro_id = ?");
                    $stmtDiv->execute([$ob['id_biro']]);
                    $old_divs = $stmtDiv->fetchAll();

                    foreach ($old_divs as $od) {
                        $stmtInsDiv = $pdo->prepare("INSERT INTO tabel_divisi (nama_divisi, biro_id, kepengurusan_id) VALUES (?, ?, ?)");
                        $stmtInsDiv->execute([$od['nama_divisi'], $new_biro_id, $new_id]);
                    }
                }

                // 4. Auto-Copy Pengurus (Only specific Angkatan as per user request)
                if ($copy_year) {
                    // We need to map old biro/div names to new IDs since IDs changed
                    $stmtMapBiro = $pdo->prepare("SELECT b1.id_biro as old_id, b2.id_biro as new_id 
                                                 FROM tabel_biro b1 
                                                 JOIN tabel_biro b2 ON b1.nama_biro = b2.nama_biro 
                                                 WHERE b1.kepengurusan_id = ? AND b2.kepengurusan_id = ?");
                    $stmtMapBiro->execute([$copy_from, $new_id]);
                    $biro_map = $stmtMapBiro->fetchAll(PDO::FETCH_KEY_PAIR);

                    // Fetch pengurus from latest angkatan in the source period
                    $stmtOldP = $pdo->prepare("SELECT j.* FROM tabel_pengurus_jabatan j 
                                               JOIN tabel_pengurus p ON j.nokta = p.nokta 
                                               WHERE j.kepengurusan_id = ? AND p.angkatan = ?");
                    $stmtOldP->execute([$copy_from, $copy_year]);
                    $to_copy = $stmtOldP->fetchAll();

                    foreach ($to_copy as $tc) {
                        $new_b = $biro_map[$tc['biro_id']] ?? null;
                        // Find new div id by name
                        $new_d = null;
                        if ($tc['divisi_id']) {
                            $stmtOldDivName = $pdo->prepare("SELECT nama_divisi FROM tabel_divisi WHERE id_divisi = ?");
                            $stmtOldDivName->execute([$tc['divisi_id']]);
                            $div_name = $stmtOldDivName->fetchColumn();
                            
                            $stmtNewDivId = $pdo->prepare("SELECT id_divisi FROM tabel_divisi WHERE nama_divisi = ? AND kepengurusan_id = ?");
                            $stmtNewDivId->execute([$div_name, $new_id]);
                            $new_d = $stmtNewDivId->fetchColumn() ?: null;
                        }

                        $stmtInsJ = $pdo->prepare("INSERT INTO tabel_pengurus_jabatan (nokta, kepengurusan_id, biro_id, divisi_id, role_id, jabatan) 
                                                   VALUES (?, ?, ?, ?, ?, ?)");
                        $stmtInsJ->execute([$tc['nokta'], $new_id, $new_b, $new_d, $tc['role_id'], $tc['jabatan']]);
                    }
                }
            }

            $pdo->commit();
            $message = "Tahun Kepengurusan $nama berhasil dibuat!";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = "Gagal: " . $e->getMessage();
        }
    } elseif (isset($_POST['set_active'])) {
        $id = $_POST['id_kepengurusan'];
        $pdo->query("UPDATE tabel_kepengurusan SET status = 'arsip'");
        $stmt = $pdo->prepare("UPDATE tabel_kepengurusan SET status = 'aktif' WHERE id_kepengurusan = ?");
        $stmt->execute([$id]);
        $message = "Tahun Kepengurusan berhasil diaktifkan!";
    } elseif (isset($_POST['delete_period'])) {
        $id = $_POST['id_kepengurusan'];
        $stmt = $pdo->prepare("DELETE FROM tabel_kepengurusan WHERE id_kepengurusan = ? AND status != 'aktif'");
        if ($stmt->execute([$id])) {
            $message = "Tahun Kepengurusan berhasil dihapus!";
        } else {
            $message = "Gagal menghapus periode aktif.";
        }
    }
}

$all_periods = $pdo->query("SELECT * FROM tabel_kepengurusan ORDER BY nama_periode DESC")->fetchAll();
$angkatans = $pdo->query("SELECT DISTINCT angkatan FROM tabel_pengurus ORDER BY angkatan DESC")->fetchAll(PDO::FETCH_COLUMN);
?>

<div id="content" class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-brand-red mb-1">Manajemen Tahun Kepengurusan</h4>
            <p class="text-muted small mb-0">Kelola siklus organisasi, regenerasi struktur, dan arsip data tahunan SIPPI.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show d-flex align-items-center rounded-4 p-3 mb-4" role="alert">
            <i class="fas fa-check-circle fa-lg me-3 text-brand-green"></i>
            <div class="fw-600 text-dark"><?= $message ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Panel Tambah Tahun -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-0 py-4 px-4 border-bottom border-light">
                    <h6 class="mb-0 fw-800 text-dark"><i class="fas fa-calendar-plus text-primary me-2"></i>Inisialisasi Tahun Baru</h6>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label small fw-800 text-muted text-uppercase ls-1">Nama Periode</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="fas fa-id-card-alt text-muted"></i></span>
                                <input type="text" name="nama_periode" class="form-control form-control-lg bg-light border-0" required placeholder="Contoh: 2025/2026">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-800 text-muted text-uppercase ls-1">Salin Struktur Dari (Opsional)</label>
                            <select name="copy_from" class="form-select form-select-lg bg-light border-0">
                                <option value="">-- Buat Dari Nol --</option>
                                <?php foreach($all_periods as $period): ?>
                                <option value="<?= $period['id_kepengurusan'] ?>">Sama Seperti <?= $period['nama_periode'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-info-soft mt-2 d-block fw-600" style="font-size: 0.7rem;">Menyalin data Biro dan Divisi secara otomatis.</small>
                        </div>

                        <div class="mb-5">
                            <label class="form-label small fw-800 text-muted text-uppercase ls-1">Salin Pengurus Angkatan</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="fas fa-users-cog text-muted"></i></span>
                                <input type="number" name="copy_year" class="form-control form-control-lg bg-light border-0" placeholder="Contoh: 2024">
                            </div>
                            <small class="text-danger mt-2 d-block fw-600" style="font-size: 0.65rem;"><i class="fas fa-info-circle me-1"></i>Isi HANYA jika ingin menyalin anggota angkatan tersebut.</small>
                        </div>

                        <button type="submit" name="add_period" class="btn btn-primary w-100 fw-800 py-3 rounded-pill shadow-lg">
                            <i class="fas fa-rocket me-2"></i> Buat & Simpan Periode
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Panel Daftar Tahun -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                <div class="card-header bg-white border-0 py-4 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-800 text-dark">Arsip & Status Kepengurusan</h6>
                    <span class="badge bg-slate-100 text-slate-600 px-3 py-2 rounded-pill fw-bold small">Total: <?= count($all_periods) ?> Periode</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-slate-50 text-muted small text-uppercase fw-bold">
                                <tr>
                                    <th class="ps-4 py-3">Nama Kepengurusan</th>
                                    <th class="text-center">Status Sistem</th>
                                    <th class="text-center pe-4" style="width: 250px;">Kendali Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($all_periods): foreach($all_periods as $p): ?>
                                <tr class="modern-row <?= $p['status'] == 'aktif' ? 'active-row' : '' ?>">
                                    <td class="ps-4 py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm me-3 <?= $p['status'] == 'aktif' ? 'bg-brand-green-soft text-brand-green' : 'bg-slate-100 text-muted' ?> rounded-3 d-flex align-items-center justify-content-center fw-800">
                                                <i class="fas fa-calendar-check"></i>
                                            </div>
                                            <div class="fw-800 text-dark"><?= $p['nama_periode'] ?></div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php if($p['status'] == 'aktif'): ?>
                                            <span class="badge bg-brand-green-soft text-brand-green px-3 py-2 rounded-pill fw-bold" style="font-size: 0.65rem;">
                                                <i class="fas fa-play-circle me-1 animate-pulse"></i> AKTIF SEKARANG
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border px-3 py-2 rounded-pill fw-bold" style="font-size: 0.65rem;">ARSIP DATA</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center pe-4">
                                        <div class="d-flex justify-content-center gap-1">
                                            <?php if($p['status'] != 'aktif'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="id_kepengurusan" value="<?= $p['id_kepengurusan'] ?>">
                                                    <button type="submit" name="set_active" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-800" onclick="return confirm('Aktifkan periode ini? Periode lain akan otomatis menjadi arsip data.')">
                                                        <i class="fas fa-power-off me-1"></i> Aktifkan
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="id_kepengurusan" value="<?= $p['id_kepengurusan'] ?>">
                                                    <button type="submit" name="delete_period" class="btn btn-icon btn-light-soft text-danger" onclick="return confirm('PERINGATAN: Menghapus periode akan menghapus SEMUA data terkait. Lanjutkan?')">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-brand-red-soft text-brand-green btn-sm rounded-pill px-3 fw-800 border-0" disabled>
                                                    <i class="fas fa-check-circle me-1"></i> Digunakan
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="3" class="text-center py-5">
                                        <div class="py-4">
                                            <i class="fas fa-folder-open fa-3x text-muted opacity-25 mb-3"></i>
                                            <h6 class="text-muted fw-bold">Belum ada data kepengurusan.</h6>
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
    </div>
</div>

<style>
.bg-light { background-color: #f8fafc !important; }
.bg-slate-50 { background-color: #f8fafc; }
.bg-slate-100 { background-color: #f1f5f9; }
.text-slate-600 { color: #475569; }
.bg-brand-green-soft { background-color: #FEF2F2; color: #DC2626; }
.text-brand-green { color: #DC2626; }
.btn-light-soft { background-color: #f1f5f9; border: none; }
.btn-icon { width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 0.9rem; }
.avatar-sm { width: 35px; height: 35px; border-radius: 10px; font-size: 0.8rem; }
.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.modern-row:hover { background-color: #f9fafb; }
.active-row { background-color: #fbfdfc; }
.ls-1 { letter-spacing: 0.5px; }
.rounded-4 { border-radius: 1.25rem !important; }
.form-control-lg, .form-select-lg { font-size: 0.95rem; font-weight: 600; padding: 0.9rem 1.25rem; border-radius: 12px; }
.text-info-soft { color: #0891b2; font-weight: 600; }
.input-group-text { border-top-left-radius: 12px !important; border-bottom-left-radius: 12px !important; }
.animate-pulse { animation: pulse 2s infinite; }
@keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }
</style>

<?php include '../layout/footer.php'; ?>


