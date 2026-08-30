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

$bulan = (int)$active['bulan'];
$tahun = (int)$active['tahun'];
$me = $_SESSION['user']['nokta'];
$ppi_info = get_user_ppi_info($me, $active_p['id_kepengurusan']);
$is_kepala_or_admin = !empty($ppi_info['is_kepala']) || !empty($ppi_info['is_super_admin']);

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

// Pengecualian: PJNas dan Pengurus Angkatan Non-Aktif (2023) termasuk Super Admin non-aktif tidak ikut menilai
if ($ppi_info['is_2023'] || in_array($_SESSION['user']['nama_role'] ?? '', ['PJnas', 'PJNas'])) {
    $title = "Akses Penilaian Dikecualikan";
    include '../layout/header.php';
    include '../layout/sidebar.php';
    echo '<div id="content" class="text-center mt-5">
            <i class="fas fa-user-slash fa-4x text-muted mb-3"></i>
            <h4 class="fw-800">Partisipasi Penilaian Dikecualikan</h4>
            <p class="text-muted small">Akun Anda (PJNas / Pengurus Angkatan Non-Aktif) dikecualikan dari pengisian modul penilaian kinerja.</p>
            <a href="' . base_url('dashboard/dashboard.php') . '" class="btn btn-primary rounded-pill px-4 mt-3">Kembali ke Dashboard</a>
          </div>';
    include '../layout/footer.php';
    exit;
}

