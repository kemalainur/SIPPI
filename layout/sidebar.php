<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['user']['nama_role'];
?>
<nav id="sidebar" class="shadow">
    <div class="sidebar-header border-bottom border-light-soft mb-3">
        <div class="d-inline-block position-relative mb-3">
            <img src="<?= base_url('assets/img/logo.png') ?>" alt="Logo" class="img-fluid rounded-circle shadow-lg border border-3 border-brand-gold p-1" style="width: 75px; background: #fff;">
            <span class="position-absolute bottom-0 end-0 p-2 bg-success border border-3 border-dark rounded-circle shadow-sm" style="margin-bottom: 2px; margin-right: 2px;"></span>
        </div>
        <h5 class="mb-0 fw-800 text-white ls-1">FLMPI SIPPI</h5>
        <p class="text-muted mb-0 opacity-50" style="font-size: 0.65rem; letter-spacing: 2px; font-weight: 700;">FORUM MAHASISWA</p>
    </div>

    <ul class="list-unstyled components">
        <li class="<?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
            <a href="<?= base_url('dashboard/dashboard.php') ?>">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
        </li>

        <?php if (in_array($role, ['Super Admin', 'Sekjend', 'PPI'])): ?>
        <li class="<?= (strpos($_SERVER['PHP_SELF'], '/pengurus/') !== false) ? 'active' : '' ?>">
            <a href="<?= base_url('pengurus/data_pengurus.php') ?>">
                <i class="fas fa-users"></i> Data Pengurus
            </a>
        </li>
        <?php endif; ?>

        <?php if (in_array($role, ['Super Admin'])): ?>
        <li class="<?= (strpos($_SERVER['PHP_SELF'], '/biro/') !== false || strpos($_SERVER['PHP_SELF'], '/divisi/') !== false) ? 'active' : '' ?>">
            <a href="#masterSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-sitemap"></i> Struktur Org
            </a>
            <ul class="collapse list-unstyled <?= (strpos($_SERVER['PHP_SELF'], '/biro/') !== false || strpos($_SERVER['PHP_SELF'], '/divisi/') !== false) ? 'show' : '' ?>" id="masterSubmenu">
                <li><a href="<?= base_url('biro/data_biro.php') ?>">Data Biro</a></li>
                <li><a href="<?= base_url('divisi/data_divisi.php') ?>">Data Divisi</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if (in_array($role, ['Super Admin', 'Bendum', 'Sekjend', 'Koorkam', 'PPI', 'Kabiro', 'Kadiv', 'Staff'])): ?>
        <li class="<?= (strpos($_SERVER['PHP_SELF'], '/kas/') !== false) ? 'active' : '' ?>">
            <a href="#kasSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-wallet"></i> Keuangan
            </a>
            <ul class="collapse list-unstyled <?= (strpos($_SERVER['PHP_SELF'], '/kas/') !== false) ? 'show' : '' ?>" id="kasSubmenu">
                <li><a href="<?= base_url('kas/kas_saya.php') ?>" class="fw-800 text-brand-red">Kas Saya</a></li>
                <li><a href="<?= base_url('kas/laporan_kas.php') ?>">Laporan Kas</a></li>
                <?php if (in_array($role, ['Super Admin', 'Bendum'])): ?>
                <li><a href="<?= base_url('kas/status_kas_pengurus.php') ?>">Status Bayar Kas</a></li>
                <li><a href="<?= base_url('kas/pengatur_kas.php') ?>">Konfigurasi Kas</a></li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>

        <?php if (in_array($role, ['Super Admin', 'PPI'])): ?>
        <li class="<?= (strpos($_SERVER['PHP_SELF'], '/ppi/') !== false) ? 'active' : '' ?>">
            <a href="#ppiSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-shield-halved"></i> Modul PPI
            </a>
            <ul class="collapse list-unstyled <?= (strpos($_SERVER['PHP_SELF'], '/ppi/') !== false) ? 'show' : '' ?>" id="ppiSubmenu">
                <li><a href="<?= base_url('ppi/kelola_kepengurusan.php') ?>">Tahun Kepengurusan</a></li>
                <li><a href="<?= base_url('ppi/bulan_penilaian.php') ?>">Bulan Penilaian</a></li>
                <li><a href="<?= base_url('ppi/indikator_kpi.php') ?>">Indikator KPI</a></li>
                <li><a href="<?= base_url('ppi/monitoring_penilaian.php') ?>">Monitor Penilaian</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <li class="<?= (strpos($_SERVER['PHP_SELF'], '/raport/') !== false) ? 'active' : '' ?>">
            <a href="<?= base_url('raport/raport_saya.php') ?>">
                <i class="fas fa-file-contract"></i> Raport Saya
            </a>
        </li>

        <?php if (in_array($role, ['Super Admin', 'Sekjend', 'Bendum', 'PPI', 'Koorkam', 'Kabiro', 'Kadiv', 'Staff'])): ?>
        <li class="<?= (strpos($_SERVER['PHP_SELF'], '/penilaian_input.php') !== false) ? 'active' : '' ?>">
            <a href="<?= base_url('ppi/penilaian_input.php') ?>">
                <i class="fas fa-star-half-alt"></i> Beri Penilaian
            </a>
        </li>
        <?php endif; ?>

        <li class="<?= (strpos($_SERVER['PHP_SELF'], '/inventaris/') !== false) ? 'active' : '' ?>">
            <a href="<?= base_url('inventaris/data_inventaris.php') ?>">
                <i class="fas fa-box"></i> Inventaris
            </a>
        </li>

        <li>
            <a href="<?= base_url('auth/logout.php') ?>" class="text-danger mt-4">
                <i class="fas fa-sign-out-alt"></i> Keluar
            </a>
        </li>
    </ul>
</nav>


