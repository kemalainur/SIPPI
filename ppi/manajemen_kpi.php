<?php
require_once '../config/database.php';
session_start();
check_login();
check_permission('kpi.manage');


$title = "Manajemen KPI & Bobot";
include '../layout/header.php';
include '../layout/sidebar.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_weights'])) {
    $weights = $_POST['bobot']; // Array: kunci => value
    
    $sumUtama = 0;
    $sumDisiplin = 0;
    
    $stmtCats = $pdo->query("SELECT kunci, kategori FROM tabel_pengaturan_kpi");
    $cats = [];
    while($r = $stmtCats->fetch()) $cats[$r['kunci']] = $r['kategori'];

    foreach($weights as $k => $v) {
        if(($cats[$k] ?? '') == 'utama') $sumUtama += (float)$v;
        if(($cats[$k] ?? '') == 'disiplin') $sumDisiplin += (float)$v;
    }

    if($sumUtama != 100) {
        $error = "Gagal: Total Bobot Utama harus berjumlah 100% (Saat ini: $sumUtama%).";
    } elseif($sumDisiplin != 100) {
        $error = "Gagal: Total Bobot Sub-Indikator Disiplin harus berjumlah 100% (Saat ini: $sumDisiplin%).";
    } else {
        // Save
        $stmtUpdate = $pdo->prepare("UPDATE tabel_pengaturan_kpi SET bobot = ? WHERE kunci = ?");
        foreach($weights as $k => $v) {
            $stmtUpdate->execute([$v, $k]);
        }
        $message = "Konfigurasi Bobot KPI berhasil diperbarui!";
    }
}

$settings = $pdo->query("SELECT * FROM tabel_pengaturan_kpi ORDER BY kategori DESC, kunci ASC")->fetchAll();
$utama = array_filter($settings, fn($s) => $s['kategori'] == 'utama');
$disiplin = array_filter($settings, fn($s) => $s['kategori'] == 'disiplin');
?>

<div id="content" class="fade-in">
    <div class="mb-4">
        <h4 class="fw-800 text-brand-red mb-1">Manajemen & Konfigurasi KPI</h4>
        <p class="text-muted small">Tentukan bobot prioritas untuk setiap indikator penilaian organisasi.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 py-3 d-flex align-items-center mb-4">
            <i class="fas fa-check-circle me-3 fa-lg text-brand-green"></i>
            <div class="fw-600"><?= $message ?></div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 py-3 d-flex align-items-center mb-4">
            <i class="fas fa-exclamation-triangle me-3 fa-lg"></i>
            <div class="fw-600"><?= $error ?></div>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                    <div class="card-header bg-white border-0 py-4 px-4 border-bottom border-light">
                        <h6 class="mb-0 fw-800"><i class="fas fa-layer-group text-primary me-2"></i>Bobot KPI Utama (Total 100%)</h6>
                    </div>
                    <div class="card-body p-4 bg-white">
                        <p class="text-muted small mb-4">Pembagian bobot untuk tiga pilar utama penilaian kinerja pengurus.</p>
                        <?php foreach($utama as $s): ?>
                        <div class="mb-4">
                            <label class="form-label small fw-800 text-muted ls-1 text-uppercase d-flex justify-content-between">
                                <?= $s['label'] ?>
                                <span class="text-primary" id="val_<?= $s['kunci'] ?>"><?= $s['bobot'] ?>%</span>
                            </label>
                            <div class="d-flex align-items-center gap-3">
                                <input type="range" name="bobot[<?= $s['kunci'] ?>]" class="form-range flex-grow-1" 
                                       min="0" max="100" step="5" value="<?= $s['bobot'] ?>"
                                       oninput="document.getElementById('val_<?= $s['kunci'] ?>').innerText = this.value + '%'">
                                <input type="number" class="form-control text-center fw-800 border-0 bg-light" style="width: 80px;" value="<?= $s['bobot'] ?>" readonly disabled>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="alert alert-info border-0 rounded-4 bg-slate-50 mb-0">
                            <i class="fas fa-info-circle me-2"></i> Metode: <strong>Attitude & Komunikasi</strong> dipengaruhi oleh penilaian sejawat (Peer). <strong>Disiplin</strong> dihitung secara otomatis.
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                    <div class="card-header bg-white border-0 py-4 px-4 border-bottom border-light">
                        <h6 class="mb-0 fw-800"><i class="fas fa-user-clock text-primary me-2"></i>Sub-Indikator Disiplin (Total 100%)</h6>
                    </div>
                    <div class="card-body p-4 bg-white">
                        <p class="text-muted small mb-4">Tentukan kontribusi setiap parameter disiplin terhadap nilai Disiplin akhir.</p>
                        <?php foreach($disiplin as $s): ?>
                        <div class="mb-4">
                            <label class="form-label small fw-800 text-muted ls-1 text-uppercase d-flex justify-content-between">
                                <?= $s['label'] ?>
                                <span class="text-primary" id="val_<?= $s['kunci'] ?>"><?= $s['bobot'] ?>%</span>
                            </label>
                            <div class="d-flex align-items-center gap-3">
                                <input type="range" name="bobot[<?= $s['kunci'] ?>]" class="form-range flex-grow-1" 
                                       min="0" max="100" step="5" value="<?= $s['bobot'] ?>"
                                       oninput="document.getElementById('val_<?= $s['kunci'] ?>').innerText = this.value + '%'">
                                <input type="number" class="form-control text-center fw-800 border-0 bg-light" style="width: 80px;" value="<?= $s['bobot'] ?>" readonly disabled>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <div class="alert alert-warning border-0 rounded-4 bg-warning-soft text-dark mb-0">
                            <i class="fas fa-robot me-2"></i> Seluruh parameter di atas dikalkulasi secara otomatis oleh sistem berdasarkan data presensi dan kas.
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 mt-4 mb-5">
                <button type="submit" name="update_weights" class="btn btn-primary btn-lg rounded-pill px-5 fw-800 shadow-lg w-100 py-3">
                    <i class="fas fa-save me-2"></i> Simpan & Terapkan Konfigurasi Bobot
                </button>
                <p class="text-center mt-3 text-muted small">Perubahan bobot akan segera berdampak pada seluruh kalkulasi nilai pengurus.</p>
            </div>
        </div>
    </form>
</div>

<style>
.bg-slate-50 { background-color: #f8fafc; }
.bg-warning-soft { background-color: #fffbeb; }
.form-range::-webkit-slider-thumb { background: var(--primary); }
.form-range::-moz-range-thumb { background: var(--primary); }
.form-range::-ms-thumb { background: var(--primary); }
.ls-1 { letter-spacing: 0.5px; }
.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.rounded-4 { border-radius: 1.5rem !important; }
</style>

<?php include '../layout/footer.php'; ?>