if ($mode_penilaian === 'PPI') {
    // Mode Penilaian PPI (Bulanan): Hanya Tim PPI (dan Super Admin aktif) yang dapat menilai
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

/**
 * Mengambil daftar pengurus yang dapat dinilai dengan informasi status dan penilai
 */
function get_members_to_rate($pdo, $active_p_id, $me, $bulan, $tahun, $active, $ppi_info) {
    $mode = $active['mode_penilaian'] ?? 'PPI';

    if ($mode === 'PPI') {
        if ($ppi_info['is_staff_pj'] && $ppi_info['biro_id']) {
            // Staff PPI (PJ Biro): Pengurus di biro binaan yang dinilai oleh PJ Biro ini ($me)
            $stmt = $pdo->prepare("SELECT p.nokta, p.nama, j.jabatan, r.nama_role, b.nama_biro, b.id_biro,
                                          COUNT(tp.id_penilaian) as total_skor_terisi,
                                          CASE WHEN COUNT(tp.id_penilaian) > 0 THEN 1 ELSE 0 END AS is_rated,
                                          (SELECT penilai.nama FROM tabel_penilaian tp2 
                                           JOIN tabel_pengurus penilai ON tp2.penilai_nokta = penilai.nokta 
                                           WHERE tp2.dinilai_nokta = p.nokta AND tp2.penilai_nokta = ? AND tp2.bulan = ? AND tp2.tahun = ? LIMIT 1) as penilai_nama
                                   FROM tabel_pengurus p 
                                   JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
                                   JOIN tabel_role r ON j.role_id = r.id_role
                                   LEFT JOIN tabel_biro b ON j.biro_id = b.id_biro
                                   LEFT JOIN tabel_penilaian tp ON p.nokta = tp.dinilai_nokta AND tp.penilai_nokta = ? AND tp.bulan = ? AND tp.tahun = ?
                                   WHERE r.nama_role NOT IN ('Super Admin', 'PJnas', 'PJNas') 
                                   AND (p.angkatan IS NULL OR p.angkatan != '2023')
                                   AND p.nokta != ?
                                   AND j.biro_id = ?
                                   GROUP BY p.nokta, p.nama, j.jabatan, r.nama_role, b.nama_biro, b.id_biro
                                   ORDER BY is_rated ASC, p.nama ASC");
            $stmt->execute([$me, $bulan, $tahun, $active_p_id, $me, $bulan, $tahun, $me, $ppi_info['biro_id']]);
        } else {
            // Kepala PPI / Super Admin: Seluruh pengurus lintas biro (cek status penilaian dari PJ Biro / Kepala PPI)
            $stmt = $pdo->prepare("SELECT p.nokta, p.nama, j.jabatan, r.nama_role, b.nama_biro, b.id_biro,
                                          COUNT(tp.id_penilaian) as total_skor_terisi,
                                          CASE WHEN COUNT(tp.id_penilaian) > 0 THEN 1 ELSE 0 END AS is_rated,
                                          (SELECT penilai.nama FROM tabel_penilaian tp2 
                                           JOIN tabel_pengurus penilai ON tp2.penilai_nokta = penilai.nokta 
                                           WHERE tp2.dinilai_nokta = p.nokta AND tp2.bulan = ? AND tp2.tahun = ? LIMIT 1) as penilai_nama
                                   FROM tabel_pengurus p 
                                   JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
                                   JOIN tabel_role r ON j.role_id = r.id_role
                                   LEFT JOIN tabel_biro b ON j.biro_id = b.id_biro
                                   LEFT JOIN tabel_penilaian tp ON p.nokta = tp.dinilai_nokta AND tp.bulan = ? AND tp.tahun = ?
                                   WHERE r.nama_role NOT IN ('Super Admin', 'PJnas', 'PJNas') 
                                   AND (p.angkatan IS NULL OR p.angkatan != '2023')
                                   AND p.nokta != ?
                                   GROUP BY p.nokta, p.nama, j.jabatan, r.nama_role, b.nama_biro, b.id_biro
                                   ORDER BY is_rated ASC, b.nama_biro ASC, p.nama ASC");
            $stmt->execute([$bulan, $tahun, $active_p_id, $bulan, $tahun, $me]);
        }
    } else {
        // Mode Peer Assessment (Triwulan)
        $stmtCheckDinilai = $pdo->prepare("SELECT COUNT(*) FROM tabel_konfigurasi_penilaian 
                                           WHERE kepengurusan_id = ? AND bulan = ? AND tahun = ? AND tipe = 'dinilai'");
        $stmtCheckDinilai->execute([$active_p_id, $bulan, $tahun]);
        $has_dinilai_config = ($stmtCheckDinilai->fetchColumn() > 0);

        if ($has_dinilai_config) {
            $stmt = $pdo->prepare("SELECT p.nokta, p.nama, j.jabatan, r.nama_role, b.nama_biro, b.id_biro,
                                          COUNT(tp.id_penilaian) as total_skor_terisi,
                                          CASE WHEN COUNT(tp.id_penilaian) > 0 THEN 1 ELSE 0 END AS is_rated,
                                          '' as penilai_nama
                                   FROM tabel_pengurus p 
                                   JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
                                   JOIN tabel_role r ON j.role_id = r.id_role
                                   LEFT JOIN tabel_biro b ON j.biro_id = b.id_biro
                                   JOIN tabel_konfigurasi_penilaian kp ON p.nokta = kp.nokta AND kp.kepengurusan_id = ? AND kp.bulan = ? AND kp.tahun = ? AND kp.tipe = 'dinilai'
                                   LEFT JOIN tabel_penilaian tp ON p.nokta = tp.dinilai_nokta AND tp.penilai_nokta = ? AND tp.bulan = ? AND tp.tahun = ?
                                   WHERE r.nama_role NOT IN ('Super Admin', 'PJnas', 'PJNas') 
                                   AND (p.angkatan IS NULL OR p.angkatan != '2023')
                                   AND p.nokta != ?
                                   GROUP BY p.nokta, p.nama, j.jabatan, r.nama_role, b.nama_biro, b.id_biro
                                   ORDER BY is_rated ASC, p.nama ASC");
            $stmt->execute([$active_p_id, $active_p_id, $bulan, $tahun, $me, $bulan, $tahun, $me]);
        } else {
            $stmt = $pdo->prepare("SELECT p.nokta, p.nama, j.jabatan, r.nama_role, b.nama_biro, b.id_biro,
                                          COUNT(tp.id_penilaian) as total_skor_terisi,
                                          CASE WHEN COUNT(tp.id_penilaian) > 0 THEN 1 ELSE 0 END AS is_rated,
                                          '' as penilai_nama
                                   FROM tabel_pengurus p 
                                   JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
                                   JOIN tabel_role r ON j.role_id = r.id_role
                                   LEFT JOIN tabel_biro b ON j.biro_id = b.id_biro
                                   LEFT JOIN tabel_penilaian tp ON p.nokta = tp.dinilai_nokta AND tp.penilai_nokta = ? AND tp.bulan = ? AND tp.tahun = ?
                                   WHERE r.nama_role NOT IN ('Super Admin', 'PJnas', 'PJNas') 
                                   AND (p.angkatan IS NULL OR p.angkatan != '2023')
                                   AND p.nokta != ?
                                   GROUP BY p.nokta, p.nama, j.jabatan, r.nama_role, b.nama_biro, b.id_biro
                                   ORDER BY is_rated ASC, p.nama ASC");
            $stmt->execute([$active_p_id, $me, $bulan, $tahun, $me]);
        }
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$message = '';
$selected_nokta_after_post = '';

// =========================================================================
// HANDLER FORM PENILAIAN (SIMPAN & EDIT NILAI)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dinilai_nokta'])) {
    if ($active['status'] !== 'aktif') {
        die("Error: Periode penilaian sudah ditutup. Perubahan nilai tidak diizinkan.");
    }

    $dinilai_nokta = trim($_POST['dinilai_nokta']);
    $skors = $_POST['skor'] ?? []; // Array: id_indikator => skor (1-4)
    $action_type = $_POST['action_type'] ?? 'save';
    $nama_dinilai = $_POST['nama_dinilai'] ?? 'Pengurus';

    if (!empty($dinilai_nokta) && !empty($skors)) {
        try {
            $pdo->beginTransaction();

            $stmtSave = $pdo->prepare("INSERT INTO tabel_penilaian 
                                        (penilai_nokta, dinilai_nokta, indikator_id, skor, bulan, tahun) 
                                       VALUES (?, ?, ?, ?, ?, ?)
                                       ON DUPLICATE KEY UPDATE skor = ?");

            foreach ($skors as $indId => $score) {
                $scoreVal = max(1, min(4, (int)$score));
                $stmtSave->execute([$me, $dinilai_nokta, (int)$indId, $scoreVal, $bulan, $tahun, $scoreVal]);
            }

            $pdo->commit();

            // Auto Trigger Kalkulasi Nilai KPI
            update_kpi_member($dinilai_nokta, $bulan, $tahun, $active_p['id_kepengurusan']);

            $aksi_text = ($action_type === 'edit') ? 'diperbarui' : 'disimpan';
            $message = "Nilai evaluasi untuk <strong>" . htmlspecialchars($nama_dinilai) . "</strong> berhasil {$aksi_text}!";
            $selected_nokta_after_post = $dinilai_nokta;

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Gagal menyimpan penilaian: " . $e->getMessage();
        }
    }
}

// Data Pengurus & Indikator
$members = get_members_to_rate($pdo, $active_p['id_kepengurusan'], $me, $bulan, $tahun, $active, $ppi_info);
$indicators = $pdo->query("SELECT * FROM tabel_indikator ORDER BY kategori, id_indikator ASC")->fetchAll();

// Ambil riwayat skor untuk pre-fill form
if ($mode_penilaian === 'PPI' && $is_kepala_or_admin) {
    // Kepala PPI: ambil skor di periode ini (prioritaskan skor Kepala PPI jika ada, jika tidak ambil skor PJ Biro)
    $stmtExisting = $pdo->prepare("SELECT dinilai_nokta, indikator_id, skor, penilai_nokta 
                                   FROM tabel_penilaian 
                                   WHERE bulan = ? AND tahun = ?
                                   ORDER BY (penilai_nokta = ?) DESC");
    $stmtExisting->execute([$bulan, $tahun, $me]);
    $raw_scores = $stmtExisting->fetchAll(PDO::FETCH_ASSOC);
    $existing_scores = [];
    foreach ($raw_scores as $row) {
        if (!isset($existing_scores[$row['dinilai_nokta']][$row['indikator_id']])) {
            $existing_scores[$row['dinilai_nokta']][$row['indikator_id']] = (int)$row['skor'];
        }
    }
} else {
    $stmtExisting = $pdo->prepare("SELECT dinilai_nokta, indikator_id, skor 
                                   FROM tabel_penilaian 
                                   WHERE penilai_nokta = ? AND bulan = ? AND tahun = ?");
    $stmtExisting->execute([$me, $bulan, $tahun]);
    $raw_scores = $stmtExisting->fetchAll(PDO::FETCH_ASSOC);
    $existing_scores = [];
    foreach ($raw_scores as $row) {
        $existing_scores[$row['dinilai_nokta']][$row['indikator_id']] = (int)$row['skor'];
    }
}

// Data Monitoring Per Biro khusus Kepala PPI / Super Admin di Mode PPI
$biro_summary = [];
$all_biros = [];
if ($mode_penilaian === 'PPI' && $is_kepala_or_admin) {
    $stmtBiros = $pdo->prepare("SELECT id_biro, nama_biro FROM tabel_biro WHERE kepengurusan_id = ? ORDER BY nama_biro ASC");
    $stmtBiros->execute([$active_p['id_kepengurusan']]);
    $all_biros = $stmtBiros->fetchAll();

    foreach ($all_biros as $b) {
        $stmtTot = $pdo->prepare("SELECT p.nokta, p.nama, j.jabatan,
                                         (SELECT COUNT(*) FROM tabel_penilaian WHERE dinilai_nokta = p.nokta AND bulan = ? AND tahun = ?) as is_rated_count
                                  FROM tabel_pengurus_jabatan j 
                                  JOIN tabel_pengurus p ON j.nokta = p.nokta 
                                  JOIN tabel_role r ON j.role_id = r.id_role 
                                  WHERE j.kepengurusan_id = ? AND j.biro_id = ? 
                                  AND r.nama_role NOT IN ('Super Admin', 'PJnas', 'PJNas') 
                                  AND (p.angkatan IS NULL OR p.angkatan != '2023')
                                  ORDER BY p.nama ASC");
        $stmtTot->execute([$bulan, $tahun, $active_p['id_kepengurusan'], $b['id_biro']]);
        $biro_members = $stmtTot->fetchAll(PDO::FETCH_ASSOC);
        
        $tot_m = count($biro_members);
        $unrated_members = [];
        $done_m = 0;
        foreach ($biro_members as $bm) {
            if ($bm['is_rated_count'] > 0) {
                $done_m++;
            } else {
                $unrated_members[] = [
                    'nokta' => $bm['nokta'],
                    'nama' => $bm['nama'],
                    'jabatan' => $bm['jabatan']
                ];
            }
        }

        // Cari nama PJ Biro
        $stmtPJ = $pdo->prepare("SELECT p.nama, j.jabatan FROM tabel_pengurus_jabatan j 
                                 JOIN tabel_pengurus p ON j.nokta = p.nokta 
                                 WHERE j.kepengurusan_id = ? AND j.biro_id = ? AND (j.role_id = 4 OR LOWER(j.jabatan) LIKE '%pj%') LIMIT 1");
        $stmtPJ->execute([$active_p['id_kepengurusan'], $b['id_biro']]);
        $pj_info = $stmtPJ->fetch();

        $biro_summary[$b['id_biro']] = [
            'id_biro' => $b['id_biro'],
            'nama_biro' => $b['nama_biro'],
            'pj_nama' => $pj_info['nama'] ?? 'Belum ditentukan',
            'total' => $tot_m,
            'sudah' => $done_m,
            'belum' => count($unrated_members),
            'unrated_members' => $unrated_members,
            'persen' => $tot_m > 0 ? round(($done_m / $tot_m) * 100, 1) : 100
        ];
    }
}

$is_periode_closed = ($active['status'] !== 'aktif');

$title = "Evaluasi Penilaian Pengurus";
include '../layout/header.php';
include '../layout/sidebar.php';
?>

<div id="content" class="fade-in">
    <!-- HEADER UTAMA CLEAN & PROFESIONAL -->
    <div class="mb-4 d-flex justify-content-between align-items-center g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-dark mb-1">Evaluasi Penilaian Pengurus</h4>
            <div class="text-muted small d-flex align-items-center gap-2 flex-wrap">
                <span>Periode: <strong class="text-dark fw-bold"><?= $bulan ?>/<?= $tahun ?></strong></span>
                <span class="text-muted opacity-25">|</span>
                <span>Mode: <strong class="text-dark fw-bold"><?= htmlspecialchars($active['mode_penilaian'] ?? 'PPI') ?> (<?= htmlspecialchars($active['jenis_periode'] ?? 'Bulanan') ?>)</strong></span>
                <?php if ($mode_penilaian === 'PPI' && $ppi_info['is_staff_pj'] && $ppi_info['nama_biro']): ?>
                    <span class="text-muted opacity-25">|</span>
                    <span>Biro: <strong class="text-dark fw-bold"><?= htmlspecialchars($ppi_info['nama_biro']) ?></strong></span>
                <?php elseif ($mode_penilaian === 'PPI' && $is_kepala_or_admin): ?>
                    <span class="text-muted opacity-25">|</span>
                    <span>Akses: <strong class="text-dark fw-bold">Kepala PPI</strong></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-end">
            <span class="text-muted small fw-600">
                <i class="fas fa-lock me-1 text-muted opacity-75"></i> Penilaian Rahasia
            </span>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show d-flex align-items-center rounded-3 p-3 mb-4" role="alert">
            <i class="fas fa-check-circle fa-lg me-3 text-success"></i>
            <div class="fw-600"><?= $message ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- WIDGET RINGKASAN PJ BIRO (CLEAN & MINIMALIST) -->
    <?php if (!empty($biro_summary)): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
            <div class="card-header bg-white border-0 py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom border-light">
                <div class="d-flex align-items-center">
                    <h6 class="mb-0 fw-800 text-dark">
                        <i class="fas fa-chart-pie me-2 text-muted"></i>Status Penilaian PJ Biro
                    </h6>
                </div>
                <span class="text-muted very-small">Klik kartu biro untuk memfilter daftar pengurus</span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <?php foreach($biro_summary as $bs): 
                        $is_complete = ($bs['total'] > 0 && $bs['sudah'] >= $bs['total']);
                    ?>
                        <div class="col-md-4 col-sm-6">
                            <div class="p-3 rounded-3 border biro-card-filter bg-white <?= $is_complete ? 'border-success-subtle' : 'border-light-subtle' ?>" 
                                 style="cursor: pointer; transition: all 0.2s ease;"
                                 onclick="filterByBiroCard(<?= $bs['id_biro'] ?>)">
                                <div class="d-flex justify-content-between align-items-baseline mb-1">
                                    <span class="fw-800 text-dark" style="font-size: 0.95rem;"><?= htmlspecialchars($bs['nama_biro']) ?></span>
                                    <span class="fw-800 <?= $is_complete ? 'text-success' : 'text-dark' ?>" style="font-size: 1rem;">
                                        <?= $bs['persen'] ?>%
                                    </span>
                                </div>
                                <div class="text-muted mb-2 text-truncate" style="font-size: 0.78rem;">
                                    PJ: <span class="text-dark fw-600"><?= htmlspecialchars($bs['pj_nama']) ?></span>
                                </div>
                                <div class="progress mb-2 bg-slate-100" style="height: 5px; border-radius: 4px;">
                                    <div class="progress-bar <?= $is_complete ? 'bg-success' : 'bg-dark' ?>" role="progressbar" style="width: <?= $bs['persen'] ?>%"></div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center very-small mt-2 pt-2 border-top border-light">
                                    <span class="text-muted fw-600">Sudah: <strong class="text-dark"><?= $bs['sudah'] ?></strong> / <?= $bs['total'] ?></span>
                                    <?php if ($bs['belum'] > 0): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 rounded-pill fw-bold" style="font-size: 0.7rem; border-width: 1px;"
                                                onclick='openUnratedModal("<?= addslashes($bs['nama_biro']) ?>", <?= htmlspecialchars(json_encode($bs['unrated_members']), ENT_QUOTES, 'UTF-8') ?>, <?= $bs['id_biro'] ?>); event.stopPropagation();'>
                                            Belum: <?= $bs['belum'] ?> <i class="fas fa-chevron-right ms-1 very-small opacity-50"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-success fw-bold"><i class="fas fa-check me-1"></i>Selesai</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- PANEL KIRI: DAFTAR PENGURUS -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 bg-white">
                <div class="card-header bg-white border-0 py-3 px-3 border-bottom border-light">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="mb-0 fw-800 text-dark">Daftar Pengurus</h6>
                        <span id="memberCountBadge" class="badge bg-light text-muted rounded-pill fw-bold"><?= count($members) ?> Orang</span>
                    </div>

                    <!-- Filter Bar & Search -->
                    <div class="row g-2">
                        <?php if (!empty($all_biros)): ?>
                            <div class="col-6">
                                <select id="filterBiroSelect" class="form-select form-select-sm bg-light border-0 fw-600 text-muted" onchange="applyAllFilters()">
                                    <option value="all">Semua Biro</option>
                                    <?php foreach($all_biros as $b): ?>
                                        <option value="<?= $b['id_biro'] ?>">Biro <?= htmlspecialchars($b['nama_biro']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <select id="filterStatusSelect" class="form-select form-select-sm bg-light border-0 fw-600 text-muted" onchange="applyAllFilters()">
                                    <option value="all">Semua Status</option>
                                    <option value="belum">Belum Dinilai</option>
                                    <option value="sudah">Sudah Dinilai</option>
                                </select>
                            </div>
                        <?php else: ?>
                            <div class="col-12">
                                <select id="filterStatusSelect" class="form-select form-select-sm bg-light border-0 fw-600 text-muted" onchange="applyAllFilters()">
                                    <option value="all">Semua Status</option>
                                    <option value="belum">Belum Dinilai</option>
                                    <option value="sudah">Sudah Dinilai</option>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="col-12">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" id="searchMember" class="form-control bg-light border-0" placeholder="Cari nama..." onkeyup="applyAllFilters()">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LIST ITEM PENGURUS (INTERAKTIF & LANGSUNG KLIK) -->
                <div class="card-body p-0">
                    <div class="list-group list-group-flush overflow-auto" id="memberList" style="max-height: 600px;">
                        <?php if(!empty($members)): foreach($members as $m): 
                            $initials = strtoupper(substr($m['nama'] ?? '-', 0, 1));
                            $is_rated = ($m['is_rated'] == 1);
                            $member_scores = isset($existing_scores[$m['nokta']]) ? json_encode($existing_scores[$m['nokta']]) : '{}';
                        ?>
                        <div class="list-group-item list-group-item-action modern-member-row py-3 px-3 d-flex align-items-center justify-content-between border-light member-item <?= $selected_nokta_after_post === $m['nokta'] ? 'active-member-row' : '' ?>" 
                             id="item_<?= $m['nokta'] ?>"
                             data-nokta="<?= $m['nokta'] ?>"
                             data-name="<?= strtolower(htmlspecialchars($m['nama'])) ?>"
                             data-jabatan="<?= strtolower(htmlspecialchars($m['jabatan'])) ?>"
                             data-biro="<?= $m['id_biro'] ?? '' ?>"
                             data-rated="<?= $is_rated ? 'sudah' : 'belum' ?>"
                             onclick='selectMember(this, "<?= $m['nokta'] ?>", "<?= addslashes($m['nama']) ?>", "<?= addslashes($m['jabatan']) ?>", <?= $member_scores ?>, <?= $is_periode_closed ? 'true' : 'false' ?>)'>
                            
                            <div class="d-flex align-items-center min-width-0 me-2">
                                <div class="avatar-sm me-3 <?= $is_rated ? 'bg-success-soft text-success' : 'bg-slate-100 text-muted' ?> rounded-circle d-flex align-items-center justify-content-center fw-800 border" 
                                     style="width: 38px; height: 38px; min-width: 38px; font-size: 0.95rem;">
                                    <?= $initials ?>
                                </div>
                                <div class="flex-grow-1 min-width-0 text-start">
                                    <div class="fw-800 text-dark text-truncate mb-0" style="font-size: 0.92rem;"><?= htmlspecialchars($m['nama']) ?></div>
                                    <div class="text-muted very-small fw-600 opacity-75 text-truncate">
                                        <?= htmlspecialchars($m['jabatan']) ?><?= !empty($m['nama_biro']) ? ' • Biro ' . htmlspecialchars($m['nama_biro']) : '' ?>
                                    </div>
                                    <?php if (!empty($m['penilai_nama']) && $is_kepala_or_admin): ?>
                                        <div class="very-small text-primary opacity-75 text-truncate mt-0-5">
                                            <i class="fas fa-pen-nib me-1"></i>Oleh: <?= htmlspecialchars($m['penilai_nama']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- INDIKATOR STATUS HALUS / MINIMALIS -->
                            <div class="text-end ps-2">
                                <?php if ($is_rated): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-1 fw-bold" style="font-size: 0.7rem;">
                                        <i class="fas fa-check me-1"></i> Dinilai
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted rounded-pill px-2 py-1 fw-600" style="font-size: 0.7rem;">
                                        Belum
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; else: ?>
                            <div class="p-5 text-center">
                                <i class="fas fa-user-slash fa-3x text-muted opacity-25 mb-3"></i>
                                <p class="text-muted small fw-600 mb-0">Tidak ada pengurus yang terdaftar.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- PANEL KANAN: FORM EVALUASI NILAI -->
        <div class="col-lg-8">
            <div id="selectPlaceholder" class="card border-4 border-dashed border-light rounded-4 h-100 d-flex align-items-center justify-content-center text-center p-5 bg-white opacity-75 <?= $selected_nokta_after_post ? 'd-none' : '' ?>">
                <div class="p-4 text-dark text-center w-100">
                    <div class="stats-icon bg-light text-muted mx-auto mb-4" style="width: 80px; height: 80px; border-radius: 25px;">
                        <i class="fas fa-user-edit fa-3x"></i>
                    </div>
                    <h5 class="fw-800">Pilih Pengurus untuk Dinilai</h5>
                    <p class="text-muted px-lg-5">Cukup klik pada salah satu nama pengurus di daftar sebelah kiri. Form penilaian akan otomatis terbuka dan memuat nilai jika sudah pernah dinilai sebelumnya.</p>
                </div>
            </div>

            <div id="ratingFormArea" class="card border-0 shadow-sm rounded-4 overflow-hidden <?= $selected_nokta_after_post ? '' : 'd-none' ?>">
                <form method="POST" id="formPenilaian">
                    <div class="card-header bg-white border-0 py-4 px-4 border-bottom border-light">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center text-dark">
                                <div id="targetAvatar" class="avatar-lg me-3 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-800 shadow-sm" style="width: 52px; height: 52px; font-size: 1.35rem;">
                                    ?
                                </div>
                                <div>
                                    <h5 id="targetName" class="fw-800 text-dark mb-0">-</h5>
                                    <span id="targetJabatan" class="badge bg-slate-100 text-muted px-2 py-1 rounded-pill small fw-bold">Jabatan</span>
                                    <input type="hidden" name="dinilai_nokta" id="targetNokta">
                                    <input type="hidden" name="nama_dinilai" id="targetNameInput">
                                    <input type="hidden" name="action_type" id="actionType" value="save">
                                </div>
                            </div>
                            <div>
                                <span id="formModeBadge" class="badge bg-light text-muted px-3 py-2 rounded-pill fw-bold small">
                                    Mode Input
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4 bg-white">
                        <!-- INFO ASPEK DISIPLIN OTOMATIS -->
                        <div class="mb-4 p-3 rounded-4 bg-light border d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <div class="fw-800 text-dark small mb-0">
                                    <i class="fas fa-magic text-primary me-1"></i> Aspek Disiplin (Otomatis)
                                </div>
                                <div class="text-muted very-small">
                                    Dihitung otomatis oleh sistem dari integrasi rekap kehadiran absensi dan ketertiban kas.
                                </div>
                            </div>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-bold" style="font-size: 0.75rem;">
                                <i class="fas fa-bolt me-1"></i> Auto-Calculated
                            </span>
                        </div>

                        <?php foreach(['attitude' => 'Attitude & Perilaku', 'komunikasi' => 'Komunikasi & Koordinasi'] as $cat => $label): ?>
                            <h6 class="fw-800 text-muted small text-uppercase ls-2 mb-4 ms-1 mt-<?= $cat == 'komunikasi' ? '4' : '0' ?>"><?= $label ?></h6>
                            
                            <?php foreach($indicators as $i): if($i['kategori'] == $cat): ?>
                                <div class="mb-4 p-3 rounded-4 bg-slate-50 border border-light-soft rating-row">
                                    <div class="fw-800 text-dark mb-3" style="font-size: 0.95rem;"><?= htmlspecialchars($i['nama_indikator']) ?></div>
                                    
                                    <div class="d-flex justify-content-between gap-2 radio-group">
                                        <?php for($s=1; $s<=4; $s++): 
                                            $desc = ['', 'Kurang', 'Cukup', 'Baik', 'Sangat Baik'];
                                        ?>
                                            <div class="rating-option flex-fill">
                                                <input type="radio" class="btn-check rating-radio" 
                                                       name="skor[<?= $i['id_indikator'] ?>]" 
                                                       id="s_<?= $i['id_indikator'] ?>_<?= $s ?>" 
                                                       value="<?= $s ?>" 
                                                       data-indikator="<?= $i['id_indikator'] ?>"
                                                       required>
                                                <label class="btn btn-outline-light w-100 py-3 rounded-3 border-0 bg-white shadow-sm d-flex flex-column align-items-center h-100 transition-all rating-label text-dark" for="s_<?= $i['id_indikator'] ?>_<?= $s ?>">
                                                    <span class="fw-800 mb-1" style="font-size: 1.2rem;"><?= $s ?></span>
                                                    <span class="very-small text-muted fw-bold text-uppercase opacity-75 d-none d-md-block" style="font-size: 0.62rem;"><?= $desc[$s] ?></span>
                                                </label>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            <?php endif; endforeach; ?>
                        <?php endforeach; ?>
                        
                        <div class="mt-4" id="submitBtnWrapper">
                            <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 fw-800 shadow-sm w-100" id="btnSimpanNilai">
                                <i class="fas fa-save me-2"></i> Simpan Penilaian
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- MODAL DETAIL PENGURUS BELUM DINILAI (KEPALA PPI) -->
<div class="modal fade" id="unratedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                <h6 class="modal-title fw-800 mb-0" id="unratedModalTitle">
                    <i class="fas fa-user-clock me-2"></i>Pengurus Belum Dinilai
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-white">
                <p class="text-muted small mb-3">Berikut adalah anggota di biro ini yang belum memiliki riwayat skor penilaian pada periode aktif:</p>
                <div class="list-group list-group-flush border rounded-3 mb-3 overflow-auto" id="unratedModalList" style="max-height: 320px;">
                    <!-- Diisi via JavaScript -->
                </div>
                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openUnratedModal(biroName, unratedMembers, biroId) {
    document.getElementById('unratedModalTitle').innerHTML = '<i class="fas fa-user-clock me-2"></i>Belum Dinilai: ' + biroName;
    const listContainer = document.getElementById('unratedModalList');
    listContainer.innerHTML = '';
    
    if (!unratedMembers || unratedMembers.length === 0) {
        listContainer.innerHTML = '<div class="p-4 text-center text-success fw-bold"><i class="fas fa-check-circle fa-2x mb-2 d-block"></i> Semua anggota di biro ini sudah dinilai!</div>';
    } else {
        unratedMembers.forEach(m => {
            const item = document.createElement('div');
            item.className = 'list-group-item d-flex justify-content-between align-items-center py-3 px-3';
            item.innerHTML = `
                <div>
                    <div class="fw-800 text-dark">${m.nama}</div>
                    <div class="text-muted small">${m.jabatan}</div>
                </div>
                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 fw-800 shadow-sm" onclick="selectFromModal('${m.nokta}', '${m.nama.replace(/'/g, "\\'")}', '${m.jabatan.replace(/'/g, "\\'")}')">
                    <i class="fas fa-pen me-1"></i> Nilai
                </button>
            `;
            listContainer.appendChild(item);
        });
    }
    
    const modal = new bootstrap.Modal(document.getElementById('unratedModal'));
    modal.show();
}

function selectFromModal(nokta, nama, jabatan) {
    const modalEl = document.getElementById('unratedModal');
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) {
        modalInstance.hide();
    }
    
    // Reset filters to ensure the clicked target row is rendered
    const biroSelect = document.getElementById('filterBiroSelect');
    const statusSelect = document.getElementById('filterStatusSelect');
    const searchInput = document.getElementById('searchMember');
    if (biroSelect) biroSelect.value = 'all';
    if (statusSelect) statusSelect.value = 'all';
    if (searchInput) searchInput.value = '';
    applyAllFilters();
    
    // Find target row
    let targetRow = null;
    document.querySelectorAll('.member-item').forEach(row => {
        if (row.getAttribute('data-name') === nama.toLowerCase()) {
            targetRow = row;
        }
    });
    
    // Trigger rating selection
    selectMember(targetRow, nokta, nama, jabatan, {}, false);
}

function selectMember(element, nokta, nama, jabatan, existingScores, isReadOnly) {
    // 1. Highlight baris terpilih
    document.querySelectorAll('.member-item').forEach(item => item.classList.remove('active-member-row'));
    if (element) {
        element.classList.add('active-member-row');
    } else {
        const row = document.getElementById('item_' + nokta);
        if (row) row.classList.add('active-member-row');
    }

    // 2. Buka card form
    document.getElementById('selectPlaceholder').classList.add('d-none');
    const ratingCard = document.getElementById('ratingFormArea');
    ratingCard.classList.remove('d-none');
    ratingCard.classList.add('fade-in');

    // 3. Set Info Target
    document.getElementById('targetNokta').value = nokta;
    document.getElementById('targetName').innerText = nama;
    document.getElementById('targetNameInput').value = nama;
    document.getElementById('targetJabatan').innerText = jabatan;
    document.getElementById('targetAvatar').innerText = nama.charAt(0).toUpperCase();

    // 4. Reset & Set Radios
    const allRadios = ratingCard.querySelectorAll('.rating-radio');
    allRadios.forEach(radio => {
        radio.checked = false;
        radio.disabled = isReadOnly;
    });

    const isEditMode = existingScores && Object.keys(existingScores).length > 0;
    const formBadge = document.getElementById('formModeBadge');
    const submitBtn = document.getElementById('btnSimpanNilai');
    const actionTypeInput = document.getElementById('actionType');

    // 5. Pre-fill nilai lama
    if (isEditMode) {
        for (const [indikatorId, score] of Object.entries(existingScores)) {
            const targetRadio = document.getElementById('s_' + indikatorId + '_' + score);
            if (targetRadio) {
                targetRadio.checked = true;
            }
        }
    }

    // 6. Atur status antarmuka form
    if (isReadOnly) {
        formBadge.className = 'badge bg-secondary px-3 py-2 rounded-pill fw-bold small';
        formBadge.innerHTML = '<i class="fas fa-lock me-1"></i> Periode Ditutup (Hanya Lihat)';
        submitBtn.style.display = 'none';
    } else if (isEditMode) {
        formBadge.className = 'badge bg-success-soft text-success px-3 py-2 rounded-pill fw-bold small border border-success-subtle';
        formBadge.innerHTML = '<i class="fas fa-edit me-1"></i> Memperbarui Nilai';
        actionTypeInput.value = 'edit';
        submitBtn.style.display = 'block';
        submitBtn.className = 'btn btn-primary btn-lg rounded-pill px-5 fw-800 shadow-sm w-100';
        submitBtn.innerHTML = '<i class="fas fa-sync-alt me-2"></i> Perbarui Nilai Pengurus';
    } else {
        formBadge.className = 'badge bg-light text-dark px-3 py-2 rounded-pill fw-bold small border';
        formBadge.innerHTML = '<i class="fas fa-pen me-1"></i> Input Nilai Baru';
        actionTypeInput.value = 'save';
        submitBtn.style.display = 'block';
        submitBtn.className = 'btn btn-primary btn-lg rounded-pill px-5 fw-800 shadow-sm w-100';
        submitBtn.innerHTML = '<i class="fas fa-save me-2"></i> Simpan Penilaian';
    }

    if (window.innerWidth < 992) {
        ratingCard.scrollIntoView({ behavior: 'smooth' });
    }
}

function applyAllFilters() {
    const searchInput = document.getElementById('searchMember').value.toLowerCase();
    const biroSelect = document.getElementById('filterBiroSelect');
    const statusSelect = document.getElementById('filterStatusSelect');
    
    const biroVal = biroSelect ? biroSelect.value : 'all';
    const statusVal = statusSelect ? statusSelect.value : 'all';

    const items = document.querySelectorAll('.member-item');
    let visibleCount = 0;

    items.forEach(item => {
        const name = item.getAttribute('data-name') || '';
        const jabatan = item.getAttribute('data-jabatan') || '';
        const biro = item.getAttribute('data-biro') || '';
        const rated = item.getAttribute('data-rated') || '';

        const matchSearch = name.includes(searchInput) || jabatan.includes(searchInput);
        const matchBiro = (biroVal === 'all' || biro === biroVal);
        const matchStatus = (statusVal === 'all' || rated === statusVal);

        if (matchSearch && matchBiro && matchStatus) {
            item.style.setProperty('display', 'flex', 'important');
            visibleCount++;
        } else {
            item.style.setProperty('display', 'none', 'important');
        }
    });

    const badge = document.getElementById('memberCountBadge');
    if (badge) badge.innerText = visibleCount + ' Orang';
}

function filterByBiroCard(biroId) {
    const biroSelect = document.getElementById('filterBiroSelect');
    if (biroSelect) {
        biroSelect.value = biroId;
        applyAllFilters();
        // Highlight active biro card
        document.querySelectorAll('.biro-card-filter').forEach(card => card.classList.remove('shadow-sm', 'border-primary'));
        if (event && event.currentTarget) {
            event.currentTarget.classList.add('shadow-sm', 'border-primary');
        }
    }
}

<?php if ($selected_nokta_after_post): 
    $mScores = isset($existing_scores[$selected_nokta_after_post]) ? json_encode($existing_scores[$selected_nokta_after_post]) : '{}';
    $postTargetName = '';
    $postTargetJabatan = '';
    foreach($members as $m) {
        if ($m['nokta'] === $selected_nokta_after_post) {
            $postTargetName = $m['nama'];
            $postTargetJabatan = $m['jabatan'];
            break;
        }
    }
?>
document.addEventListener('DOMContentLoaded', function() {
    selectMember(null, "<?= $selected_nokta_after_post ?>", "<?= addslashes($postTargetName) ?>", "<?= addslashes($postTargetJabatan) ?>", <?= $mScores ?>, <?= $is_periode_closed ? 'true' : 'false' ?>);
});
<?php endif; ?>
</script>

<style>
.bg-rose-soft { background-color: #F0FDF4; color: #16A34A; }
.text-rose { color: #16A34A; }
.bg-success-soft { background-color: #ECFDF5; color: #059669; }
.bg-danger-soft { background-color: #FEF2F2; color: #DC2626; }
.bg-slate-50 { background-color: #f8fafc; }
.bg-slate-100 { background-color: #f1f5f9; }
.stats-icon { display: flex; align-items: center; justify-content: center; }
.ls-2 { letter-spacing: 1.5px; }
.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.rounded-4 { border-radius: 1.25rem !important; }
.modern-member-row { cursor: pointer; transition: all 0.15s ease; border-left: 3px solid transparent !important; }
.modern-member-row:hover { background-color: #f8fafc !important; }
.active-member-row { background-color: #FEF2F2 !important; border-left: 3px solid var(--primary) !important; }
.rating-label { border: 1px solid transparent !important; }
.rating-label:hover { background-color: #f8fafc !important; transform: translateY(-2px); border-color: #e2e8f0 !important; }
.btn-check:checked + .rating-label { 
    background-color: var(--primary) !important; 
    color: white !important; 
    box-shadow: 0 8px 15px -3px rgba(220, 38, 38, 0.3) !important;
}
.btn-check:checked + .rating-label .text-muted { color: rgba(255, 255, 255, 0.8) !important; }
.rating-row { transition: all 0.2s ease; }
.rating-row:hover { border-color: var(--primary-light) !important; background-color: #fff !important; }
.very-small { font-size: 0.72rem; }
.transition-all { transition: all 0.2s ease; }
</style>

<?php include '../layout/footer.php'; ?>
