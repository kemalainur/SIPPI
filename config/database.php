<?php
/**
 * Konfigurasi Database SIPPI
 * Menggunakan PDO untuk keamanan dan fleksibilitas
 */

$host = 'localhost';
$db   = 'sippi';
$user = 'root';
$pass = ''; // Sesuaikan dengan konfigurasi XAMPP Anda
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Global Help Functions
function base_url($path = '') {
    // Detect if running on local XAMPP or Live Domain
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    // If on localhost/sippi, keep /sippi/, otherwise use root
    $base = ($host === 'localhost') ? '/sippi/' : '/';
    return $base . ltrim($path, '/');
}

function redirect($path) {
    header("Location: " . base_url($path));
    exit;
}

function check_login() {
    if (!isset($_SESSION['user'])) {
        redirect('auth/login.php');
    }
}

function check_role($roles) {
    if (!in_array($_SESSION['user']['nama_role'] ?? '', (array)$roles)) {
        $_SESSION['error'] = 'Anda tidak memiliki hak akses ke halaman ini.';
        header("Location: " . base_url('dashboard/dashboard.php'));
        exit;
    }
}

function get_active_kepengurusan() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM tabel_kepengurusan WHERE status = 'aktif' LIMIT 1");
    return $stmt->fetch();
}

function get_kpi_settings() {
    global $pdo;
    $stmt = $pdo->query("SELECT kunci, bobot FROM tabel_pengaturan_kpi");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['kunci']] = $row['bobot'] / 100; // Store as decimal multiplier
    }
    return $settings;
}

/**
 * Automate KPI Calculation for a single member
 * Called on every input (penilaian, kehadiran, kas status)
 */
function update_kpi_member($nokta, $bulan, $tahun, $kepengurusan_id) {
    global $pdo;

    // 1. Total Kegiatan in this month
    $stmtTotalKegiatan = $pdo->prepare("SELECT COUNT(*) FROM tabel_kegiatan WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ?");
    $stmtTotalKegiatan->execute([$bulan, $tahun, $kepengurusan_id]);
    $totalKegiatan = max(1, $stmtTotalKegiatan->fetchColumn());

    // 2. Performance (Attitude & Komunikasi) - Peer Assessment
    $stmtAK = $pdo->prepare("SELECT i.kategori, AVG(tp.skor) as avg_score
                             FROM tabel_penilaian tp
                             JOIN tabel_indikator i ON tp.indikator_id = i.id_indikator
                             WHERE tp.dinilai_nokta = ? AND tp.bulan = ? AND tp.tahun = ?
                             GROUP BY i.kategori");
    $stmtAK->execute([$nokta, $bulan, $tahun]);
    $ak_results = $stmtAK->fetchAll(PDO::FETCH_KEY_PAIR);

    $nilai_attitude = $ak_results['attitude'] ?? 0;
    $nilai_komunikasi = $ak_results['komunikasi'] ?? 0;

    // 3. Discipline Sub-indicators (30% of Total)
    
    // a. Kehadiran (40% of Discipline)
    // Hadir, Izin, Telat count as present in percentage
    $stmtHadir = $pdo->prepare("SELECT COUNT(*) FROM tabel_kehadiran WHERE nokta_pengurus = ? AND kegiatan_id IN (SELECT id_kegiatan FROM tabel_kegiatan WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ?) AND status_hadir IN ('hadir', 'izin', 'telat')");
    $stmtHadir->execute([$nokta, $bulan, $tahun, $kepengurusan_id]);
    $jmlHadir = $stmtHadir->fetchColumn();

    $persenHadir = ($jmlHadir / $totalKegiatan) * 100;
    if ($persenHadir >= 80) $scoreHadir = 4;
    elseif ($persenHadir >= 60) $scoreHadir = 3;
    elseif ($persenHadir >= 40) $scoreHadir = 2;
    else $scoreHadir = 1;

    // b. Ketepatan Waktu (40% of Discipline)
    $stmtTelat = $pdo->prepare("SELECT COUNT(*) FROM tabel_kehadiran WHERE nokta_pengurus = ? AND kegiatan_id IN (SELECT id_kegiatan FROM tabel_kegiatan WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ?) AND status_hadir = 'telat'");
    $stmtTelat->execute([$nokta, $bulan, $tahun, $kepengurusan_id]);
    $jmlTelat = $stmtTelat->fetchColumn();
    
    $stmtHadirMurni = $pdo->prepare("SELECT COUNT(*) FROM tabel_kehadiran WHERE nokta_pengurus = ? AND kegiatan_id IN (SELECT id_kegiatan FROM tabel_kegiatan WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ?) AND status_hadir IN ('hadir', 'telat')");
    $stmtHadirMurni->execute([$nokta, $bulan, $tahun, $kepengurusan_id]);
    $totalHadirMurni = max(1, $stmtHadirMurni->fetchColumn());

    $persenTelat = ($jmlTelat / $totalHadirMurni) * 100;
    if ($persenTelat <= 10) $scoreTelat = 4;
    elseif ($persenTelat <= 30) $scoreTelat = 3;
    elseif ($persenTelat <= 50) $scoreTelat = 2;
    else $scoreTelat = 1;

    // c. Pembayaran Kas (20% of Discipline)
    $stmtKas = $pdo->prepare("SELECT status_bayar FROM tabel_kas_pengurus WHERE nokta_pengurus = ? AND bulan = ? AND tahun = ?");
    $stmtKas->execute([$nokta, $bulan, $tahun]);
    $kasStatus = $stmtKas->fetchColumn();
    $scoreKas = ($kasStatus == 'sudah') ? 4 : 1;

    $settings = get_kpi_settings();
    $wHadir = $settings['weight_disiplin_hadir'] ?? 0.4;
    $wTelat = $settings['weight_disiplin_telat'] ?? 0.4;
    $wKas   = $settings['weight_disiplin_kas'] ?? 0.2;
    $nilai_disiplin = ($scoreHadir * $wHadir) + ($scoreTelat * $wTelat) + ($scoreKas * $wKas);

    // 4. Final Score Calculation
    $wAttitude   = $settings['weight_attitude'] ?? 0.4;
    $wKomunikasi = $settings['weight_komunikasi'] ?? 0.3;
    $wDisiplin   = $settings['weight_disiplin'] ?? 0.3;
    $nilai_kpi_total = ($nilai_attitude * $wAttitude) + ($nilai_komunikasi * $wKomunikasi) + ($nilai_disiplin * $wDisiplin);

    // 5. Save/Update to tabel_nilai_kpi
    $stmtSave = $pdo->prepare("INSERT INTO tabel_nilai_kpi (nokta, bulan, tahun, kepengurusan_id, nilai_attitude, nilai_komunikasi, nilai_disiplin, nilai_kpi_total)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE nilai_attitude = ?, nilai_komunikasi = ?, nilai_disiplin = ?, nilai_kpi_total = ?");
    return $stmtSave->execute([
        $nokta, $bulan, $tahun, $kepengurusan_id, $nilai_attitude, $nilai_komunikasi, $nilai_disiplin, $nilai_kpi_total,
        $nilai_attitude, $nilai_komunikasi, $nilai_disiplin, $nilai_kpi_total
    ]);
}


