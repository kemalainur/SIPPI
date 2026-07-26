<?php
require_once '../config/database.php';
session_start();
check_login();

$active_p = get_active_kepengurusan();
if (!$active_p) {
    die("Error: Tidak ada Tahun Kepengurusan yang aktif.");
}

$stmtAktif = $pdo->prepare("SELECT * FROM tabel_periode WHERE status = 'aktif' AND kepengurusan_id = ? LIMIT 1");
$stmtAktif->execute([$active_p['id_kepengurusan']]);
$active = $stmtAktif->fetch();

if (!$active) {
    $title = "Penilaian Ditutup";
    include '../layout/header.php';
    include '../layout/sidebar.php';
    echo '<div id="content" class="text-center mt-5"><i class="fas fa-calendar-times fa-4x text-muted mb-3"></i><h4>Bulan Penilaian Belum Dibuka.</h4><p class="text-muted small">Silakan tunggu info dari PPI.</p></div>';
    include '../layout/footer.php';
    exit;
}

$bulan = $active['bulan'];
$tahun = $active['tahun'];
$me = $_SESSION['user']['nokta'];
$ppi_info = get_user_ppi_info($me, $active_p['id_kepengurusan']);

$user_role_name = $_SESSION['user']['nama_role'] ?? '';
$is_pjnas = in_array($user_role_name, ['PJnas', 'PJNas']);

if ($ppi_info['is_2023'] || $is_pjnas) {
    $title = "Akses Penilaian Dibatasi";
    include '../layout/header.php';
    include '../layout/sidebar.php';
    echo '<div id="content" class="text-center mt-5">
            <i class="fas fa-user-slash fa-4x text-muted mb-3"></i>
            <h4 class="fw-800">Akses Penilaian Dibatasi</h4>
            <p class="text-muted small">Pengurus Angkatan 2023 dan Role PJnas tidak diikutsertakan pada sistem evaluasi penilaian ini.</p>
            <a href="' . base_url('dashboard/dashboard.php') . '" class="btn btn-primary rounded-pill px-4 mt-3">Kembali ke Dashboard</a>
          </div>';
    include '../layout/footer.php';
    exit;
}

$mode_penilaian = $active['mode_penilaian'] ?? 'PPI';

