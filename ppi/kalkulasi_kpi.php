<?php
require_once '../config/database.php';
session_start();
check_login();
check_permission('kpi.manage');


$active_p = get_active_kepengurusan();
if (!$active_p) {
    die("Error: Tidak ada Tahun Kepengurusan yang aktif.");
}

$stmtAktif = $pdo->prepare("SELECT * FROM tabel_periode WHERE status = 'aktif' AND kepengurusan_id = ? LIMIT 1");
$stmtAktif->execute([$active_p['id_kepengurusan']]);
$active = $stmtAktif->fetch();

if (!$active) {
    header("Location: bulan_penilaian.php");
    exit;
}

$bulan = (int)$active['bulan'];
$tahun = (int)$active['tahun'];

$stmtToCalc = $pdo->prepare("SELECT j.nokta FROM tabel_pengurus_jabatan j 
                             JOIN tabel_role r ON j.role_id = r.id_role
                             JOIN tabel_pengurus p ON j.nokta = p.nokta
                             WHERE j.kepengurusan_id = ? 
                             AND r.nama_role NOT IN ('PPI', 'Koorkam', 'Super Admin')
                             AND (p.angkatan IS NULL OR p.angkatan != '2023')");
$stmtToCalc->execute([$active_p['id_kepengurusan']]);
$members = $stmtToCalc->fetchAll(PDO::FETCH_COLUMN);

$stmtTotalKegiatan = $pdo->prepare("SELECT COUNT(*) FROM tabel_kegiatan WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ?");
$stmtTotalKegiatan->execute([$bulan, $tahun, $active_p['id_kepengurusan']]);
$totalKegiatan = max(1, $stmtTotalKegiatan->fetchColumn()); // Avoid division by zero

foreach ($members as $nokta) {
    update_kpi_member($nokta, $bulan, $tahun, $active_p['id_kepengurusan']);
}

$_SESSION['success'] = "Kalkulasi Nilai KPI " . format_nama_periode($active) . " Berhasil!";
header("Location: monitoring_penilaian.php");
exit;
?>


