<?php
require_once '../config/database.php';
session_start();
check_login();
check_permission('kpi.view');


$active_p = get_active_kepengurusan();
if (!$active_p) {
    die("Error: Tahun Kepengurusan tidak aktif.");
}

$stmtAktif = $pdo->prepare("SELECT * FROM tabel_periode WHERE status = 'aktif' AND kepengurusan_id = ? LIMIT 1");
$stmtAktif->execute([$active_p['id_kepengurusan']]);
$active = $stmtAktif->fetch();

if (!$active) {
    $title = "Monitoring Penilaian";
    include '../layout/header.php';
    include '../layout/sidebar.php';
    echo '<div id="content" class="text-center mt-5">
            <div class="py-5">
                <i class="fas fa-calendar-times fa-4x text-muted opacity-25 mb-3"></i>
                <h4 class="fw-800 text-muted">Bulan Penilaian Belum Dibuka</h4>
                <p class="text-muted small">Silakan buka bulan penilaian terlebih dahulu di menu <a href="bulan_penilaian.php" class="text-primary fw-600">Bulan Penilaian</a>.</p>
            </div>
          </div>';
    include '../layout/footer.php';
    exit;
}

$bulan = $active['bulan'];
$tahun = $active['tahun'];
$me_nokta = $_SESSION['user']['nokta'];
$ppi_info = get_user_ppi_info($me_nokta, $active_p['id_kepengurusan']);

