<?php
require_once '../config/database.php';
session_start();
check_login();
check_permission('pengurus.view');


if (isset($_GET['delete'])) {
    check_permission('pengurus.delete');
    $nokta = $_GET['delete'];

    $stmtCheck = $pdo->prepare("SELECT r.nama_role FROM tabel_pengurus_jabatan j JOIN tabel_role r ON j.role_id = r.id_role WHERE j.nokta = ? LIMIT 1");
    $stmtCheck->execute([$nokta]);
    $target_role = $stmtCheck->fetchColumn();

    if ($_SESSION['user']['nama_role'] == 'Sekjend' && $target_role == 'Super Admin') {
        $_SESSION['error'] = "Akses Ditolak: Sekjend tidak diperbolehkan menghapus data Super Admin.";
        header("Location: data_pengurus.php");
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM tabel_pengurus WHERE nokta = ?");
    if ($stmt->execute([$nokta])) {
        $_SESSION['success'] = "Data pengurus berhasil dihapus dari sistem!";
        header("Location: data_pengurus.php");
        exit;
    }
}

$title = "Data Pengurus";
include '../layout/header.php';
include '../layout/sidebar.php';

$all_periods = $pdo->query("SELECT * FROM tabel_kepengurusan ORDER BY nama_periode DESC")->fetchAll();
$active_p = get_active_kepengurusan();

$filter_period = $_GET['period'] ?? ($active_p['id_kepengurusan'] ?? '');
$filter_angkatan = $_GET['angkatan'] ?? '';
$filter_biro = $_GET['biro'] ?? '';

$angkatans = $pdo->query("SELECT DISTINCT angkatan FROM tabel_pengurus WHERE angkatan IS NOT NULL ORDER BY angkatan DESC")->fetchAll(PDO::FETCH_COLUMN);

$stmtBiros = $pdo->prepare("SELECT * FROM tabel_biro WHERE kepengurusan_id = ? ORDER BY nama_biro ASC");
$stmtBiros->execute([$filter_period]);
$biro_options = $stmtBiros->fetchAll();

$message = '';
if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    unset($_SESSION['success']);
}

$error_msg = '';
if (isset($_SESSION['error'])) {
    $error_msg = $_SESSION['error'];
    unset($_SESSION['error']);
}

$params = [];
$query = "SELECT p.*, r.nama_role, b.nama_biro, d.nama_divisi, j.jabatan as jabatan_aktif
          FROM tabel_pengurus p 
          LEFT JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
          LEFT JOIN tabel_role r ON j.role_id = r.id_role 
          LEFT JOIN tabel_biro b ON j.biro_id = b.id_biro 
          LEFT JOIN tabel_divisi d ON j.divisi_id = d.id_divisi 
          WHERE 1=1";
$params[] = $filter_period;

if ($filter_angkatan) {
    $query .= " AND p.angkatan = ?";
    $params[] = $filter_angkatan;
}

if ($filter_biro !== '') {
    if ($filter_biro === 'bph') {
        $query .= " AND (j.biro_id IS NULL OR j.biro_id = 0)";
    } else {
        $query .= " AND j.biro_id = ?";
        $params[] = $filter_biro;
    }
}

$query .= " ORDER BY p.nokta ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$pengurus = $stmt->fetchAll();
?>

