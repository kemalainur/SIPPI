<?php
require_once '../config/database.php';
session_start();
check_login();

$nokta = $_GET['nokta'] ?? $_SESSION['user']['nokta'];
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

// PPI / Admin can view anyone, others only themselves
if ($_SESSION['user']['nokta'] != $nokta && !in_array($_SESSION['user']['nama_role'], ['Super Admin', 'PPI', 'Koorkam'])) {
    die("Akses Ditolak.");
}

// 1. Fetch KPI Data (to get the context period)
$stmtKPI = $pdo->prepare("SELECT * FROM tabel_nilai_kpi WHERE nokta = ? AND bulan = ? AND tahun = ?");
$stmtKPI->execute([$nokta, $bulan, $tahun]);
$kpi = $stmtKPI->fetch();

if (!$kpi) {
    die("Data rapor untuk periode ini belum tersedia.");
}

$period_id = $kpi['kepengurusan_id'];

// 2. Fetch Pengurus Data + Specific Position for that period
$stmtP = $pdo->prepare("SELECT p.*, r.nama_role, b.nama_biro, d.nama_divisi, j.jabatan as jabatan_period
                        FROM tabel_pengurus p 
                        JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta AND j.kepengurusan_id = ?
                        JOIN tabel_role r ON j.role_id = r.id_role 
                        LEFT JOIN tabel_biro b ON j.biro_id = b.id_biro 
                        LEFT JOIN tabel_divisi d ON j.divisi_id = d.id_divisi 
                        WHERE p.nokta = ?");
$stmtP->execute([$period_id, $nokta]);
$p = $stmtP->fetch();

$indonesian_months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Raport_<?= $nokta ?>_<?= $bulan ?>_<?= $tahun ?></title>
    <style>
        body { font-family: 'Times New Roman', Times, serif; color: #333; line-height: 1.6; }
        .container { width: 800px; margin: 0 auto; border: 1px solid #ddd; padding: 40px; }
        .header { text-align: center; border-bottom: 3px double #333; padding-bottom: 20px; margin-bottom: 30px; position: relative; }
        .header img { position: absolute; left: 0; top: 0; width: 80px; height: 80px; }
        .header h1 { margin: 0; font-size: 24px; text-transform: uppercase; color: #dc3545; }
        .header h2 { margin: 5px 0; font-size: 18px; color: #198754; }
        .header p { margin: 0; font-size: 12px; font-style: italic; }
        
        .title { text-align: center; font-weight: bold; font-size: 20px; margin-bottom: 30px; text-decoration: underline; }
        
        .info-table { width: 100%; margin-bottom: 30px; border-collapse: collapse; }
        .info-table td { padding: 5px 0; font-size: 14px; }
        .info-table td:first-child { width: 150px; }
        
        .score-table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        .score-table th, .score-table td { border: 1px solid #000; padding: 10px; text-align: center; font-size: 14px; }
        .score-table th { background-color: #f2f2f2; }
        .score-table .text-left { text-align: left; }
        
        .grade-box { float: right; border: 2px solid #000; padding: 10px 20px; text-align: center; margin-bottom: 40px; }
        .grade-box h4 { margin: 0; font-size: 14px; }
        .grade-box h2 { margin: 5px 0; font-size: 30px; color: #dc3545; }
        
        .footer-sig { width: 100%; margin-top: 100px; }
        .footer-sig td { text-align: center; width: 33%; font-size: 14px; vertical-align: top; }
        .sig-space { height: 80px; }
        
        @media print {
            .no-print { display: none; }
            .container { border: none; width: 100%; padding: 0; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="background: #f8f9fa; padding: 10px; text-align: center; border-bottom: 1px solid #ddd;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; background: #dc3545; color: white; border: none; border-radius: 5px; font-weight: bold;">
            KLIK UNTUK CETAK RAPORT
        </button>
        <p style="font-size: 12px; margin-top: 5px;">Gunakan browser Chrome/Edge dan simpan sebagai PDF.</p>
    </div>

    <div class="container">
        <div class="header">
            <img src="../assets/img/logo.png" alt="Logo">
            <h1>Sistem Informasi SIPPI</h1>
            <h2>Laporan Kinerja Bulanan Pengurus</h2>
            <p>Sekretariat Jenderal - Tim Pengawasan dan Penilaian Internal (PPI)</p>
        </div>

        <div class="title">RAPOR KINERJA PENGURUS</div>

        <table class="info-table">
            <tr>
                <td>Nama Pengurus</td>
                <td>: <strong><?= $p['nama'] ?></strong></td>
            </tr>
            <tr>
                <td>Nomor KTA</td>
                <td>: <?= $p['nokta'] ?></td>
            </tr>
            <tr>
                <td>Jabatan / Biro</td>
                <td>: <?= $p['jabatan'] ?> (<?= $p['nama_biro'] ?? '-' ?>)</td>
            </tr>
            <tr>
                <td>Periode Penilaian</td>
                <td>: <?= $indonesian_months[(int)$bulan] ?> <?= $tahun ?></td>
            </tr>
        </table>

        <table class="score-table">
            <thead>
                <tr>
                    <th width="50">No</th>
                    <th class="text-left">Aspek Penilaian (KPI)</th>
                    <th>Skor (1-4)</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td class="text-left"><strong>Attitude (Sikap)</strong><br><small>Rata-rata penilaian sejawat</small></td>
                    <td><strong><?= number_format($kpi['nilai_attitude'], 2) ?></strong></td>
                    <td><?= $kpi['nilai_attitude'] >= 3 ? 'BAIK' : 'CUKUP' ?></td>
                </tr>
                <tr>
                    <td>2</td>
                    <td class="text-left"><strong>Komunikasi</strong><br><small>Responsivitas dan koordinasi</small></td>
                    <td><strong><?= number_format($kpi['nilai_komunikasi'], 2) ?></strong></td>
                    <td><?= $kpi['nilai_komunikasi'] >= 3 ? 'BAIK' : 'CUKUP' ?></td>
                </tr>
                <tr>
                    <td>3</td>
                    <td class="text-left"><strong>Kedisiplinan</strong><br><small>Kehadiran (75%) & Pembayaran Kas</small></td>
                    <td><strong><?= number_format($kpi['nilai_disiplin'], 2) ?></strong></td>
                    <td><?= $kpi['nilai_disiplin'] >= 3 ? 'DISIPLIN' : 'KURANG' ?></td>
                </tr>
                <tr style="background-color: #f9f9f9; font-weight: bold;">
                    <td></td>
                    <td class="text-left">TOTAL KPI INDIVIDU</td>
                    <td><?= number_format($kpi['nilai_kpi_total'], 2) ?></td>
                    <td>
                        <?php 
                        if($kpi['nilai_kpi_total'] >= 3.5) echo "ISTIMEWA";
                        elseif($kpi['nilai_kpi_total'] >= 3) echo "BAIK";
                        elseif($kpi['nilai_kpi_total'] >= 2) echo "CUKUP";
                        else echo "KURANG";
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="grade-box">
            <h4>PREDIKAT</h4>
            <h2>
                <?php 
                $total = $kpi['nilai_kpi_total'];
                if($total >= 3.5) echo "A";
                elseif($total >= 3) echo "B";
                elseif($total >= 2) echo "C";
                else echo "D";
                ?>
            </h2>
        </div>

        <div style="clear: both;"></div>

        <table class="footer-sig">
            <tr>
                <td>
                    Kepala PPI,
                    <div class="sig-space"></div>
                    ( ................................ )
                </td>
                <td></td>
                <td>
                    Sekretaris Jenderal,
                    <div class="sig-space"></div>
                    ( ................................ )
                </td>
            </tr>
        </table>
        
        <div style="text-align: center; margin-top: 50px; font-size: 10px; color: #888;">
            Dokumen ini dihasilkan secara otomatis oleh Sistem Informasi SIPPI pada <?= date('d/m/Y H:i') ?>
        </div>
    </div>
</body>
</html>


