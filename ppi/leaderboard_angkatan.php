<?php
require_once '../config/database.php';
session_start();
check_login();
check_permission('leaderboard.view');


$title = "Leaderboard Angkatan";
include '../layout/header.php';
include '../layout/sidebar.php';

$active_p = get_active_kepengurusan();
$active_id = $active_p['id_kepengurusan'] ?? 0;

$stmtAktif = $pdo->prepare("SELECT * FROM tabel_periode WHERE status = 'aktif' AND kepengurusan_id = ? LIMIT 1");
$stmtAktif->execute([$active_id]);
$activeM = $stmtAktif->fetch();

$bulan = $activeM['bulan'] ?? null;
$tahun = $activeM['tahun'] ?? null;

$stmtAllP = $pdo->prepare("SELECT id_periode, bulan, tahun, jenis_periode, mode_penilaian FROM tabel_periode WHERE kepengurusan_id = ? ORDER BY tahun DESC, bulan DESC");
$stmtAllP->execute([$active_id]);
$allPeriods = $stmtAllP->fetchAll();

$view_bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : ($activeM['bulan'] ?? null);
$view_tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : ($activeM['tahun'] ?? null);

$is_historical = ($view_bulan != ($activeM['bulan'] ?? 0) || $view_tahun != ($activeM['tahun'] ?? 0));

$excludedRoles = "'Super Admin', 'Sekjend', 'Bendum', 'PPI', 'Koorkam', 'Kabiro'";

function getLeaderboardByAngkatan($pdo, $active_id, $bulan, $tahun, $angkatan, $excludedRoles) {
    if (!$bulan || !$tahun) return [];
    
    $stmt = $pdo->prepare("SELECT p.nama, p.nokta, r.nama_role, n.nilai_kpi_total, p.angkatan
                          FROM tabel_nilai_kpi n
                          JOIN tabel_pengurus p ON n.nokta = p.nokta
                          JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND n.kepengurusan_id = j.kepengurusan_id
                          JOIN tabel_role r ON j.role_id = r.id_role
                          WHERE n.kepengurusan_id = ? 
                          AND n.bulan = ? AND n.tahun = ?
                          AND p.angkatan = ?
                          AND r.nama_role NOT IN ($excludedRoles)
                          ORDER BY n.nilai_kpi_total DESC LIMIT 10");
    $stmt->execute([$active_id, $bulan, $tahun, $angkatan]);
    return $stmt->fetchAll();
}

$leaderboard2024 = getLeaderboardByAngkatan($pdo, $active_id, $view_bulan, $view_tahun, '2024', $excludedRoles);
$leaderboard2025 = getLeaderboardByAngkatan($pdo, $active_id, $view_bulan, $view_tahun, '2025', $excludedRoles);
?>

<div id="content" class="fade-in">
    <div class="row align-items-end mb-4 g-3">
        <div class="col-md-6">
            <h4 class="fw-800 text-brand-red mb-1">Leaderboard per Angkatan</h4>
            <p class="text-muted small mb-0"><?= $is_historical ? '<span class="badge bg-warning-soft text-warning me-1"><i class="fas fa-history"></i> Mode Riwayat</span>' : '<span class="badge bg-brand-green-soft text-brand-green me-1"><i class="fas fa-check-circle"></i> Mode Aktif</span>' ?> Memantau performa pengurus.</p>
        </div>
        <div class="col-md-6">
            <form method="GET" class="d-flex gap-2 justify-content-md-end align-items-center">
                <div class="input-group input-group-sm rounded-pill overflow-hidden border shadow-sm" style="max-width: 280px;">
                    <span class="input-group-text bg-white border-0 ps-3"><i class="fas fa-filter text-muted"></i></span>
                    <select name="period" class="form-select border-0 fw-600" onchange="const [b,t] = this.value.split('|'); window.location.href='?bulan='+b+'&tahun='+t;">
                        <option value="">Pilih Periode Audit...</option>
                        <?php foreach($allPeriods as $ap): 
                            $val = $ap['bulan'].'|'.$ap['tahun'];
                            $sel = ($view_bulan == $ap['bulan'] && $view_tahun == $ap['tahun']) ? 'selected' : '';
                        ?>
                            <option value="<?= $val ?>" <?= $sel ?>><?= format_nama_periode($ap) ?> <?= ($ap['bulan']==($activeM['bulan'] ?? null) && $ap['tahun']==($activeM['tahun'] ?? null)) ? '(Aktif)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if($is_historical): ?>
                    <a href="leaderboard_angkatan.php" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Reset ke Aktif"><i class="fas fa-redo"></i></a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                <div class="card-header bg-white py-4 px-4 border-bottom border-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-800 text-dark">Top Performa Angkatan 2024</h6>
                    <span class="badge bg-brand-red-soft text-brand-red rounded-pill px-3 py-1">Angkatan 2024</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-slate-50 text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4">Rank</th>
                                    <th>Nama</th>
                                    <th>Skor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($leaderboard2024): foreach($leaderboard2024 as $rank => $u): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="rank-badge <?= $rank == 0 ? 'bg-warning text-white' : 'bg-light text-muted' ?> rounded-circle d-flex align-items-center justify-content-center fw-800 shadow-sm" style="width: 28px; height: 28px; font-size: 0.75rem;">
                                            <?= $rank + 1 ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-800 text-dark"><?= $u['nama'] ?></div>
                                        <div class="text-muted small"><?= $u['nama_role'] ?></div>
                                    </td>
                                    <td>
                                        <div class="badge bg-brand-green-soft text-brand-green rounded-pill px-3 py-1 fw-bold">
                                            <?= number_format($u['nilai_kpi_total'] ?? 0, 2) ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="3" class="text-center py-5 text-muted small">Belum ada data nilai.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                <div class="card-header bg-white py-4 px-4 border-bottom border-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-800 text-dark">Top Performa Angkatan 2025</h6>
                    <span class="badge bg-primary-soft text-primary rounded-pill px-3 py-1">Angkatan 2025</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-slate-50 text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4">Rank</th>
                                    <th>Nama</th>
                                    <th>Skor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($leaderboard2025): foreach($leaderboard2025 as $rank => $u): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="rank-badge <?= $rank == 0 ? 'bg-warning text-white' : 'bg-light text-muted' ?> rounded-circle d-flex align-items-center justify-content-center fw-800 shadow-sm" style="width: 28px; height: 28px; font-size: 0.75rem;">
                                            <?= $rank + 1 ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-800 text-dark"><?= $u['nama'] ?></div>
                                        <div class="text-muted small"><?= $u['nama_role'] ?></div>
                                    </td>
                                    <td>
                                        <div class="badge bg-brand-green-soft text-brand-green rounded-pill px-3 py-1 fw-bold">
                                            <?= number_format($u['nilai_kpi_total'] ?? 0, 2) ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="3" class="text-center py-5 text-muted small">Belum ada data nilai.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../layout/footer.php'; ?>