<div id="content" class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-800 text-brand-red mb-1">Manajemen Pengurus</h4>
        </div>
        <?php if (has_permission('pengurus.create')): ?>
        <a href="tambah_pengurus.php" class="btn btn-primary d-flex align-items-center shadow-sm">
            <i class="fas fa-plus-circle me-2"></i> Tambah Pengurus Baru
        </a>
        <?php endif; ?>
    </div>

    <div class="card border-0 shadow-sm mb-4 overflow-hidden">
        <div class="card-body p-4 bg-light-soft">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-800 text-muted text-uppercase ls-1">Periode Kepengurusan</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-calendar-alt text-primary"></i></span>
                        <select name="period" class="form-select border-start-0" onchange="this.form.submit()">
                            <?php foreach($all_periods as $ap): ?>
                            <option value="<?= $ap['id_kepengurusan'] ?>" <?= $ap['id_kepengurusan'] == $filter_period ? 'selected' : '' ?>>
                                <?= $ap['nama_periode'] ?> <?= $ap['status'] == 'aktif' ? '(Aktif)' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-800 text-muted text-uppercase ls-1">Struktur Biro</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-sitemap text-primary"></i></span>
                        <select name="biro" class="form-select border-start-0">
                            <option value="">-- Semua Biro --</option>
                            <option value="bph" <?= $filter_biro === 'bph' ? 'selected' : '' ?>>BPH / Tanpa Biro</option>
                            <?php foreach($biro_options as $bo): ?>
                            <option value="<?= $bo['id_biro'] ?>" <?= $filter_biro == $bo['id_biro'] ? 'selected' : '' ?>>
                                Biro <?= htmlspecialchars($bo['nama_biro']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-800 text-muted text-uppercase ls-1">Angkatan</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-graduation-cap text-primary"></i></span>
                        <select name="angkatan" class="form-select border-start-0">
                            <option value="">-- Semua Angkatan --</option>
                            <?php foreach($angkatans as $a): ?>
                            <option value="<?= $a ?>" <?= $a == $filter_angkatan ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4 d-flex gap-2 justify-content-md-end">
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">
                        <i class="fas fa-filter me-1"></i> Terapkan
                    </button>
                    <a href="data_pengurus.php" class="btn btn-light btn-sm px-4 border fw-bold text-muted">
                        <i class="fas fa-undo me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show d-flex align-items-center rounded-3 p-3 mb-4" role="alert">
            <i class="fas fa-check-circle fa-lg me-3"></i>
            <div><?= $message ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger border-0 shadow-sm alert-dismissible fade show d-flex align-items-center rounded-3 p-3 mb-4" role="alert">
            <i class="fas fa-exclamation-triangle fa-lg me-3"></i>
            <div><?= $error_msg ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-slate-50 text-muted small text-uppercase fw-bold">
                        <tr>
                            <th class="ps-4 py-3">No. KTA / Nama</th>
                            <th>Angkatan</th>
                            <th>Jabatan & Struktur</th>
                            <th>Role & Akses</th>
                            <?php if (in_array($_SESSION['user']['nama_role'], ['Super Admin', 'PPI'])): ?>
                            <th>Password</th>
                            <?php endif; ?>
                            <th class="text-center pe-4" style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pengurus): foreach($pengurus as $p): ?>
                        <tr class="modern-row">
                            <td class="ps-4">
                                <div class="d-flex align-items-center py-1">
                                    <div class="avatar-md me-3 bg-primary-soft text-primary rounded-circle d-flex align-items-center justify-content-center fw-800">
                                        <?= substr($p['nama'] ?? '-', 0, 1) ?>
                                    </div>
                                    <div>
                                        <div class="fw-800 text-dark mb-0"><?= $p['nama'] ?></div>
                                        <div class="text-muted small ls-1"><?= $p['nokta'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-muted border px-2 py-1"><?= $p['angkatan'] ?></span></td>
                            <td>
                                <?php if($p['jabatan_aktif']): ?>
                                    <div class="fw-600 text-dark small mb-1"><?= $p['jabatan_aktif'] ?></div>
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="badge bg-brand-green-soft text-brand-green px-2 py-1 rounded-pill" style="font-size: 0.65rem;">
                                            <i class="fas fa-at me-1"></i><?= $p['nama_biro'] ?? '-' ?>
                                        </span>
                                        <?php if($p['nama_divisi']): ?>
                                        <span class="text-muted small mx-1">/</span>
                                        <span class="text-muted" style="font-size: 0.7rem;"><?= $p['nama_divisi'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-danger-soft text-danger px-2 py-1 rounded-pill small">Demisioner</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($p['nama_role']): ?>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-user-shield text-primary-light me-2 small"></i>
                                        <span class="fw-600 text-muted small"><?= $p['nama_role'] ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <?php if (in_array($_SESSION['user']['nama_role'], ['Super Admin', 'PPI'])): ?>
                            <td>
                                <?php if ($p['nama_role'] === 'Super Admin' && $_SESSION['user']['nama_role'] !== 'Super Admin'): ?>
                                    <span class="badge bg-light text-muted border px-2 py-1 fw-bold" style="font-size: 0.75rem;" title="Password Super Admin Terlindungi">
                                        <i class="fas fa-lock me-1 text-danger opacity-75"></i>••••••••
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-primary border px-2 py-1 fw-800" style="font-size: 0.75rem;">
                                        <i class="fas fa-key me-1 opacity-50"></i><?= htmlspecialchars($p['password']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td class="text-center pe-4">
                                <div class="d-flex justify-content-center gap-1">
                                    <?php 
                                    $can_edit = !($p['nama_role'] === 'Super Admin' && $_SESSION['user']['nama_role'] !== 'Super Admin');
                                    if ($can_edit): 
                                    ?>
                                        <?php if (has_permission('pengurus.update')): ?>
                                        <a href="edit_pengurus.php?nokta=<?= $p['nokta'] ?>&context_period=<?= $filter_period ?>" 
                                            class="btn btn-icon btn-light-soft text-primary" title="Edit Data">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if (has_permission('pengurus.delete')): ?>
                                        <button class="btn btn-icon btn-light-soft text-danger" 
                                                onclick="if(confirm('Yakin ingin menghapus pengurus ini dari sistem secara permanen?')) window.location.href='data_pengurus.php?delete=<?= $p['nokta'] ?>'" 
                                                title="Hapus Permanen">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted opacity-50" title="Akun Super Admin Terproteksi"><i class="fas fa-lock me-1"></i> Terkunci</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="py-4">
                                    <i class="fas fa-users-slash fa-3x text-muted opacity-25 mb-3"></i>
                                    <h6 class="text-muted fw-bold">Belum ada data pengurus yang sesuai filter.</h6>
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

<style>
.bg-light-soft { background-color: #fcfcfd; }
.bg-slate-50 { background-color: #f8fafc; }
.bg-primary-soft { background-color: rgba(220, 38, 38, 0.1); }
.bg-brand-green-soft { background-color: #FEF2F2; }
.text-brand-green { color: #DC2626; }
.bg-danger-soft { background-color: #F0FDF4; }
.btn-light-soft { background-color: #f1f5f9; border: none; }
.btn-icon { width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 0.9rem; }
.avatar-md { width: 42px; height: 42px; border-radius: 12px; font-size: 1.1rem; }
.fw-800 { font-weight: 800; }
.modern-row:hover { background-color: #f9fafb; }
.ls-1 { letter-spacing: 0.5px; }
.rounded-4 { border-radius: 1.25rem !important; }
</style>

<?php include '../layout/footer.php'; ?>


