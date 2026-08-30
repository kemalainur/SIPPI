<?php
require_once '../config/database.php';
session_start();
check_permission('dashboard.view');

$active_p = get_active_kepengurusan();
$active_id = $active_p['id_kepengurusan'] ?? 0;

$role_name = $_SESSION['user']['nama_role'];
$is_admin_view = in_array($role_name, ['Super Admin', 'Sekjend', 'Bendum', 'PPI', 'Kabiro', 'Koorkam']);
$my_nokta = $_SESSION['user']['nokta'];

$stmtPeriode = $pdo->prepare("SELECT * FROM tabel_periode WHERE status = 'aktif' AND kepengurusan_id = ? LIMIT 1");
$stmtPeriode->execute([$active_id]);
$periodeAktif = $stmtPeriode->fetch();

if ($is_admin_view) {
    try {
        $stmtPengurus = $pdo->prepare("SELECT COUNT(*) FROM tabel_pengurus_jabatan j 
                                     JOIN tabel_pengurus p ON j.nokta = p.nokta 
                                     WHERE j.kepengurusan_id = ? AND (p.angkatan IS NULL OR p.angkatan != '2023')");
        $stmtPengurus->execute([$active_id]);
        $totalPengurus = $stmtPengurus->fetchColumn();

        $stmtBiro = $pdo->prepare("SELECT COUNT(*) FROM tabel_biro WHERE kepengurusan_id = ?");
        $stmtBiro->execute([$active_id]);
        $totalBiro = $stmtBiro->fetchColumn();

        $stmtDivisi = $pdo->prepare("SELECT COUNT(*) FROM tabel_divisi WHERE kepengurusan_id = ?");
        $stmtDivisi->execute([$active_id]);
        $totalDivisi = $stmtDivisi->fetchColumn();

        $stmtAvg = $pdo->prepare("SELECT AVG(nilai_kpi_total) FROM tabel_nilai_kpi WHERE kepengurusan_id = ? AND bulan = ? AND tahun = ?");
        $stmtAvg->execute([$active_id, $periodeAktif['bulan'] ?? 0, $periodeAktif['tahun'] ?? 0]);
        $avgKPI = round($stmtAvg->fetchColumn() ?: 0, 2);

        // COUNT(item inventaris) as requested, instead of SUM(jumlah)
        $stmtInv = $pdo->query("SELECT COUNT(*) FROM tabel_inventaris");
        $totalInventaris = $stmtInv->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        $totalPengurus = $totalBiro = $totalDivisi = $totalInventaris = 0;
        $avgKPI = 0;
    }
}

try {
    // Personal KPI Score (Latest)
    $stmtMyKPI = $pdo->prepare("SELECT * FROM tabel_nilai_kpi WHERE nokta = ? AND kepengurusan_id = ? ORDER BY tahun DESC, bulan DESC LIMIT 1");
    $stmtMyKPI->execute([$my_nokta, $active_id]);
    $myKPI = $stmtMyKPI->fetch();

    if ($periodeAktif) {
        // Kas
        $stmtMyKas = $pdo->prepare("SELECT status_bayar FROM tabel_kas_pengurus WHERE nokta_pengurus = ? AND bulan = ? AND tahun = ?");
        $stmtMyKas->execute([$my_nokta, $periodeAktif['bulan'], $periodeAktif['tahun']]);
        $myKas = $stmtMyKas->fetchColumn() ?: 'belum';

        // Attendance %
        $stmtTotKeg = $pdo->prepare("SELECT COUNT(*) FROM tabel_kegiatan WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ?");
        $stmtTotKeg->execute([$periodeAktif['bulan'], $periodeAktif['tahun'], $active_id]);
        $totKeg = max(1, $stmtTotKeg->fetchColumn());

        $stmtMyHadir = $pdo->prepare("SELECT COUNT(*) FROM tabel_kehadiran WHERE nokta_pengurus = ? AND status_hadir = 'hadir' AND kegiatan_id IN (SELECT id_kegiatan FROM tabel_kegiatan WHERE bulan = ? AND tahun = ? AND kepengurusan_id = ?)");
        $stmtMyHadir->execute([$my_nokta, $periodeAktif['bulan'], $periodeAktif['tahun'], $active_id]);
        $myHadir = round(($stmtMyHadir->fetchColumn() / $totKeg) * 100);
    } else {
        $myKas = 'N/A';
        $myHadir = 0;
    }
} catch (PDOException $e) {
    $myKPI = null;
    $myKas = 'Error';
    $myHadir = 0;
}

if ($periodeAktif && $role_name != 'Super Admin') {
    // Check if user is a penilai
    $stmtCheckPenilai = $pdo->prepare("SELECT COUNT(*) FROM tabel_konfigurasi_penilaian 
                                       WHERE kepengurusan_id = ? AND bulan = ? AND tahun = ? AND tipe = 'penilai'");
    $stmtCheckPenilai->execute([$active_id, $periodeAktif['bulan'], $periodeAktif['tahun']]);
    $has_penilai_config = ($stmtCheckPenilai->fetchColumn() > 0);

    $is_allowed_to_assess = true;
    if ($has_penilai_config) {
        $stmtIsPenilai = $pdo->prepare("SELECT COUNT(*) FROM tabel_konfigurasi_penilaian 
                                         WHERE kepengurusan_id = ? AND bulan = ? AND tahun = ? AND nokta = ? AND tipe = 'penilai'");
        $stmtIsPenilai->execute([$active_id, $periodeAktif['bulan'], $periodeAktif['tahun'], $my_nokta]);
        $is_allowed_to_assess = ($stmtIsPenilai->fetchColumn() > 0);
    }

    if (!$is_allowed_to_assess) {
        $remAssess = 0;
    } else {
        // Check if there is a 'dinilai' configuration
        $stmtCheckDinilai = $pdo->prepare("SELECT COUNT(*) FROM tabel_konfigurasi_penilaian 
                                           WHERE kepengurusan_id = ? AND bulan = ? AND tahun = ? AND tipe = 'dinilai'");
        $stmtCheckDinilai->execute([$active_id, $periodeAktif['bulan'], $periodeAktif['tahun']]);
        $has_dinilai_config = ($stmtCheckDinilai->fetchColumn() > 0);

        if ($has_dinilai_config) {
            $stmtRem = $pdo->prepare("SELECT COUNT(*) 
                                     FROM tabel_pengurus p 
                                     JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
                                     JOIN tabel_role r ON j.role_id = r.id_role
                                     JOIN tabel_konfigurasi_penilaian kp ON p.nokta = kp.nokta AND kp.kepengurusan_id = ? AND kp.bulan = ? AND kp.tahun = ? AND kp.tipe = 'dinilai'
                                     LEFT JOIN tabel_penilaian tp ON p.nokta = tp.dinilai_nokta AND tp.penilai_nokta = ? AND tp.bulan = ? AND tp.tahun = ?
                                     WHERE r.nama_role NOT IN ('PPI', 'Koorkam', 'Super Admin') 
                                     AND (p.angkatan IS NULL OR p.angkatan != '2023')
                                     AND p.nokta != ?
                                     AND tp.id_penilaian IS NULL");
            $stmtRem->execute([$active_id, $active_id, $periodeAktif['bulan'], $periodeAktif['tahun'], $my_nokta, $periodeAktif['bulan'], $periodeAktif['tahun'], $my_nokta]);
        } else {
            $stmtRem = $pdo->prepare("SELECT COUNT(*) 
                                     FROM tabel_pengurus p 
                                     JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
                                     JOIN tabel_role r ON j.role_id = r.id_role
                                     LEFT JOIN tabel_penilaian tp ON p.nokta = tp.dinilai_nokta AND tp.penilai_nokta = ? AND tp.bulan = ? AND tp.tahun = ?
                                     WHERE r.nama_role NOT IN ('PPI', 'Koorkam', 'Super Admin') 
                                     AND (p.angkatan IS NULL OR p.angkatan != '2023')
                                     AND p.nokta != ?
                                     AND tp.id_penilaian IS NULL");
            $stmtRem->execute([$active_id, $my_nokta, $periodeAktif['bulan'], $periodeAktif['tahun'], $my_nokta]);
        }
        $remAssess = $stmtRem->fetchColumn();
    }
} else {
    $remAssess = 0;
}

// Queries for Kehadiran Trend
try {
    if ($is_admin_view) {
        $stmtHadirTrend = $pdo->prepare("SELECT k.bulan, k.tahun, COUNT(CASE WHEN kh.status_hadir = 'hadir' THEN 1 END) * 100.0 / COUNT(*) as avg_hadir 
                                         FROM tabel_kehadiran kh 
                                         JOIN tabel_kegiatan k ON kh.kegiatan_id = k.id_kegiatan 
                                         WHERE k.kepengurusan_id = ? 
                                         GROUP BY k.tahun, k.bulan 
                                         ORDER BY k.tahun ASC, k.bulan ASC LIMIT 6");
        $stmtHadirTrend->execute([$active_id]);
    } else {
        $stmtHadirTrend = $pdo->prepare("SELECT k.bulan, k.tahun, COUNT(CASE WHEN kh.status_hadir = 'hadir' THEN 1 END) * 100.0 / COUNT(*) as avg_hadir 
                                         FROM tabel_kehadiran kh 
                                         JOIN tabel_kegiatan k ON kh.kegiatan_id = k.id_kegiatan 
                                         WHERE kh.nokta_pengurus = ? AND k.kepengurusan_id = ? 
                                         GROUP BY k.tahun, k.bulan 
                                         ORDER BY k.tahun ASC, k.bulan ASC LIMIT 6");
        $stmtHadirTrend->execute([$my_nokta, $active_id]);
    }
    $hadirTrendResults = $stmtHadirTrend->fetchAll();
    $hadirLabels = [];
    $hadirData = [];
    foreach ($hadirTrendResults as $ht) {
        $hadirLabels[] = $ht['bulan'] . '/' . substr($ht['tahun'], 2);
        $hadirData[] = round($ht['avg_hadir'], 1);
    }
    if (empty($hadirData)) {
        $hadirLabels = ['Data Kosong'];
        $hadirData = [0];
    }
} catch (PDOException $e) {
    $hadirLabels = ['Error'];
    $hadirData = [0];
}

// Queries for Kas Trend
try {
    if ($is_admin_view) {
        $stmtKasTrend = $pdo->query("SELECT MONTH(tanggal) as bulan, YEAR(tanggal) as tahun, 
                                            SUM(CASE WHEN jenis = 'masuk' THEN jumlah ELSE 0 END) as masuk, 
                                            SUM(CASE WHEN jenis = 'keluar' THEN jumlah ELSE 0 END) as keluar 
                                     FROM tabel_kas_umum 
                                     GROUP BY YEAR(tanggal), MONTH(tanggal) 
                                     ORDER BY YEAR(tanggal) ASC, MONTH(tanggal) ASC LIMIT 6");
        $kasTrendResults = $stmtKasTrend->fetchAll();
        $kasLabels = [];
        $kasMasukData = [];
        $kasKeluarData = [];
        foreach ($kasTrendResults as $kt) {
            $kasLabels[] = $kt['bulan'] . '/' . substr($kt['tahun'], 2);
            $kasMasukData[] = (float)$kt['masuk'];
            $kasKeluarData[] = (float)$kt['keluar'];
        }
        if (empty($kasLabels)) {
            $kasLabels = ['Data Kosong'];
            $kasMasukData = [0];
            $kasKeluarData = [0];
        }
    } else {
        $stmtKasTrend = $pdo->prepare("SELECT bulan, tahun, nominal FROM tabel_kas_pengurus 
                                        WHERE nokta_pengurus = ? 
                                        ORDER BY tahun ASC, bulan ASC LIMIT 6");
        $stmtKasTrend->execute([$my_nokta]);
        $kasTrendResults = $stmtKasTrend->fetchAll();
        $kasLabels = [];
        $kasData = [];
        foreach ($kasTrendResults as $kt) {
            $kasLabels[] = $kt['bulan'] . '/' . substr($kt['tahun'], 2);
            $kasData[] = (float)$kt['nominal'];
        }
        if (empty($kasLabels)) {
            $kasLabels = ['Data Kosong'];
            $kasData = [0];
        }
    }
} catch (PDOException $e) {
    $kasLabels = ['Error'];
    $kasMasukData = [0];
    $kasKeluarData = [0];
    $kasData = [0];
}

$ppi_info = get_user_ppi_info($my_nokta, $active_id);

// Kepala PPI Dashboard Data
$kepala_ppi_dashboard = null;
if ($ppi_info['is_kepala'] && $periodeAktif) {
    $kepala_ppi_dashboard = [];
    $stmtBiros = $pdo->prepare("SELECT id_biro, nama_biro FROM tabel_biro WHERE kepengurusan_id = ? ORDER BY nama_biro ASC");
    $stmtBiros->execute([$active_id]);
    $all_biros = $stmtBiros->fetchAll();

    foreach ($all_biros as $b) {
        $stmtMembers = $pdo->prepare("SELECT p.nokta, p.nama, j.jabatan,
                                             (SELECT COUNT(*) FROM tabel_penilaian tp WHERE tp.dinilai_nokta = p.nokta AND tp.bulan = ? AND tp.tahun = ?) as is_rated
                                      FROM tabel_pengurus_jabatan j 
                                      JOIN tabel_pengurus p ON j.nokta = p.nokta 
                                      JOIN tabel_role r ON j.role_id = r.id_role 
                                      WHERE j.kepengurusan_id = ? AND j.biro_id = ? 
                                      AND r.nama_role NOT IN ('Super Admin', 'PJnas', 'PJNas') 
                                      AND (p.angkatan IS NULL OR p.angkatan != '2023')
                                      ORDER BY p.nama ASC");
        $stmtMembers->execute([$periodeAktif['bulan'], $periodeAktif['tahun'], $active_id, $b['id_biro']]);
        $members_biro = $stmtMembers->fetchAll(PDO::FETCH_ASSOC);

        $tot_pengurus_biro = count($members_biro);
        $unrated_biro = [];
        $eval_biro = 0;
        foreach ($members_biro as $mb) {
            if ($mb['is_rated'] > 0) {
                $eval_biro++;
            } else {
                $unrated_biro[] = [
                    'nokta' => $mb['nokta'],
                    'nama' => $mb['nama'],
                    'jabatan' => $mb['jabatan']
                ];
            }
        }

        $pct_biro = $tot_pengurus_biro > 0 ? round(($eval_biro / $tot_pengurus_biro) * 100, 1) : 100;

        $stmtPJ = $pdo->prepare("SELECT p.nama, j.jabatan FROM tabel_pengurus_jabatan j JOIN tabel_pengurus p ON j.nokta = p.nokta WHERE j.kepengurusan_id = ? AND j.biro_id = ? AND (j.role_id = 4 OR LOWER(j.jabatan) LIKE '%pj%') LIMIT 1");
        $stmtPJ->execute([$active_id, $b['id_biro']]);
        $pj_data = $stmtPJ->fetch();

        $kepala_ppi_dashboard[] = [
            'id_biro' => $b['id_biro'],
            'nama_biro' => $b['nama_biro'],
            'pj_nama' => $pj_data['nama'] ?? 'Staff PPI',
            'tot_pengurus' => $tot_pengurus_biro,
            'sudah_dinilai' => $eval_biro,
            'belum_dinilai' => count($unrated_biro),
            'unrated_members' => $unrated_biro,
            'persen' => $pct_biro
        ];
    }
}

// Staff PPI (PJ Biro) Dashboard Data
$staff_ppi_dashboard = null;
if ($ppi_info['is_staff_pj'] && $ppi_info['biro_id'] && $periodeAktif) {
    $biro_id = $ppi_info['biro_id'];
    
    $stmtBiroMembers = $pdo->prepare("SELECT p.nokta, p.nama, j.jabatan,
                                       (SELECT COUNT(*) FROM tabel_penilaian tp WHERE tp.dinilai_nokta = p.nokta AND tp.penilai_nokta = ? AND tp.bulan = ? AND tp.tahun = ?) as is_rated_by_me
                                       FROM tabel_pengurus p
                                       JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
                                       JOIN tabel_role r ON j.role_id = r.id_role
                                       WHERE j.biro_id = ? AND r.nama_role NOT IN ('Super Admin', 'PJnas', 'PJNas') AND (p.angkatan IS NULL OR p.angkatan != '2023') AND p.nokta != ?
                                       ORDER BY p.nama ASC");
    $stmtBiroMembers->execute([$my_nokta, $periodeAktif['bulan'], $periodeAktif['tahun'], $active_id, $biro_id, $my_nokta]);
    $biro_members = $stmtBiroMembers->fetchAll();

    $tot_biro_members = count($biro_members);
    $rated_count = count(array_filter($biro_members, fn($m) => $m['is_rated_by_me'] > 0));
    $unrated_count = $tot_biro_members - $rated_count;

    $staff_ppi_dashboard = [
        'nama_biro' => $ppi_info['nama_biro'],
        'members' => $biro_members,
        'total' => $tot_biro_members,
        'sudah' => $rated_count,
        'belum' => $unrated_count
    ];
}

$title = "Dashboard Utama";
include '../layout/header.php';
include '../layout/sidebar.php';

$greeting = "Selamat Datang";
$time = date("H");
if ($time < 12)
    $greeting = "Selamat Pagi";
elseif ($time < 15)
    $greeting = "Selamat Siang";
elseif ($time < 18)
    $greeting = "Selamat Sore";
else
    $greeting = "Selamat Malam";
?>

<div id="content" class="fade-in">
    <div class="row align-items-center mb-4 g-3">
        <div class="col-md-6">
            <h3 class="fw-800 text-dark mb-1"><?= $greeting ?>, <?= explode(' ', $_SESSION['user']['nama'])[0] ?>! 👋</h3>
        </div>
        <div class="col-md-6 text-md-end">
            <div class="d-inline-flex align-items-center p-2 bg-white rounded-pill shadow-sm border-light border px-4 h-100">
                <i class="fas fa-calendar-alt text-primary me-2"></i>
                <span class="small fw-800 text-dark"><?= $active_p['nama_periode'] ?? 'Tahun Belum Diatur' ?></span>
                <div class="vr mx-3" style="height: 20px; opacity: 0.1;"></div>
                <span class="badge <?= $periodeAktif ? 'bg-brand-red-soft text-brand-red' : 'bg-slate-100 text-muted' ?> px-3 py-2 rounded-pill fw-bold">
                    <?= $periodeAktif ? '<i class="fas fa-check-circle me-1"></i> Bulan ' . $periodeAktif['bulan'] . ' Terbuka' : '<i class="fas fa-lock me-1"></i> Ditutup' ?>
                </span>
            </div>
        </div>
    </div>

    <?php if ($kepala_ppi_dashboard): ?>
        <!-- Dashboard Monitoring Kepala PPI -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-white">
            <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0 fw-800 text-dark">
                    <i class="fas fa-shield-alt text-danger me-2"></i> Monitoring Dashboard Kepala PPI
                </h6>
                <div>
                    <span class="badge bg-danger text-white rounded-pill px-3 py-1 fw-bold me-1">
                        Mode: <?= htmlspecialchars($periodeAktif['mode_penilaian'] ?? 'PPI') ?>
                    </span>
                    <span class="badge bg-dark text-white rounded-pill px-3 py-1 fw-bold">
                        Periode: <?= htmlspecialchars($periodeAktif['jenis_periode'] ?? 'Bulanan') ?> (<?= $periodeAktif['bulan'] ?>/<?= $periodeAktif['tahun'] ?>)
                    </span>
                </div>
            </div>
            <div class="card-body p-4 pt-2">
                <div class="row g-3">
                    <?php foreach ($kepala_ppi_dashboard as $kpd): ?>
                        <div class="col-md-4">
                            <div class="p-3 rounded-4 bg-slate-50 border border-light">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-800 text-dark">Biro <?= htmlspecialchars($kpd['nama_biro']) ?></span>
                                    <span class="badge bg-danger text-white fw-bold"><?= $kpd['persen'] ?>%</span>
                                </div>
                                <div class="small text-muted mb-2"><i class="fas fa-user-shield me-1"></i> PJ: <strong><?= htmlspecialchars($kpd['pj_nama']) ?></strong></div>
                                <div class="progress mb-3" style="height: 6px;">
                                    <div class="progress-bar bg-danger" style="width: <?= $kpd['persen'] ?>%"></div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center small text-muted">
                                    <span>Total: <strong><?= $kpd['tot_pengurus'] ?></strong></span>
                                    <span class="text-success fw-bold">Sudah: <strong><?= $kpd['sudah_dinilai'] ?></strong></span>
                                    <?php if ($kpd['belum_dinilai'] > 0): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 rounded-pill fw-bold shadow-sm" style="font-size: 0.75rem;"
                                                onclick='openUnratedDashboardModal("<?= addslashes($kpd['nama_biro']) ?>", <?= htmlspecialchars(json_encode($kpd['unrated_members']), ENT_QUOTES, 'UTF-8') ?>)'>
                                            <i class="fas fa-eye me-1"></i> Belum: <?= $kpd['belum_dinilai'] ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-success bg-opacity-15 text-success rounded-pill px-2 py-1 fw-bold"><i class="fas fa-check-double me-1"></i>Komplit</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3 text-end">
                    <a href="<?= base_url('ppi/monitoring_penilaian.php') ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold">
                        <i class="fas fa-eye me-1"></i> Detail Monitoring
                    </a>
                    <a href="<?= base_url('ppi/penilaian_input.php') ?>" class="btn btn-sm btn-danger rounded-pill px-3 fw-bold ms-1">
                        <i class="fas fa-edit me-1"></i> Bantu Penilaian
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($staff_ppi_dashboard): 
        $pct_staff = $staff_ppi_dashboard['total'] > 0 ? round(($staff_ppi_dashboard['sudah'] / $staff_ppi_dashboard['total']) * 100, 1) : 100;
    ?>
        <!-- Dashboard Staff PPI (PJ Biro) - Information Summary Only -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-white">
            <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0 fw-800 text-dark">
                    <i class="fas fa-user-shield text-danger me-2"></i> Ringkasan Penilaian PJ Biro <?= htmlspecialchars($staff_ppi_dashboard['nama_biro']) ?>
                </h6>
                <span class="badge bg-primary text-white rounded-pill px-3 py-1 fw-bold">
                    Tanggung Jawab: Biro <?= htmlspecialchars($staff_ppi_dashboard['nama_biro']) ?>
                </span>
            </div>
            <div class="card-body p-4 pt-2">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-4 text-center border border-light">
                            <div class="text-muted small fw-600">Harus Dinilai</div>
                            <div class="fw-800 text-dark h3 mb-0"><?= $staff_ppi_dashboard['total'] ?> <span class="fs-6 text-muted fw-bold">Pengurus</span></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-brand-green-soft rounded-4 text-center border border-light">
                            <div class="text-brand-green small fw-600">Sudah Dinilai</div>
                            <div class="fw-800 text-brand-green h3 mb-0"><?= $staff_ppi_dashboard['sudah'] ?> <span class="fs-6 text-brand-green fw-bold">Pengurus</span></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-danger-soft rounded-4 text-center border border-light">
                            <div class="text-danger small fw-600">Belum Dinilai</div>
                            <div class="fw-800 text-danger h3 mb-0"><?= $staff_ppi_dashboard['belum'] ?> <span class="fs-6 text-danger fw-bold">Pengurus</span></div>
                        </div>
                    </div>
                </div>

                <div class="p-3 rounded-4 bg-slate-50 border border-light mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-800 text-dark">Progres Penyelesaian Penilaian Biro <?= htmlspecialchars($staff_ppi_dashboard['nama_biro']) ?></span>
                        <span class="badge bg-danger text-white fw-bold fs-6"><?= $pct_staff ?>%</span>
                    </div>
                    <div class="progress" style="height: 10px; border-radius: 5px;">
                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?= $pct_staff ?>%"></div>
                    </div>
                </div>

                <div class="text-end">
                    <a href="<?= base_url('ppi/penilaian_input.php') ?>" class="btn btn-danger rounded-pill px-4 fw-800 shadow-sm">
                        <i class="fas fa-edit me-1"></i> Buka Modul Beri Penilaian <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($is_admin_view): ?>
        <!-- KPI Overview Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 bg-gradient-brand-red text-white h-100">
                    <div class="card-body p-4 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="bg-white text-brand-red rounded-circle d-flex align-items-center justify-content-center me-3 shadow-lg"
                                style="width: 50px; height: 50px;">
                                <i class="fas fa-user-check fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-600 opacity-75">Performa Pribadi Saya</h6>
                                <h3 class="mb-0 fw-800 tracking-tight">Skor KPI: <?= number_format($myKPI['nilai_kpi_total'] ?? 0, 2) ?> / 4.0</h3>
                            </div>
                        </div>
                        <div class="text-end d-none d-md-block">
                            <span class="badge bg-white text-dark rounded-pill px-3 py-2 fw-bold small shadow-sm">
                                <i class="fas fa-calendar-day me-1"></i> Update: <?= date('M Y') ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 bg-dark text-white h-100">
                    <div class="card-body p-4 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="bg-white text-dark rounded-circle d-flex align-items-center justify-content-center me-3 shadow-lg"
                                style="width: 50px; height: 50px;">
                                <i class="fas fa-chart-line fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-600 opacity-75">Skor Rata-rata KPI Organisasi</h6>
                                <h3 class="mb-0 fw-800 tracking-tight">Rata-rata: <?= number_format($avgKPI, 2) ?> / 4.0</h3>
                            </div>
                        </div>
                        <p class="mb-0 small text-brand-green fw-bold d-none d-md-block"><i class="fas fa-check-circle me-1"></i> Real-time</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Count Stats Cards (Total Pengurus & Jumlah Inventaris) -->
        <div class="row g-4 mb-4">
            <!-- Total Pengurus -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 stats-card">
                    <div class="card-body p-4 position-relative">
                        <div class="d-flex align-items-center mb-4">
                            <div class="stats-icon bg-brand-red text-white shadow-lg me-3"
                                style="width: 54px; height: 54px; border-radius: 16px;">
                                <i class="fas fa-users-viewfinder fa-lg"></i>
                            </div>
                            <h6 class="text-muted fw-800 small text-uppercase mb-0 ls-1">Aktif Pengurus</h6>
                        </div>
                        <div class="d-flex align-items-baseline">
                            <h2 class="fw-800 text-dark display-6 mb-0"><?= $totalPengurus ?></h2>
                            <span class="ms-3 badge bg-brand-red-soft text-brand-red rounded-pill px-3 py-1 fw-bold small">Periode <?= date('Y') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Jumlah Inventaris (COUNT of items) -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 stats-card">
                    <div class="card-body p-4 position-relative">
                        <div class="d-flex align-items-center mb-4">
                            <div class="stats-icon bg-primary text-white shadow-lg me-3"
                                style="width: 54px; height: 54px; border-radius: 16px;">
                                <i class="fas fa-boxes fa-lg"></i>
                            </div>
                            <h6 class="text-muted fw-800 small text-uppercase mb-0 ls-1">Jumlah Inventaris</h6>
                        </div>
                        <div class="d-flex align-items-baseline">
                            <h2 class="fw-800 text-dark display-6 mb-0"><?= $totalInventaris ?></h2>
                            <span class="ms-3 text-muted small fw-600">Aset Terdaftar</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Non-admin Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <!-- Card 1: My Score -->
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 stats-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-4">
                            <div class="stats-icon bg-brand-red text-white shadow-lg me-3"
                                style="width: 50px; height: 50px; border-radius: 14px;">
                                <i class="fas fa-star fa-lg"></i>
                            </div>
                            <h6 class="text-muted fw-800 small text-uppercase mb-0 ls-1">Skor KPI Saya</h6>
                        </div>
                        <div class="d-flex align-items-baseline">
                            <h2 class="fw-800 text-dark display-6 mb-0"><?= number_format($myKPI['nilai_kpi_total'] ?? 0, 2) ?></h2>
                            <span class="ms-2 text-muted fw-600">/ 4.0</span>
                        </div>
                        <p class="mb-0 text-muted small mt-2">Berdasarkan kalkulasi periode terakhir.</p>
                    </div>
                </div>
            </div>

            <!-- Card 2: Discipline Status -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 stats-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-4">
                            <div class="stats-icon bg-primary text-white shadow-lg me-3"
                                style="width: 50px; height: 50px; border-radius: 14px;">
                                <i class="fas fa-calendar-check fa-lg"></i>
                            </div>
                            <h6 class="text-muted fw-800 small text-uppercase mb-0 ls-1">Status Disiplin</h6>
                        </div>
                        <div class="d-flex flex-column gap-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small fw-600 text-muted">Kas Bulan Ini</span>
                                <span class="badge <?= $myKas == 'sudah' ? 'bg-brand-green-soft text-brand-green' : 'bg-danger-soft text-danger' ?> rounded-pill px-3 py-1">
                                    <?= strtoupper($myKas) ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small fw-600 text-muted">Presensi Aktif</span>
                                <span class="fw-800 text-dark"><?= $myHadir ?>%</span>
                            </div>
                        </div>
                        <div class="progress mt-3 bg-slate-100 shadow-none" style="height: 6px; border-radius: 10px;">
                            <div class="progress-bar bg-primary rounded-pill" style="width: <?= $myHadir ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Trend KPI Chart and Leaderboard -->
    <div class="row g-4 mt-2">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-4 px-4 border-bottom border-light">
                    <h6 class="mb-0 fw-800 text-dark"><?= $is_admin_view ? 'Tren Kinerja Kolektif (Grafik KPI)' : 'Grafik Performa Saya (KPI)' ?></h6>
                </div>
                <div class="card-body p-4">
                    <canvas id="kpiChart" height="280"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <?php if ($is_admin_view): ?>
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 h-100">
                    <div class="card-header bg-white border-0 py-4 px-4 border-bottom border-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-800 text-dark">Leaderboard (Top 3)</h6>
                        <i class="fas fa-crown text-amber-400"></i>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php
                            $stmtLeader = $pdo->prepare("SELECT p.nama, r.nama_role, n.nilai_kpi_total 
                                                       FROM tabel_nilai_kpi n
                                                       JOIN tabel_pengurus p ON n.nokta = p.nokta
                                                       JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND n.kepengurusan_id = j.kepengurusan_id
                                                       JOIN tabel_role r ON j.role_id = r.id_role
                                                       WHERE n.kepengurusan_id = ?
                                                       AND n.bulan = ? AND n.tahun = ?
                                                       AND r.nama_role NOT IN ('Super Admin', 'Sekjend', 'Bendum', 'PPI', 'Koorkam', 'Kabiro')
                                                       ORDER BY n.nilai_kpi_total DESC LIMIT 3");
                            $stmtLeader->execute([$active_id, $periodeAktif['bulan'] ?? 0, $periodeAktif['tahun'] ?? 0]);
                            $leaders = $stmtLeader->fetchAll();

                            if ($leaders):
                                foreach ($leaders as $rank => $lead):
                                    $colors = ['bg-warning', 'bg-secondary', 'bg-amber-600'];
                                    ?>
                                    <div class="list-group-item p-4 border-0 border-bottom-light transparency-hover">
                                        <div class="d-flex align-items-center">
                                            <div class="rank-badge <?= $colors[$rank] ?? 'bg-light' ?> text-white rounded-circle me-3 d-flex align-items-center justify-content-center fw-800 shadow-sm"
                                                style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                <?= $rank + 1 ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="fw-800 text-dark mb-0"><?= $lead['nama'] ?></h6>
                                                <small class="text-muted fw-600"><?= $lead['nama_role'] ?></small>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-800 text-brand-red"><?= number_format($lead['nilai_kpi_total'], 2) ?></div>
                                                <small class="text-muted small ls-1 opacity-50">KPI SCORE</small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; else: ?>
                                <div class="p-5 text-center">
                                    <p class="text-muted small mb-0">Belum ada data.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($periodeAktif && ($periodeAktif['mode_penilaian'] ?? 'PPI') === 'Peer Assessment'): ?>
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 <?= $remAssess > 0 ? 'border-amber border-2' : '' ?> h-100"
                    style="background: linear-gradient(135deg, #fff 0%, #fff9f0 100%);">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-4">
                            <div class="stats-icon bg-amber-400 text-white shadow-lg me-3"
                                style="width: 50px; height: 50px; border-radius: 14px;">
                                <i class="fas fa-exclamation-circle fa-lg"></i>
                            </div>
                            <h6 class="text-muted fw-800 small text-uppercase mb-0 ls-1">Tugas Penilaian</h6>
                        </div>
                        <div class="mb-3">
                            <?php if ($remAssess > 0): ?>
                                <h2 class="fw-800 text-amber-600 display-6 mb-0"><?= $remAssess ?></h2>
                                <p class="text-muted small fw-600 mb-0">Orang belum Anda nilai bulan ini.</p>
                            <?php else: ?>
                                <h4 class="fw-800 text-brand-green mb-0"><i class="fas fa-check-circle me-2"></i>Selesai!</h4>
                                <p class="text-muted small fw-600 mb-0">Semua tugas penilaian tuntas.</p>
                            <?php endif; ?>
                        </div>
                        <?php if ($remAssess > 0): ?>
                            <a href="<?= base_url('ppi/penilaian_input.php') ?>"
                                class="btn btn-amber w-100 rounded-pill fw-800 mt-2 py-2">
                                Beri Penilaian <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div> 
    </div>

    <!-- Trend Presensi (Kehadiran) & Trend Kas charts -->
    <div class="row g-4 mt-2 mb-5">
        <!-- Grafik Kehadiran -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-4 px-4 border-bottom border-light">
                    <h6 class="mb-0 fw-800 text-dark">
                        <?= $is_admin_view ? 'Tren Presensi Kehadiran' : 'Grafik Presensi Kehadiran Saya' ?>
                    </h6>
                </div>
                <div class="card-body p-4">
                    <canvas id="hadirChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <!-- Grafik Kas -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-4 px-4 border-bottom border-light">
                    <h6 class="mb-0 fw-800 text-dark">
                        <?= $is_admin_view ? 'Tren Alur Keuangan Kas' : 'Grafik Setoran Kas Saya' ?>
                    </h6>
                </div>
                <div class="card-body p-4">
                    <canvas id="kasChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
</div> 

<style>
    .bg-light-soft { background-color: #f1f5f9; border: none; }
    .stats-icon { display: flex; align-items: center; justify-content: center; }
    .fw-800 { font-weight: 800; }
    .fw-600 { font-weight: 600; }
    .ls-1 { letter-spacing: 0.5px; }
    .rounded-4 { border-radius: 1.5rem !important; }
    .border-bottom-light { border-bottom: 1px solid #f1f5f9; }
    .transparency-hover:hover { background-color: #fcfdfe; transition: all 0.3s ease; }
    .stats-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
    .stats-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0, 0, 0, 0.08) !important; }
    .border-amber { border-color: #fbbf24 !important; }
    .btn-amber { background-color: #fbbf24; color: #fff; border: none; transition: all 0.3s ease; }
    .btn-amber:hover { background-color: #f59e0b; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3); }
    .text-amber-600 { color: #d97706; }
</style>

<?php
$plotLabels = [];
$plotData = [];

if ($is_admin_view) {
    $stmtTrend = $pdo->prepare("SELECT bulan, tahun, AVG(nilai_kpi_total) as avg_score 
                              FROM tabel_nilai_kpi 
                              WHERE kepengurusan_id = ? 
                              GROUP BY tahun, bulan 
                              ORDER BY tahun ASC, bulan ASC LIMIT 6");
    $stmtTrend->execute([$active_id]);
} else {
    // Trend Personal
    $stmtTrend = $pdo->prepare("SELECT bulan, tahun, nilai_kpi_total as avg_score 
                              FROM tabel_nilai_kpi 
                              WHERE nokta = ? AND kepengurusan_id = ? 
                              ORDER BY tahun ASC, bulan ASC LIMIT 6");
    $stmtTrend->execute([$my_nokta, $active_id]);
}

$trendResults = $stmtTrend->fetchAll();
foreach ($trendResults as $tr) {
    $plotLabels[] = $tr['bulan'] . '/' . substr($tr['tahun'], 2);
    $plotData[] = round($tr['avg_score'], 2);
}

// Fallback if no data
if (empty($plotData)) {
    $plotLabels = ['Data Kosong'];
    $plotData = [0];
}
?>

<script>
    // 1. KPI Trend Chart
    const ctx = document.getElementById('kpiChart').getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(220, 38, 38, 0.25)'); // Red 600 soft
    gradient.addColorStop(1, 'rgba(220, 38, 38, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($plotLabels) ?>,
            datasets: [{
                label: 'KPI Trend',
                data: <?= json_encode($plotData) ?>,
                borderColor: '#DC2626', // Red 600
                borderWidth: 5,
                tension: 0.45,
                pointRadius: 6,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#DC2626',
                pointBorderWidth: 3,
                fill: true,
                backgroundColor: gradient
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    padding: 12,
                    titleFont: { size: 14, weight: 'bold' },
                    bodyFont: { size: 13 },
                    cornerRadius: 8,
                    displayColors: false
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    min: 0,
                    max: 4.0,
                    ticks: { stepSize: 1, font: { weight: '600' } },
                    grid: { color: '#f1f5f9' }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { weight: '600' } }
                }
            }
        }
    });

    // 2. Attendance Trend Chart
    const ctxHadir = document.getElementById('hadirChart').getContext('2d');
    const gradientHadir = ctxHadir.createLinearGradient(0, 0, 0, 400);
    gradientHadir.addColorStop(0, 'rgba(22, 163, 74, 0.25)'); // Green soft
    gradientHadir.addColorStop(1, 'rgba(22, 163, 74, 0.0)');
    
    new Chart(ctxHadir, {
        type: 'line',
        data: {
            labels: <?= json_encode($hadirLabels) ?>,
            datasets: [{
                label: 'Kehadiran (%)',
                data: <?= json_encode($hadirData) ?>,
                borderColor: '#16A34A', // Green 600
                borderWidth: 4,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#16A34A',
                pointBorderWidth: 2,
                fill: true,
                backgroundColor: gradientHadir
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    min: 0,
                    max: 100,
                    ticks: { stepSize: 20, font: { weight: '600' } },
                    grid: { color: '#f1f5f9' }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { weight: '600' } }
                }
            }
        }
    });

    // 3. Kas Trend Chart
    const ctxKas = document.getElementById('kasChart').getContext('2d');
    <?php if ($is_admin_view): ?>
    new Chart(ctxKas, {
        type: 'bar',
        data: {
            labels: <?= json_encode($kasLabels) ?>,
            datasets: [
                {
                    label: 'Kas Masuk (Rp)',
                    data: <?= json_encode($kasMasukData) ?>,
                    backgroundColor: '#16A34A',
                    borderRadius: 6
                },
                {
                    label: 'Kas Keluar (Rp)',
                    data: <?= json_encode($kasKeluarData) ?>,
                    backgroundColor: '#DC2626',
                    borderRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { font: { weight: '600' } },
                    grid: { color: '#f1f5f9' }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { weight: '600' } }
                }
            }
        }
    });
    <?php else: ?>
    const gradientKas = ctxKas.createLinearGradient(0, 0, 0, 400);
    gradientKas.addColorStop(0, 'rgba(37, 99, 235, 0.25)'); // Blue soft
    gradientKas.addColorStop(1, 'rgba(37, 99, 235, 0.0)');
    
    new Chart(ctxKas, {
        type: 'line',
        data: {
            labels: <?= json_encode($kasLabels) ?>,
            datasets: [{
                label: 'Setoran (Rp)',
                data: <?= json_encode($kasData) ?>,
                borderColor: '#2563EB', // Blue 600
                borderWidth: 4,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#2563EB',
                pointBorderWidth: 2,
                fill: true,
                backgroundColor: gradientKas
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { font: { weight: '600' } },
                    grid: { color: '#f1f5f9' }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { weight: '600' } }
                }
            }
        }
    });
    <?php endif; ?>
</script>

<!-- MODAL DETAIL PENGURUS BELUM DINILAI DI DASHBOARD -->
<div class="modal fade" id="unratedDashboardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                <h6 class="modal-title fw-800 mb-0" id="unratedDashboardModalTitle">
                    <i class="fas fa-user-clock me-2"></i>Pengurus Belum Dinilai
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-white">
                <p class="text-muted small mb-3">Berikut adalah anggota di biro ini yang belum dinilai pada periode aktif:</p>
                <div class="list-group list-group-flush border rounded-3 mb-3 overflow-auto" id="unratedDashboardModalList" style="max-height: 320px;">
                    <!-- Diisi via JavaScript -->
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Tutup</button>
                    <a href="<?= base_url('ppi/penilaian_input.php') ?>" class="btn btn-primary rounded-pill px-4 fw-800 shadow-sm">
                        <i class="fas fa-edit me-1"></i> Buka Modul Penilaian
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openUnratedDashboardModal(biroName, unratedMembers) {
    document.getElementById('unratedDashboardModalTitle').innerHTML = '<i class="fas fa-user-clock me-2"></i>Belum Dinilai: ' + biroName;
    const listContainer = document.getElementById('unratedDashboardModalList');
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
                <span class="badge bg-danger-soft text-danger fw-bold px-3 py-1 rounded-pill">Belum Ada Nilai</span>
            `;
            listContainer.appendChild(item);
        });
    }
    
    const modal = new bootstrap.Modal(document.getElementById('unratedDashboardModal'));
    modal.show();
}
</script>

<?php include '../layout/footer.php'; ?>