if ($mode_penilaian === 'PPI') {
    // Mode Penilaian PPI (Bulanan): Hanya Tim PPI (dan Super Admin) yang dapat menilai
    if (!$ppi_info['is_ppi']) {
        $title = "Akses Penilaian Dibatasi";
        include '../layout/header.php';
        include '../layout/sidebar.php';
        echo '<div id="content" class="text-center mt-5">
                <i class="fas fa-shield-alt fa-4x text-muted mb-3"></i>
                <h4 class="fw-800">Mode Penilaian PPI (Bulanan)</h4>
                <p class="text-muted small">Pada periode ini, penilaian dilakukan secara internal oleh TIM PPI.</p>
                <p class="text-muted small">Menu Peer Assessment bagi pengurus umum akan aktif pada periode Triwulanan.</p>
                <a href="' . base_url('dashboard/dashboard.php') . '" class="btn btn-primary rounded-pill px-4 mt-3">Kembali ke Dashboard</a>
              </div>';
        include '../layout/footer.php';
        exit;
    }
} else {
    // Mode Peer Assessment (Triwulanan)
    $stmtCheckPenilai = $pdo->prepare("SELECT COUNT(*) FROM tabel_konfigurasi_penilaian 
                                       WHERE kepengurusan_id = ? AND bulan = ? AND tahun = ? AND tipe = 'penilai'");
    $stmtCheckPenilai->execute([$active_p['id_kepengurusan'], $bulan, $tahun]);
    $has_penilai_config = ($stmtCheckPenilai->fetchColumn() > 0);

    $is_allowed_to_assess = true;
    if ($has_penilai_config) {
        $stmtIsPenilai = $pdo->prepare("SELECT COUNT(*) FROM tabel_konfigurasi_penilaian 
                                         WHERE kepengurusan_id = ? AND bulan = ? AND tahun = ? AND nokta = ? AND tipe = 'penilai'");
        $stmtIsPenilai->execute([$active_p['id_kepengurusan'], $bulan, $tahun, $me]);
        $is_allowed_to_assess = ($stmtIsPenilai->fetchColumn() > 0);
    }

    if (!$is_allowed_to_assess) {
        $title = "Akses Penilaian Dibatasi";
        include '../layout/header.php';
        include '../layout/sidebar.php';
        echo '<div id="content" class="text-center mt-5">
                <i class="fas fa-user-slash fa-4x text-muted mb-3"></i>
                <h4>Akses Penilaian Dinonaktifkan</h4>
                <p class="text-muted small">Anda tidak diatur sebagai penilai pada periode penilaian bulan ini (' . $bulan . '/' . $tahun . ').</p>
                <p class="text-muted small">Silakan hubungi PPI jika menurut Anda ini adalah kesalahan.</p>
                <a href="' . base_url('dashboard/dashboard.php') . '" class="btn btn-primary rounded-pill px-4 mt-3">Kembali ke Dashboard</a>
              </div>';
        include '../layout/footer.php';
        exit;
    }
}

function get_members_to_rate($pdo, $active_p_id, $me, $bulan, $tahun, $active, $ppi_info) {
    $mode = $active['mode_penilaian'] ?? 'PPI';

    if ($mode === 'PPI') {
        if ($ppi_info['is_staff_pj'] && $ppi_info['biro_id']) {
            // Staff PPI (PJ Biro): Only see & evaluate pengurus in their assigned biro (exclude 2023 & Super Admin/PJnas)
            $stmt = $pdo->prepare("SELECT p.nokta, p.nama, j.jabatan, r.nama_role, b.nama_biro 
                                   FROM tabel_pengurus p 
                                   JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
                                   JOIN tabel_role r ON j.role_id = r.id_role
                                   LEFT JOIN tabel_biro b ON j.biro_id = b.id_biro
                                   LEFT JOIN tabel_penilaian tp ON p.nokta = tp.dinilai_nokta AND tp.penilai_nokta = ? AND tp.bulan = ? AND tp.tahun = ?
                                   WHERE r.nama_role NOT IN ('Super Admin', 'PJnas', 'PJNas') 
                                   AND (p.angkatan IS NULL OR p.angkatan != '2023')
                                   AND p.nokta != ?
                                   AND j.biro_id = ?
                                   AND tp.id_penilaian IS NULL
                                   ORDER BY p.nama ASC");
            $stmt->execute([$active_p_id, $me, $bulan, $tahun, $me, $ppi_info['biro_id']]);
        } else {
            // Kepala PPI or Super Admin: Can view & evaluate all pengurus across all biros (exclude 2023 & Super Admin/PJnas)
            $stmt = $pdo->prepare("SELECT p.nokta, p.nama, j.jabatan, r.nama_role, b.nama_biro 
                                   FROM tabel_pengurus p 
                                   JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
                                   JOIN tabel_role r ON j.role_id = r.id_role
                                   LEFT JOIN tabel_biro b ON j.biro_id = b.id_biro
                                   LEFT JOIN tabel_penilaian tp ON p.nokta = tp.dinilai_nokta AND tp.penilai_nokta = ? AND tp.bulan = ? AND tp.tahun = ?
                                   WHERE r.nama_role NOT IN ('Super Admin', 'PJnas', 'PJNas') 
                                   AND (p.angkatan IS NULL OR p.angkatan != '2023')
                                   AND p.nokta != ?
                                   AND tp.id_penilaian IS NULL
                                   ORDER BY b.nama_biro ASC, p.nama ASC");
            $stmt->execute([$active_p_id, $me, $bulan, $tahun, $me]);
        }
    } else {
        // Peer Assessment Mode
        $stmtCheckDinilai = $pdo->prepare("SELECT COUNT(*) FROM tabel_konfigurasi_penilaian 
                                           WHERE kepengurusan_id = ? AND bulan = ? AND tahun = ? AND tipe = 'dinilai'");
        $stmtCheckDinilai->execute([$active_p_id, $bulan, $tahun]);
        $has_dinilai_config = ($stmtCheckDinilai->fetchColumn() > 0);

        if ($has_dinilai_config) {
            $stmt = $pdo->prepare("SELECT p.nokta, p.nama, j.jabatan, r.nama_role, b.nama_biro 
                                   FROM tabel_pengurus p 
                                   JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
                                   JOIN tabel_role r ON j.role_id = r.id_role
                                   LEFT JOIN tabel_biro b ON j.biro_id = b.id_biro
                                   JOIN tabel_konfigurasi_penilaian kp ON p.nokta = kp.nokta AND kp.kepengurusan_id = ? AND kp.bulan = ? AND kp.tahun = ? AND kp.tipe = 'dinilai'
                                   LEFT JOIN tabel_penilaian tp ON p.nokta = tp.dinilai_nokta AND tp.penilai_nokta = ? AND tp.bulan = ? AND tp.tahun = ?
                                   WHERE r.nama_role NOT IN ('Super Admin', 'PJnas', 'PJNas') 
                                   AND (p.angkatan IS NULL OR p.angkatan != '2023')
                                   AND p.nokta != ?
                                   AND tp.id_penilaian IS NULL
                                   ORDER BY p.nama ASC");
            $stmt->execute([$active_p_id, $active_p_id, $bulan, $tahun, $me, $bulan, $tahun, $me]);
        } else {
            $stmt = $pdo->prepare("SELECT p.nokta, p.nama, j.jabatan, r.nama_role, b.nama_biro 
                                   FROM tabel_pengurus p 
                                   JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
                                   JOIN tabel_role r ON j.role_id = r.id_role
                                   LEFT JOIN tabel_biro b ON j.biro_id = b.id_biro
                                   LEFT JOIN tabel_penilaian tp ON p.nokta = tp.dinilai_nokta AND tp.penilai_nokta = ? AND tp.bulan = ? AND tp.tahun = ?
                                   WHERE r.nama_role NOT IN ('Super Admin', 'PJnas', 'PJNas') 
                                   AND (p.angkatan IS NULL OR p.angkatan != '2023')
                                   AND p.nokta != ?
                                   AND tp.id_penilaian IS NULL
                                   ORDER BY p.nama ASC");
            $stmt->execute([$active_p_id, $me, $bulan, $tahun, $me]);
        }
    }
    return $stmt->fetchAll();
}

$members = get_members_to_rate($pdo, $active_p['id_kepengurusan'], $me, $bulan, $tahun, $active, $ppi_info);

$indicators = $pdo->query("SELECT * FROM tabel_indikator ORDER BY kategori, id_indikator ASC")->fetchAll();

$title = "Beri Penilaian";
include '../layout/header.php';
include '../layout/sidebar.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dinilai_nokta = $_POST['dinilai_nokta'];
    $skors = $_POST['skor']; // Array: id_indikator => skor

    foreach ($skors as $indId => $score) {
        $stmt = $pdo->prepare("INSERT INTO tabel_penilaian (penilai_nokta, dinilai_nokta, indikator_id, skor, bulan, tahun) 
                               VALUES (?, ?, ?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE skor = ?");
        $stmt->execute([$me, $dinilai_nokta, $indId, $score, $bulan, $tahun, $score]);
    }
    
    update_kpi_member($dinilai_nokta, $bulan, $tahun, $active_p['id_kepengurusan']);

    $message = "Penilaian untuk " . $_POST['nama_dinilai'] . " berhasil disimpan!";
    
    $members = get_members_to_rate($pdo, $active_p['id_kepengurusan'], $me, $bulan, $tahun, $active, $ppi_info);
}
?>

<div id="content" class="fade-in">
    <div class="mb-4 d-flex justify-content-between align-items-center g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-brand-red mb-1">Evaluasi Rekan Pengurus</h4>
            <div class="small text-muted d-flex align-items-center gap-2 flex-wrap mt-1">
                <span>Periode: <span class="badge bg-primary px-2 rounded-pill"><?= $active['bulan'] ?>/<?= $active['tahun'] ?></span></span>
                <span>Mode: <span class="badge bg-danger px-2 rounded-pill"><?= htmlspecialchars($active['mode_penilaian'] ?? 'PPI') ?> (<?= htmlspecialchars($active['jenis_periode'] ?? 'Bulanan') ?>)</span></span>
                <?php if ($mode_penilaian === 'PPI' && $ppi_info['is_staff_pj'] && $ppi_info['nama_biro']): ?>
                    <span>Biro Tanggung Jawab: <span class="badge bg-dark px-2 rounded-pill"><?= htmlspecialchars($ppi_info['nama_biro']) ?></span></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-end">
            <span class="badge bg-rose-soft text-rose px-3 py-2 rounded-pill fw-bold small">
                <i class="fas fa-info-circle me-1"></i> Penilaian bersifat rahasia
            </span>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show d-flex align-items-center rounded-3 p-3 mb-4" role="alert">
            <i class="fas fa-check-circle fa-lg me-3"></i>
            <div><?= $message ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                <div class="card-header bg-white border-0 py-3 px-4 d-flex align-items-center text-dark">
                    <h6 class="mb-0 fw-800">Daftar Pengurus</h6>
                    <span class="badge bg-light text-muted ms-auto rounded-pill"><?= count($members) ?> Orang</span>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush border-top border-light overflow-auto" id="memberList" style="max-height: 600px;">
                        <?php if($members): foreach($members as $m): 
                            $initials = strtoupper(substr($m['nama'] ?? '-', 0, 1));
                        ?>
                        <button type="button" class="list-group-item list-group-item-action modern-row py-3 px-4 d-flex align-items-center border-light member-btn" 
                                onclick="selectMember(this, '<?= $m['nokta'] ?>', '<?= addslashes($m['nama']) ?>', '<?= $m['jabatan'] ?>')">
                            <div class="avatar-sm me-3 bg-slate-100 text-muted rounded-circle d-flex align-items-center justify-content-center fw-800 position-relative border" style="width: 42px; height: 42px; min-width: 42px;">
                                <?= $initials ?>
                            </div>
                            <div class="flex-grow-1 min-width-0 text-start">
                                <div class="fw-800 text-dark text-truncate mb-0" style="font-size: 0.95rem;"><?= $m['nama'] ?></div>
                                <div class="text-muted small fw-600 opacity-75 text-truncate"><?= $m['jabatan'] ?></div>
                            </div>
                        </button>
                        <?php endforeach; else: ?>
                            <div class="p-5 text-center">
                                <i class="fas fa-check-double fa-3x text-brand-green opacity-25 mb-3"></i>
                                <p class="text-muted small fw-600 mb-0">Tugas Selesai!<br>Semua pengurus sudah Anda nilai bulan ini.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <?php if (!$members): ?>
                <div class="card border-0 shadow-sm rounded-4 h-100 d-flex align-items-center justify-content-center text-center p-5 bg-white">
                    <div class="p-4 text-dark text-center w-100">
                        <div class="stats-icon bg-brand-green-soft text-brand-green mx-auto mb-4" style="width: 100px; height: 100px; border-radius: 30px;">
                            <i class="fas fa-medal fa-4x"></i>
                        </div>
                        <h3 class="fw-800 text-brand-green">Luar Biasa!</h3>
                        <p class="text-muted px-lg-5 fs-5">Anda telah menyelesaikan seluruh tugas penilaian untuk bulan ini. Terima kasih atas partisipasi Anda!</p>
                        <a href="<?= base_url('dashboard/dashboard.php') ?>" class="btn btn-brand-green rounded-pill px-5 py-3 fw-800 mt-4 shadow-lg">
                            Kembali ke Dashboard <i class="fas fa-home ms-2"></i>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div id="selectPlaceholder" class="card border-4 border-dashed border-light rounded-4 h-100 d-flex align-items-center justify-content-center text-center p-5 bg-white opacity-75 <?= isset($_POST['dinilai_nokta']) ? 'd-none' : '' ?>">
                    <div class="p-4 text-dark text-center w-100">
                        <div class="stats-icon bg-light text-muted mx-auto mb-4" style="width: 80px; height: 80px; border-radius: 25px;">
                            <i class="fas fa-user-edit fa-3x"></i>
                        </div>
                        <h5 class="fw-800">Siap Menilai?</h5>
                        <p class="text-muted px-lg-5">Pilih salah satu rekan pengurus di sebelah kiri untuk mulai memberikan penilaian periode ini.</p>
                    </div>
                </div>

                <div id="ratingFormArea" class="card border-0 shadow-sm rounded-4 overflow-hidden <?= isset($_POST['dinilai_nokta']) && count($members) > 0 ? '' : 'd-none' ?>">
                <form method="POST">
                    <div class="card-header bg-white border-0 py-4 px-4 border-bottom border-light">
                        <div class="d-flex align-items-center text-dark">
                            <div id="targetAvatar" class="avatar-lg me-3 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-800 shadow-sm" style="width: 60px; height: 60px; font-size: 1.5rem;">
                                <?= isset($_POST['nama_dinilai']) ? strtoupper(substr($_POST['nama_dinilai'], 0, 1)) : '?' ?>
                            </div>
                            <div>
                                <h5 id="targetName" class="fw-800 text-dark mb-1"><?= $_POST['nama_dinilai'] ?? '-' ?></h5>
                                <span id="targetJabatan" class="badge bg-slate-100 text-muted px-2 py-1 rounded-pill small fw-bold"><?= $_POST['jabatan_dinilai'] ?? 'Jabatan' ?></span>
                                <input type="hidden" name="dinilai_nokta" id="targetNokta" value="<?= $_POST['dinilai_nokta'] ?? '' ?>">
                                <input type="hidden" name="nama_dinilai" id="targetNameInput" value="<?= $_POST['nama_dinilai'] ?? '' ?>">
                                <input type="hidden" name="jabatan_dinilai" id="targetJabatanInput" value="<?= $_POST['jabatan_dinilai'] ?? '' ?>">
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4 bg-white">
                        <?php foreach(['attitude' => 'Attitude', 'komunikasi' => 'Komunikasi'] as $cat => $label): ?>
                            <h6 class="fw-800 text-muted small text-uppercase ls-2 mb-4 ms-1 mt-<?= $cat == 'komunikasi' ? '5' : '0' ?>"><?= $label ?></h6>
                            
                            <?php foreach($indicators as $i): if($i['kategori'] == $cat): ?>
                                <div class="mb-4 p-3 rounded-4 bg-slate-50 border border-light-soft rating-row">
                                    <div class="fw-800 text-dark mb-3" style="font-size: 1rem;"><?= $i['nama_indikator'] ?></div>
                                    
                                    <div class="d-flex justify-content-between gap-2 radio-group">
                                        <?php for($s=1; $s<=4; $s++): 
                                            $desc = ['', 'Kurang', 'Cukup', 'Baik', 'Sangat Baik'];
                                        ?>
                                            <div class="rating-option flex-fill">
                                                <input type="radio" class="btn-check" name="skor[<?= $i['id_indikator'] ?>]" 
                                                       id="s_<?= $i['id_indikator'] ?>_<?= $s ?>" value="<?= $s ?>" required>
                                                <label class="btn btn-outline-light w-100 py-3 rounded-3 border-0 bg-white shadow-sm d-flex flex-column align-items-center h-100 transition-all rating-label text-dark" for="s_<?= $i['id_indikator'] ?>_<?= $s ?>">
                                                    <span class="fw-800 mb-1" style="font-size: 1.25rem;"><?= $s ?></span>
                                                    <span class="very-small text-muted fw-bold text-uppercase opacity-75 d-none d-md-block" style="font-size: 0.6rem;"><?= $desc[$s] ?></span>
                                                </label>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            <?php endif; endforeach; ?>
                        <?php endforeach; ?>
                        
                        <div class="mt-5">
                            <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 fw-800 shadow-sm w-100">
                                <i class="fas fa-save me-2"></i> Simpan Penilaian
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function selectMember(el, nokta, nama, jabatan) {
    document.querySelectorAll('.member-btn').forEach(btn => btn.classList.remove('active', 'bg-brand-red-soft', 'border-brand-red'));
    el.classList.add('active', 'bg-brand-red-soft', 'border-brand-red');
    
    document.getElementById('selectPlaceholder').classList.add('d-none');
    const ratingCard = document.getElementById('ratingFormArea');
    ratingCard.classList.remove('d-none');
    ratingCard.classList.add('fade-in');
    
    document.getElementById('targetNokta').value = nokta;
    document.getElementById('targetName').innerText = nama;
    document.getElementById('targetNameInput').value = nama;
    document.getElementById('targetJabatan').innerText = jabatan;
    document.getElementById('targetJabatanInput').value = jabatan;
    document.getElementById('targetAvatar').innerText = nama.charAt(0).toUpperCase();
    
    const radios = ratingCard.querySelectorAll('input[type="radio"]');
    radios.forEach(r => r.checked = false);
    
    if(window.innerWidth < 992) {
        ratingCard.scrollIntoView({ behavior: 'smooth' });
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}
</script>

<style>
.bg-rose-soft { background-color: #F0FDF4; color: #16A34A; }
.text-rose { color: #16A34A; }
.bg-brand-red-soft { background-color: #FEF2F2 !important; color: var(--primary) !important; }
.bg-brand-green-soft { background-color: #F0FDF4 !important; color: var(--secondary) !important; }
.bg-slate-50 { background-color: #f8fafc; }
.bg-slate-100 { background-color: #f1f5f9; }
.stats-icon { display: flex; align-items: center; justify-content: center; }
.ls-2 { letter-spacing: 1.5px; }
.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.modern-row:hover { background-color: #f8f9fa; }
.rounded-4 { border-radius: 1.5rem !important; }
.rating-label { border: 1px solid transparent !important; }
.rating-label:hover { background-color: #f8fafc !important; transform: translateY(-3px); border-color: #e2e8f0 !important; }
.btn-check:checked + .rating-label { 
    background-color: var(--primary) !important; 
    color: white !important; 
    box-shadow: 0 10px 15px -3px rgba(220, 38, 38, 0.3) !important;
}
.btn-check:checked + .rating-label .text-muted { color: rgba(255, 255, 255, 0.8) !important; }
.rating-row { transition: all 0.3s ease; }
.rating-row:hover { border-color: var(--primary-light) !important; background-color: #fff !important; }
.very-small { font-size: 0.65rem; }
.transition-all { transition: all 0.2s ease; }
</style>

<?php include '../layout/footer.php'; ?>


