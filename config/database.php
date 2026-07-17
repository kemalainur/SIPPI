<?php

$host = 'localhost';
$db   = 'sippi';
$user = 'root';
$pass = ''; 
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

function has_permission($permission_name) {
    if (!isset($_SESSION['user'])) {
        return false;
    }
    
    // Super Admin bypass
    if ($_SESSION['user']['nama_role'] === 'Super Admin') {
        return true;
    }
    
    global $pdo;
    $role_id = $_SESSION['user']['role_id'] ?? 0;
    
    static $role_perms = null;
    if ($role_perms === null) {
        try {
            $stmt = $pdo->prepare("SELECT p.nama_permission FROM tabel_role_permission rp 
                                   JOIN tabel_permission p ON rp.permission_id = p.id_permission 
                                   WHERE rp.role_id = ?");
            $stmt->execute([$role_id]);
            $role_perms = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            $role_perms = [];
        }
    }
    
    foreach ($role_perms as $perm) {
        if ($perm === '*' || $perm === $permission_name) {
            return true;
        }
        if (strpos($perm, '.*') !== false) {
            $prefix = str_replace('.*', '', $perm);
            if (strpos($permission_name, $prefix . '.') === 0) {
                return true;
            }
        }
    }
    
    return false;
}

function check_permission($permission_name) {
    check_login();
    if (!has_permission($permission_name)) {
        $_SESSION['error'] = 'Anda tidak memiliki hak akses ke tindakan/halaman ini.';
        
        $current_page = basename($_SERVER['PHP_SELF']);
        if ($permission_name === 'dashboard.view' || $current_page === 'dashboard.php') {
            // Fallback pages order based on typical user roles
            $fallback_pages = [
                'kas.view' => 'kas/kas_saya.php',
                'raport.view' => 'raport/raport_saya.php',
                'pengurus.view' => 'pengurus/data_pengurus.php',
                'biro.view' => 'biro/data_biro.php',
                'divisi.view' => 'divisi/data_divisi.php',
                'inventaris.view' => 'inventaris/data_inventaris.php',
            ];
            foreach ($fallback_pages as $perm => $url) {
                if (has_permission($perm)) {
                    header("Location: " . base_url($url));
                    exit;
                }
            }
            // If no other permissions exist, show friendly access denied page
            echo "<div style='font-family: sans-serif; text-align: center; padding: 50px; background: #f8fafc; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center;'>
                    <div style='background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 500px; width: 100%; border-top: 4px solid #DC2626; box-sizing: border-box;'>
                        <h2 style='color: #0f172a; margin-top: 0;'>Akses Ditolak (Access Denied)</h2>
                        <p style='color: #475569; font-size: 14px;'>Anda tidak memiliki hak akses untuk melihat dashboard ('dashboard.view') atau halaman lainnya.</p>
                        <p style='color: #64748b; font-size: 13px; background: #f1f5f9; padding: 10px; border-radius: 6px;'>Role Anda: <strong>" . htmlspecialchars($_SESSION['user']['nama_role'] ?? '') . "</strong></p>
                        <a href='" . base_url('auth/logout.php') . "' style='display: inline-block; padding: 10px 20px; background: #DC2626; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 15px;'>Keluar (Logout)</a>
                    </div>
                  </div>";
            exit;
        }
        
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

function update_kpi_member($nokta, $bulan, $tahun, $kepengurusan_id) {
    global $pdo;

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

    $stmtHadir = $pdo->prepare("SELECT COUNT(*) FROM tabel_kehadiran WHERE nokta_pengurus = ? AND kegiatan_id IN (SELECT id_kegiatan FROM tabel_kegiatan WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ?) AND status_hadir IN ('hadir', 'izin', 'telat')");
    $stmtHadir->execute([$nokta, $bulan, $tahun, $kepengurusan_id]);
    $jmlHadir = $stmtHadir->fetchColumn();

    $persenHadir = ($jmlHadir / $totalKegiatan) * 100;
    if ($persenHadir >= 80) $scoreHadir = 4;
    elseif ($persenHadir >= 60) $scoreHadir = 3;
    elseif ($persenHadir >= 40) $scoreHadir = 2;
    else $scoreHadir = 1;

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

    $stmtKas = $pdo->prepare("SELECT status_bayar FROM tabel_kas_pengurus WHERE nokta_pengurus = ? AND bulan = ? AND tahun = ?");
    $stmtKas->execute([$nokta, $bulan, $tahun]);
    $kasStatus = $stmtKas->fetchColumn();
    $scoreKas = ($kasStatus == 'sudah') ? 4 : 1;

    $settings = get_kpi_settings();
    $wHadir = $settings['weight_disiplin_hadir'] ?? 0.4;
    $wTelat = $settings['weight_disiplin_telat'] ?? 0.4;
    $wKas   = $settings['weight_disiplin_kas'] ?? 0.2;
    $nilai_disiplin = ($scoreHadir * $wHadir) + ($scoreTelat * $wTelat) + ($scoreKas * $wKas);

    $wAttitude   = $settings['weight_attitude'] ?? 0.4;
    $wKomunikasi = $settings['weight_komunikasi'] ?? 0.3;
    $wDisiplin   = $settings['weight_disiplin'] ?? 0.3;
    $nilai_kpi_total = ($nilai_attitude * $wAttitude) + ($nilai_komunikasi * $wKomunikasi) + ($nilai_disiplin * $wDisiplin);

    $stmtSave = $pdo->prepare("INSERT INTO tabel_nilai_kpi (nokta, bulan, tahun, kepengurusan_id, nilai_attitude, nilai_komunikasi, nilai_disiplin, nilai_kpi_total)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE nilai_attitude = ?, nilai_komunikasi = ?, nilai_disiplin = ?, nilai_kpi_total = ?");
    return $stmtSave->execute([
        $nokta, $bulan, $tahun, $kepengurusan_id, $nilai_attitude, $nilai_komunikasi, $nilai_disiplin, $nilai_kpi_total,
        $nilai_attitude, $nilai_komunikasi, $nilai_disiplin, $nilai_kpi_total
    ]);
}