if ($ppi_info['is_staff_pj'] && $ppi_info['biro_id']) {
    $stmtMonitoring = $pdo->prepare("SELECT p.nokta, p.nama, j.jabatan, r.nama_role, b.nama_biro,
                                     (SELECT COUNT(DISTINCT dinilai_nokta) FROM tabel_penilaian WHERE penilai_nokta = p.nokta AND bulan = ? AND tahun = ?) as total_dinilai
                                     FROM tabel_pengurus p
                                     JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
                                     JOIN tabel_role r ON j.role_id = r.id_role
                                     LEFT JOIN tabel_biro b ON j.biro_id = b.id_biro
                                     WHERE r.nama_role NOT IN ('Super Admin', 'PJnas', 'PJNas') AND (p.angkatan IS NULL OR p.angkatan != '2023') AND j.biro_id = ?
                                     ORDER BY p.nama ASC");
    $stmtMonitoring->execute([$bulan, $tahun, $active_p['id_kepengurusan'], $ppi_info['biro_id']]);
} else {
    $stmtMonitoring = $pdo->prepare("SELECT p.nokta, p.nama, j.jabatan, r.nama_role, b.nama_biro,
                                     (SELECT COUNT(DISTINCT dinilai_nokta) FROM tabel_penilaian WHERE penilai_nokta = p.nokta AND bulan = ? AND tahun = ?) as total_dinilai
                                     FROM tabel_pengurus p
                                     JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
                                     JOIN tabel_role r ON j.role_id = r.id_role
                                     LEFT JOIN tabel_biro b ON j.biro_id = b.id_biro
                                     WHERE r.nama_role NOT IN ('Super Admin', 'PJnas', 'PJNas') AND (p.angkatan IS NULL OR p.angkatan != '2023')
                                     ORDER BY p.nama ASC");
    $stmtMonitoring->execute([$bulan, $tahun, $active_p['id_kepengurusan']]);
}
$monitoring = $stmtMonitoring->fetchAll();

$stmtRatees = $pdo->prepare("SELECT COUNT(*) FROM tabel_pengurus_jabatan j 
                             JOIN tabel_pengurus p ON j.nokta = p.nokta
                             JOIN tabel_role r ON j.role_id = r.id_role
                             WHERE j.kepengurusan_id = ? 
                             AND r.nama_role NOT IN ('Super Admin', 'PJnas', 'PJNas')
                             AND (p.angkatan IS NULL OR p.angkatan != '2023')");
$stmtRatees->execute([$active_p['id_kepengurusan']]);
$countTotalPengurus = (int)$stmtRatees->fetchColumn();

$monitoring = array_map(function($m) use ($countTotalPengurus) {
    $m['target'] = max(0, $countTotalPengurus - 1);
    $m['is_done'] = $m['total_dinilai'] >= $m['target'];
    return $m;
}, $monitoring);

$completed_count = count(array_filter($monitoring, fn($m) => $m['is_done']));
$pending_count = count($monitoring) - $completed_count;

// Fetch Biro Breakdown for Kepala PPI
$biro_stats = [];
if ($ppi_info['is_kepala']) {
    $stmtBiros = $pdo->prepare("SELECT id_biro, nama_biro FROM tabel_biro WHERE kepengurusan_id = ? ORDER BY nama_biro ASC");
    $stmtBiros->execute([$active_p['id_kepengurusan']]);
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
        $stmtMembers->execute([$bulan, $tahun, $active_p['id_kepengurusan'], $b['id_biro']]);
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
        $stmtPJ->execute([$active_p['id_kepengurusan'], $b['id_biro']]);
        $pj_data = $stmtPJ->fetch();

        $biro_stats[] = [
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

$title = "Monitoring Penilaian";
include '../layout/header.php';
include '../layout/sidebar.php';
?>

<div id="content" class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 g-3 flex-wrap">
        <div>
            <h4 class="fw-800 text-dark mb-1">Monitoring Partisipasi Penilaian</h4>
            <p class="text-muted small mb-0">Periode: <strong class="text-dark"><?= $bulan ?>/<?= $tahun ?></strong> (<?= htmlspecialchars($active['mode_penilaian'] ?? 'PPI') ?> - <?= htmlspecialchars($active['jenis_periode'] ?? 'Bulanan') ?>)</p>
        </div>
        <div>
            <a href="<?= base_url('ppi/penilaian_input.php') ?>" class="btn btn-primary rounded-pill px-4 fw-800 shadow-sm">
                <i class="fas fa-edit me-1"></i> Buka Modul Penilaian
            </a>
        </div>
    </div>

    <?php if ($biro_stats): ?>
        <!-- Monitoring Biro khusus Kepala PPI -->
        <div class="mb-4">
            <h6 class="fw-800 text-dark mb-3"><i class="fas fa-chart-pie text-muted me-2"></i>Progres Penilaian Per Biro</h6>
            <div class="row g-3">
                <?php foreach($biro_stats as $bs): 
                    $is_complete = ($bs['tot_pengurus'] > 0 && $bs['sudah_dinilai'] >= $bs['tot_pengurus']);
                ?>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-baseline mb-2">
                                    <span class="fw-800 text-dark" style="font-size: 0.95rem;">Biro <?= htmlspecialchars($bs['nama_biro']) ?></span>
                                    <span class="fw-800 <?= $is_complete ? 'text-success' : 'text-dark' ?>" style="font-size: 1.05rem;"><?= $bs['persen'] ?>%</span>
                                </div>
                                <div class="small text-muted mb-3">PJ: <span class="text-dark fw-600"><?= htmlspecialchars($bs['pj_nama']) ?></span></div>
                                
                                <div class="progress mb-3 bg-slate-100" style="height: 6px; border-radius: 3px;">
                                    <div class="progress-bar <?= $is_complete ? 'bg-success' : 'bg-dark' ?>" role="progressbar" style="width: <?= $bs['persen'] ?>%"></div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center very-small text-muted border-top border-light pt-2">
                                    <span>Sudah: <strong class="text-dark"><?= $bs['sudah_dinilai'] ?></strong> / <?= $bs['tot_pengurus'] ?></span>
                                    <?php if ($bs['belum_dinilai'] > 0): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 rounded-pill fw-bold" style="font-size: 0.7rem; border-width: 1px;"
                                                onclick='openUnratedMonitorModal("<?= addslashes($bs['nama_biro']) ?>", <?= htmlspecialchars(json_encode($bs['unrated_members']), ENT_QUOTES, 'UTF-8') ?>)'>
                                            Belum: <?= $bs['belum_dinilai'] ?> <i class="fas fa-chevron-right ms-1 very-small opacity-50"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-success fw-bold"><i class="fas fa-check me-1"></i>Selesai</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (($active['mode_penilaian'] ?? 'PPI') === 'Peer Assessment'): ?>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="avatar-sm bg-primary-soft text-primary rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 42px; height: 42px;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-600">Total Penilai</div>
                        <div class="fw-800 text-dark"><?= count($monitoring) ?> <span class="text-muted small fw-600">Pengurus</span></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white text-brand-green">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="avatar-sm bg-brand-green-soft text-brand-green rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 42px; height: 42px;">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-600">Sudah Selesai</div>
                        <div class="fw-800 text-dark"><?= $completed_count ?> <span class="text-muted small fw-600">Pengurus</span></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white text-danger">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="avatar-sm bg-danger-soft text-danger rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 42px; height: 42px;">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-600">Belum Selesai</div>
                        <div class="fw-800 text-dark"><?= $pending_count ?> <span class="text-muted small fw-600">Pengurus</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-slate-50 text-muted small text-uppercase fw-bold">
                        <tr>
                            <th class="ps-4 py-3">Nama Pengurus</th>
                            <th>Jabatan & Role</th>
                            <th class="text-center" width="280">Progres Partisipasi</th>
                            <th class="text-center pe-4" style="width: 180px;">Status Akurat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($monitoring): foreach($monitoring as $m): 
                            $percent = $m['target'] > 0 ? min(100, ($m['total_dinilai'] / $m['target']) * 100) : 100;
                            $is_done = $m['is_done'];
                        ?>
                        <tr class="modern-row <?= $is_done ? 'done-row' : '' ?>">
                            <td class="ps-4">
                                <div class="d-flex align-items-center py-2">
                                    <div class="avatar-md me-3 bg-primary-soft text-primary rounded-circle d-flex align-items-center justify-content-center fw-800">
                                        <?= substr($m['nama'] ?? '-', 0, 1) ?>
                                    </div>
                                    <div class="fw-800 text-dark mb-0"><?= $m['nama'] ?></div>
                                </div>
                            </td>
                            <td>
                                <div class="small fw-800 text-dark"><?= $m['jabatan'] ?></div>
                                <div class="text-muted small ls-1"><i class="fas fa-tag me-1 opacity-50"></i><?= $m['nama_role'] ?></div>
                            </td>
                            <td class="text-center px-4">
                                <div class="d-flex align-items-center">
                                    <div class="progress flex-grow-1 shadow-none bg-slate-100 me-2" style="height: 8px; border-radius: 10px;">
                                        <div class="progress-bar <?= $is_done ? 'bg-brand-red' : 'bg-primary' ?> rounded-pill" role="progressbar" 
                                             style="width: <?= $percent ?>%"></div>
                                    </div>
                                    <span class="small fw-800 text-dark"><?= $m['total_dinilai'] ?>/<?= $m['target'] ?></span>
                                </div>
                            </td>
                            <td class="text-center pe-4">
                                <?php if($is_done): ?>
                                    <span class="badge bg-brand-green-soft text-brand-green px-3 py-2 rounded-pill fw-bold" style="font-size: 0.65rem;">
                                        <i class="fas fa-check-circle me-1"></i> KOMPLIT
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-soft text-danger px-3 py-2 rounded-pill fw-bold" style="font-size: 0.65rem;">
                                        <i class="fas fa-clock me-1"></i> PROGRES
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted fw-bold">Belum ada pengurus yang wajib menilai.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="mb-4 mt-5">
        <h4 class="fw-800 text-brand-red mb-1">Hasil Kalkulasi KPI</h4>
        <p class="text-muted small mb-0">Nilai akhir yang tersimpan di sistem untuk periode <span class="fw-800 text-dark"><?= $bulan ?>/<?= $tahun ?></span></p>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-slate-50 text-muted small text-uppercase fw-bold">
                        <tr>
                            <th class="ps-4 py-3">Nama Pengurus</th>
                            <th class="text-center">Attitude</th>
                            <th class="text-center">Komunikasi</th>
                            <th class="text-center">Disiplin</th>
                            <th class="text-center pe-4" style="width: 150px;">KPI TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $stmtResults = $pdo->prepare("SELECT p.nama, r.nama_role, n.* 
                                                     FROM tabel_nilai_kpi n
                                                     JOIN tabel_pengurus p ON n.nokta = p.nokta
                                                     JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND n.kepengurusan_id = j.kepengurusan_id
                                                     JOIN tabel_role r ON j.role_id = r.id_role
                                                     WHERE n.bulan = ? AND n.tahun = ? AND n.kepengurusan_id = ?
                                                     AND r.nama_role NOT IN ('Super Admin', 'PJnas', 'PJNas')
                                                     AND (p.angkatan IS NULL OR p.angkatan != '2023')
                                                     ORDER BY n.nilai_kpi_total DESC");
                        $stmtResults->execute([$bulan, $tahun, $active_p['id_kepengurusan']]);
                        $results = $stmtResults->fetchAll();

                        if($results): foreach($results as $r): 
                        ?>
                        <tr class="modern-row">
                            <td class="ps-4 py-3">
                                <div class="fw-800 text-dark mb-0"><?= $r['nama'] ?></div>
                                <div class="text-muted small ls-1"><?= $r['nama_role'] ?></div>
                            </td>
                            <td class="text-center fw-800 text-muted"><?= number_format($r['nilai_attitude'], 2) ?></td>
                            <td class="text-center fw-800 text-muted"><?= number_format($r['nilai_komunikasi'], 2) ?></td>
                            <td class="text-center fw-800 text-muted"><?= number_format($r['nilai_disiplin'], 2) ?></td>
                            <td class="text-center pe-4">
                                <div class="badge bg-brand-red text-white px-3 py-2 rounded-pill fw-bold" style="font-size: 0.85rem; width: 100px;">
                                    <?= number_format($r['nilai_kpi_total'], 2) ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted fw-bold">Penilaian bulan ini belum dikalkulasi.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DETAIL PENGURUS BELUM DINILAI -->
<div class="modal fade" id="unratedMonitorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                <h6 class="modal-title fw-800 mb-0" id="unratedMonitorModalTitle">
                    <i class="fas fa-user-clock me-2"></i>Pengurus Belum Dinilai
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-white">
                <p class="text-muted small mb-3">Berikut adalah anggota di biro ini yang belum dinilai pada periode aktif:</p>
                <div class="list-group list-group-flush border rounded-3 mb-3 overflow-auto" id="unratedMonitorModalList" style="max-height: 320px;">
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
function openUnratedMonitorModal(biroName, unratedMembers) {
    document.getElementById('unratedMonitorModalTitle').innerHTML = '<i class="fas fa-user-clock me-2"></i>Belum Dinilai: ' + biroName;
    const listContainer = document.getElementById('unratedMonitorModalList');
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
    
    const modal = new bootstrap.Modal(document.getElementById('unratedMonitorModal'));
    modal.show();
}
</script>

<style>
.bg-slate-50 { background-color: #f8fafc; }
.bg-slate-100 { background-color: #f1f5f9; }
.bg-primary-soft { background-color: rgba(220, 38, 38, 0.1); }
.bg-brand-green-soft { background-color: #FEF2F2; color: #DC2626; }
.text-brand-green { color: #DC2626; }
.bg-brand-red { background-color: #DC2626; }
.bg-danger-soft { background-color: #F0FDF4; color: #16A34A; }
.avatar-sm { width: 34px; height: 34px; border-radius: 10px; }
.avatar-md { width: 42px; height: 42px; border-radius: 14px; font-size: 1.1rem; }
.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.modern-row:hover { background-color: #f9fafb; }
.done-row { background-color: #fbfdfc; }
.ls-1 { letter-spacing: 0.5px; }
.rounded-4 { border-radius: 1.5rem !important; }
</style>

<?php include '../layout/footer.php'; ?>


