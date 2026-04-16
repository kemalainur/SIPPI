<?php
require_once '../config/database.php';
session_start();
check_login();
check_role(['Super Admin', 'PPI']);

// Fetch ACTIVE Grand Period
$active_p = get_active_kepengurusan();
if (!$active_p) {
    die("Error: Tidak ada Tahun Kepengurusan yang aktif.");
}

// Fetch Active Month
$stmtAktif = $pdo->prepare("SELECT * FROM tabel_periode WHERE status = 'aktif' AND kepengurusan_id = ? LIMIT 1");
$stmtAktif->execute([$active_p['id_kepengurusan']]);
$active = $stmtAktif->fetch();

if (!$active) {
    header("Location: bulan_penilaian.php");
    exit;
}

$bulan = (int)$active['bulan'];
$tahun = (int)$active['tahun'];

// Fetch all members in the ACTIVE BOARD that SHOULD be calculated
$stmtToCalc = $pdo->prepare("SELECT j.nokta FROM tabel_pengurus_jabatan j 
                             JOIN tabel_role r ON j.role_id = r.id_role
                             WHERE j.kepengurusan_id = ? 
                             AND r.nama_role NOT IN ('PPI', 'Koorkam', 'Super Admin')");
$stmtToCalc->execute([$active_p['id_kepengurusan']]);
$members = $stmtToCalc->fetchAll(PDO::FETCH_COLUMN);

// 1. Get Total Kegiatan for this period
$stmtTotalKegiatan = $pdo->prepare("SELECT COUNT(*) FROM tabel_kegiatan WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ?");
$stmtTotalKegiatan->execute([$bulan, $tahun, $active_p['id_kepengurusan']]);
$totalKegiatan = max(1, $stmtTotalKegiatan->fetchColumn()); // Avoid division by zero

foreach ($members as $nokta) {
    // --- Attitude & Komunikasi ---
    // Calculate average score for each category
    $stmtAK = $pdo->prepare("SELECT i.kategori, AVG(tp.skor) as avg_score
                             FROM tabel_penilaian tp
                             JOIN tabel_indikator i ON tp.indikator_id = i.id_indikator
                             WHERE tp.dinilai_nokta = ? AND tp.bulan = ? AND tp.tahun = ?
                             GROUP BY i.kategori");
    $stmtAK->execute([$nokta, $bulan, $tahun]);
    $ak_results = $stmtAK->fetchAll(PDO::FETCH_KEY_PAIR); // ['attitude' => X, 'komunikasi' => Y]

    $nilai_attitude = $ak_results['attitude'] ?? 0;
    $nilai_komunikasi = $ak_results['komunikasi'] ?? 0;

    // --- Disiplin ---
    // a. Kehadiran
    $stmtHadir = $pdo->prepare("SELECT COUNT(*) FROM tabel_kehadiran WHERE nokta_pengurus = ? AND kegiatan_id IN (SELECT id_kegiatan FROM tabel_kegiatan WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ?) AND status_hadir = 'hadir'");
    $stmtHadir->execute([$nokta, $bulan, $tahun, $active_p['id_kepengurusan']]);
    $jmlHadir = $stmtHadir->fetchColumn();

    $persenHadir = ($jmlHadir / $totalKegiatan) * 100;
    $nilaiKehadiran = 1;
    if ($persenHadir > 75) $nilaiKehadiran = 4;
    elseif ($persenHadir >= 50) $nilaiKehadiran = 3;
    elseif ($persenHadir >= 25) $nilaiKehadiran = 2;

    // b. Status Kas
    // 1. Check if this month is MINTATORY for this period
    $stmtMand = $pdo->prepare("SELECT COUNT(*) FROM tabel_kas_periode_wajib WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ?");
    $stmtMand->execute([$bulan, $tahun, $active_p['id_kepengurusan']]);
    $isMandatory = $stmtMand->fetchColumn() > 0;

    if ($isMandatory) {
        $stmtKas = $pdo->prepare("SELECT status_bayar FROM tabel_kas_pengurus WHERE nokta_pengurus = ? AND bulan = ? AND tahun = ?");
        $stmtKas->execute([$nokta, $bulan, $tahun]);
        $kasStatus = $stmtKas->fetchColumn();
        $nilaiKas = ($kasStatus == 'sudah') ? 4 : 1;
    } else {
        // If not mandatory, everyone gets 4 (standard score)
        $nilaiKas = 4;
    }

    $nilai_disiplin = ($nilaiKehadiran + $nilaiKas) / 2;

    // --- Total KPI ---
    $nilai_kpi_total = ($nilai_attitude + $nilai_komunikasi + $nilai_disiplin) / 3;

    // Save or Update to tabel_nilai_kpi
    $stmtSave = $pdo->prepare("INSERT INTO tabel_nilai_kpi (nokta, bulan, tahun, kepengurusan_id, nilai_attitude, nilai_komunikasi, nilai_disiplin, nilai_kpi_total)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE nilai_attitude = ?, nilai_komunikasi = ?, nilai_disiplin = ?, nilai_kpi_total = ?");
    $stmtSave->execute([
        $nokta, $bulan, $tahun, $active_p['id_kepengurusan'], $nilai_attitude, $nilai_komunikasi, $nilai_disiplin, $nilai_kpi_total,
        $nilai_attitude, $nilai_komunikasi, $nilai_disiplin, $nilai_kpi_total
    ]);
}

$_SESSION['success'] = "Kalkulasi Nilai KPI bulan $bulan/$tahun Berhasil!";
header("Location: monitoring_penilaian.php");
exit;
?>


