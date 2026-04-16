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
    return '/sippi/' . ltrim($path, '/');
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
        header("Location: /sippi/dashboard/dashboard.php");
        exit;
    }
}

function get_active_kepengurusan() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM tabel_kepengurusan WHERE status = 'aktif' LIMIT 1");
    return $stmt->fetch();
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

    // 2. Performance (Attitude & Komunikasi)
    $stmtAK = $pdo->prepare("SELECT i.kategori, AVG(tp.skor) as avg_score
                             FROM tabel_penilaian tp
                             JOIN tabel_indikator i ON tp.indikator_id = i.id_indikator
                             WHERE tp.dinilai_nokta = ? AND tp.bulan = ? AND tp.tahun = ?
                             GROUP BY i.kategori");
    $stmtAK->execute([$nokta, $bulan, $tahun]);
    $ak_results = $stmtAK->fetchAll(PDO::FETCH_KEY_PAIR);

    $nilai_attitude = $ak_results['attitude'] ?? 0;
    $nilai_komunikasi = $ak_results['komunikasi'] ?? 0;

    // 3. Discipline (Attendance & Kas)
    // a. Kehadiran
    $stmtHadir = $pdo->prepare("SELECT COUNT(*) FROM tabel_kehadiran WHERE nokta_pengurus = ? AND kegiatan_id IN (SELECT id_kegiatan FROM tabel_kegiatan WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ?) AND status_hadir = 'hadir'");
    $stmtHadir->execute([$nokta, $bulan, $tahun, $kepengurusan_id]);
    $jmlHadir = $stmtHadir->fetchColumn();

    $persenHadir = ($jmlHadir / $totalKegiatan) * 100;
    $nilaiKehadiran = 1;
    if ($persenHadir > 75) $nilaiKehadiran = 4;
    elseif ($persenHadir >= 50) $nilaiKehadiran = 3;
    elseif ($persenHadir >= 25) $nilaiKehadiran = 2;

    // b. Status Kas
    $stmtMand = $pdo->prepare("SELECT COUNT(*) FROM tabel_kas_periode_wajib WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ?");
    $stmtMand->execute([$bulan, $tahun, $kepengurusan_id]);
    $isMandatory = $stmtMand->fetchColumn() > 0;

    if ($isMandatory) {
        $stmtKas = $pdo->prepare("SELECT status_bayar FROM tabel_kas_pengurus WHERE nokta_pengurus = ? AND bulan = ? AND tahun = ?");
        $stmtKas->execute([$nokta, $bulan, $tahun]);
        $kasStatus = $stmtKas->fetchColumn();
        $nilaiKas = ($kasStatus == 'sudah') ? 4 : 1;
    } else {
        $nilaiKas = 4;
    }

    $nilai_disiplin = ($nilaiKehadiran + $nilaiKas) / 2;
    $nilai_kpi_total = ($nilai_attitude + $nilai_komunikasi + $nilai_disiplin) / 3;

    // 4. Save/Update to tabel_nilai_kpi
    $stmtSave = $pdo->prepare("INSERT INTO tabel_nilai_kpi (nokta, bulan, tahun, kepengurusan_id, nilai_attitude, nilai_komunikasi, nilai_disiplin, nilai_kpi_total)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE nilai_attitude = ?, nilai_komunikasi = ?, nilai_disiplin = ?, nilai_kpi_total = ?");
    return $stmtSave->execute([
        $nokta, $bulan, $tahun, $kepengurusan_id, $nilai_attitude, $nilai_komunikasi, $nilai_disiplin, $nilai_kpi_total,
        $nilai_attitude, $nilai_komunikasi, $nilai_disiplin, $nilai_kpi_total
    ]);
}
?>


