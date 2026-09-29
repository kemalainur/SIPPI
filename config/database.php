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

function get_nama_bulan_indo($month_num) {
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    return $months[(int)$month_num] ?? ('Bulan ' . $month_num);
}

function format_nama_periode($periode) {
    if (!$periode) return 'Belum Ada Periode';
    
    if (!empty($periode['nama_sesi'])) {
        return htmlspecialchars($periode['nama_sesi']);
    }
    
    $bulan = (int)($periode['bulan'] ?? 0);
    $tahun = (int)($periode['tahun'] ?? date('Y'));
    $jenis = $periode['jenis_periode'] ?? 'Bulanan';
    $mode = $periode['mode_penilaian'] ?? 'PPI';
    
    if ($jenis === 'Triwulan' || $jenis === 'Triwulanan' || $mode === 'Peer Assessment') {
        $triwulan_no = ($bulan <= 4 && $bulan >= 1) ? $bulan : (int)ceil($bulan / 3);
        if ($triwulan_no < 1) $triwulan_no = 1;
        if ($triwulan_no > 4) $triwulan_no = 4;
        return "Triwulan " . $triwulan_no . " " . $tahun;
    }
    
    return get_nama_bulan_indo($bulan) . " " . $tahun;
}

function update_kpi_member($nokta, $bulan, $tahun, $kepengurusan_id) {
    global $pdo;

    // Angkatan 2023 dan PJNas tidak diikutsertakan dalam kalkulasi KPI
    $stmtCheck = $pdo->prepare("SELECT p.angkatan, r.nama_role 
                                FROM tabel_pengurus p
                                LEFT JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
                                LEFT JOIN tabel_role r ON j.role_id = r.id_role
                                WHERE p.nokta = ?");
    $stmtCheck->execute([$kepengurusan_id, $nokta]);
    $uInfo = $stmtCheck->fetch();

    if (!$uInfo || $uInfo['angkatan'] === '2023' || in_array($uInfo['nama_role'], ['PJnas', 'PJNas'])) {
        return false;
    }

    // 1. Cek Jenis Periode & Pemetaan Bulan Disiplin
    $stmtPeriode = $pdo->prepare("SELECT id_periode, jenis_periode, mode_penilaian 
                                  FROM tabel_periode 
                                  WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ? 
                                  LIMIT 1");
    $stmtPeriode->execute([$bulan, $tahun, $kepengurusan_id]);
    $periodeRow = $stmtPeriode->fetch();

    $target_disiplin_months = [];
    if ($periodeRow && ($periodeRow['jenis_periode'] === 'Triwulan' || $periodeRow['jenis_periode'] === 'Triwulanan' || $periodeRow['mode_penilaian'] === 'Peer Assessment')) {
        $stmtMap = $pdo->prepare("SELECT bulan_sumber, tahun_sumber 
                                  FROM tabel_periode_disiplin_bulan 
                                  WHERE periode_id = ? 
                                  ORDER BY tahun_sumber ASC, bulan_sumber ASC");
        $stmtMap->execute([$periodeRow['id_periode']]);
        $target_disiplin_months = $stmtMap->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($target_disiplin_months)) {
        $target_disiplin_months = [['bulan_sumber' => $bulan, 'tahun_sumber' => $tahun]];
    }

    // 2. Performance (Attitude & Komunikasi) - Manual Input
    $stmtAK = $pdo->prepare("SELECT i.kategori, AVG(tp.skor) as avg_score
                             FROM tabel_penilaian tp
                             JOIN tabel_indikator i ON tp.indikator_id = i.id_indikator
                             WHERE tp.dinilai_nokta = ? AND tp.bulan = ? AND tp.tahun = ?
                             GROUP BY i.kategori");
    $stmtAK->execute([$nokta, $bulan, $tahun]);
    $ak_results = $stmtAK->fetchAll(PDO::FETCH_KEY_PAIR);

    $nilai_attitude = (float)($ak_results['attitude'] ?? 0);
    $nilai_komunikasi = (float)($ak_results['komunikasi'] ?? 0);

    // 3. Kalkulasi Otomatis Disiplin (Agregasi Rata-rata dari Bulan Terpilih yang diceklis)
    $settings = get_kpi_settings();
    $wHadir = $settings['weight_disiplin_hadir'] ?? 0.5;
    $wKas   = $settings['weight_disiplin_kas'] ?? 0.5;

    $total_disiplin_accum = 0;
    $month_count = 0;

    foreach ($target_disiplin_months as $tm) {
        $b_src = (int)$tm['bulan_sumber'];
        $t_src = (int)$tm['tahun_sumber'];

        // A. Kehadiran Kegiatan
        $stmtTotKeg = $pdo->prepare("SELECT COUNT(*) FROM tabel_kegiatan WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ?");
        $stmtTotKeg->execute([$b_src, $t_src, $kepengurusan_id]);
        $countKeg = (int)$stmtTotKeg->fetchColumn();

        if ($countKeg > 0) {
            $stmtHadir = $pdo->prepare("SELECT COUNT(*) FROM tabel_kehadiran WHERE nokta_pengurus = ? AND kegiatan_id IN (SELECT id_kegiatan FROM tabel_kegiatan WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ?) AND status_hadir IN ('hadir', 'izin')");
            $stmtHadir->execute([$nokta, $b_src, $t_src, $kepengurusan_id]);
            $jmlHadir = (int)$stmtHadir->fetchColumn();
            $persenHadir = ($jmlHadir / $countKeg) * 100;
        } else {
            $persenHadir = 100;
        }

        if ($persenHadir >= 80) {
            $scoreHadir = 4;
        } elseif ($persenHadir >= 60) {
            $scoreHadir = 3;
        } elseif ($persenHadir >= 40) {
            $scoreHadir = 2;
        } else {
            $scoreHadir = 1;
        }

        // B. Ketertiban Pembayaran Kas
        $stmtKas = $pdo->prepare("SELECT status_bayar FROM tabel_kas_pengurus WHERE nokta_pengurus = ? AND bulan = ? AND tahun = ?");
        $stmtKas->execute([$nokta, $b_src, $t_src]);
        $kasStatus = $stmtKas->fetchColumn();
        $scoreKas = ($kasStatus === 'sudah') ? 4 : 2;

        // Disiplin bulan ini
        $disiplin_bulan = ($scoreHadir * $wHadir) + ($scoreKas * $wKas);
        $total_disiplin_accum += $disiplin_bulan;
        $month_count++;
    }

    $nilai_disiplin = $month_count > 0 ? ($total_disiplin_accum / $month_count) : 4.0;

    // 4. Perhitungan Nilai KPI Akhir (Disiplin, Komunikasi, Attitude)
    $wAttitude   = $settings['weight_attitude'] ?? 0.4;
    $wKomunikasi = $settings['weight_komunikasi'] ?? 0.3;
    $wDisiplin   = $settings['weight_disiplin'] ?? 0.3;
    $nilai_kpi_total = ($nilai_disiplin * $wDisiplin) + ($nilai_komunikasi * $wKomunikasi) + ($nilai_attitude * $wAttitude);

    $stmtSave = $pdo->prepare("INSERT INTO tabel_nilai_kpi (nokta, bulan, tahun, kepengurusan_id, nilai_attitude, nilai_komunikasi, nilai_disiplin, nilai_kpi_total)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE nilai_attitude = ?, nilai_komunikasi = ?, nilai_disiplin = ?, nilai_kpi_total = ?");
    return $stmtSave->execute([
        $nokta, $bulan, $tahun, $kepengurusan_id, $nilai_attitude, $nilai_komunikasi, $nilai_disiplin, $nilai_kpi_total,
        $nilai_attitude, $nilai_komunikasi, $nilai_disiplin, $nilai_kpi_total
    ]);
}

function get_user_ppi_info($nokta, $kepengurusan_id = null) {
    global $pdo;
    if (!$kepengurusan_id) {
        $active_p = get_active_kepengurusan();
        $kepengurusan_id = $active_p['id_kepengurusan'] ?? 0;
    }

    $stmt = $pdo->prepare("SELECT j.jabatan, j.biro_id, r.nama_role, b.nama_biro, p.angkatan
                           FROM tabel_pengurus p
                           LEFT JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
                           LEFT JOIN tabel_role r ON j.role_id = r.id_role
                           LEFT JOIN tabel_biro b ON j.biro_id = b.id_biro
                           WHERE p.nokta = ?");
    $stmt->execute([$kepengurusan_id, $nokta]);
    $row = $stmt->fetch();

    $angkatan = $row['angkatan'] ?? '';
    $is_2023 = ($angkatan == '2023');

    $role_name = $_SESSION['user']['nama_role'] ?? ($row['nama_role'] ?? '');
    $jabatan = $row['jabatan'] ?? ($_SESSION['user']['jabatan'] ?? '');
    $biro_id = $row['biro_id'] ?? null;
    $nama_biro = $row['nama_biro'] ?? '';

    $is_super_admin = ($role_name === 'Super Admin');
    $is_ppi_role = !$is_2023 && ($role_name === 'PPI' || strpos(strtolower($jabatan), 'ppi') !== false);
    $is_kepala = !$is_2023 && ($is_super_admin || ($is_ppi_role && (strpos(strtolower($jabatan), 'kepala') !== false || strpos(strtolower($jabatan), 'ketua') !== false)));

    if (!$biro_id && $is_ppi_role && !$is_kepala) {
        $search_biro = '';
        if (strpos(strtolower($jabatan), 'infokom') !== false) {
            $search_biro = 'INFOKOM';
        } elseif (strpos(strtolower($jabatan), 'kastrat') !== false) {
            $search_biro = 'KASTRAT';
        } elseif (strpos(strtolower($jabatan), 'psdm') !== false) {
            $search_biro = 'PSDM';
        }
        if ($search_biro) {
            $stmtB = $pdo->prepare("SELECT id_biro, nama_biro FROM tabel_biro WHERE kepengurusan_id = ? AND nama_biro LIKE ? LIMIT 1");
            $stmtB->execute([$kepengurusan_id, "%$search_biro%"]);
            $b = $stmtB->fetch();
            if ($b) {
                $biro_id = $b['id_biro'];
                $nama_biro = $b['nama_biro'];
            }
        }
    }

    return [
        'is_2023' => $is_2023,
        'angkatan' => $angkatan,
        'is_super_admin' => $is_super_admin,
        'is_ppi' => $is_ppi_role,
        'is_kepala' => $is_kepala,
        'is_staff_pj' => $is_ppi_role && !$is_kepala,
        'biro_id' => $biro_id,
        'nama_biro' => $nama_biro,
        'jabatan' => $jabatan ?: ($is_kepala ? 'Kepala PPI' : ($nama_biro ? 'PJ Biro ' . $nama_biro : 'Staff PPI'))
    ];
}



