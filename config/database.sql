-- Database: sippi
CREATE DATABASE IF NOT EXISTS sippi;
USE sippi;

-- 1. tabel_role
CREATE TABLE IF NOT EXISTS tabel_role (
    id_role INT PRIMARY KEY AUTO_INCREMENT,
    nama_role VARCHAR(50) NOT NULL
);

INSERT INTO tabel_role (nama_role) VALUES 
('Super Admin'), ('Sekjend'), ('Bendum'), ('PPI'), 
('Kabiro'), ('Kadiv'), ('Staff'), ('Koorkam');

-- 2. tabel_kepengurusan (Grand Period)
CREATE TABLE IF NOT EXISTS tabel_kepengurusan (
    id_kepengurusan INT PRIMARY KEY AUTO_INCREMENT,
    nama_periode VARCHAR(50) NOT NULL UNIQUE,
    status ENUM('aktif', 'arsip') DEFAULT 'aktif'
);

INSERT INTO tabel_kepengurusan (nama_periode, status) VALUES ('2024/2025', 'aktif');

-- 3. tabel_biro
CREATE TABLE IF NOT EXISTS tabel_biro (
    id_biro INT PRIMARY KEY AUTO_INCREMENT,
    kepengurusan_id INT,
    nama_biro VARCHAR(100) NOT NULL,
    FOREIGN KEY (kepengurusan_id) REFERENCES tabel_kepengurusan(id_kepengurusan) ON DELETE CASCADE
);

-- 4. tabel_divisi
CREATE TABLE IF NOT EXISTS tabel_divisi (
    id_divisi INT PRIMARY KEY AUTO_INCREMENT,
    kepengurusan_id INT,
    nama_divisi VARCHAR(100) NOT NULL,
    biro_id INT,
    FOREIGN KEY (biro_id) REFERENCES tabel_biro(id_biro) ON DELETE CASCADE,
    FOREIGN KEY (kepengurusan_id) REFERENCES tabel_kepengurusan(id_kepengurusan) ON DELETE CASCADE
);

-- 5. tabel_pengurus (Static Data)
CREATE TABLE IF NOT EXISTS tabel_pengurus (
    nokta VARCHAR(20) PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    angkatan CHAR(4),
    no_hp VARCHAR(20),
    password VARCHAR(255) NOT NULL
);

-- 6. tabel_pengurus_jabatan (Period-based Data)
CREATE TABLE IF NOT EXISTS tabel_pengurus_jabatan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nokta VARCHAR(20),
    kepengurusan_id INT,
    biro_id INT NULL,
    divisi_id INT NULL,
    role_id INT NULL,
    jabatan VARCHAR(100),
    FOREIGN KEY (nokta) REFERENCES tabel_pengurus(nokta) ON DELETE CASCADE,
    FOREIGN KEY (kepengurusan_id) REFERENCES tabel_kepengurusan(id_kepengurusan) ON DELETE CASCADE,
    FOREIGN KEY (biro_id) REFERENCES tabel_biro(id_biro) ON DELETE SET NULL,
    FOREIGN KEY (divisi_id) REFERENCES tabel_divisi(id_divisi) ON DELETE SET NULL,
    FOREIGN KEY (role_id) REFERENCES tabel_role(id_role) ON DELETE SET NULL,
    UNIQUE KEY (nokta, kepengurusan_id)
);

-- 7. tabel_periode (Bulan Penilaian)
CREATE TABLE IF NOT EXISTS tabel_periode (
    id_periode INT PRIMARY KEY AUTO_INCREMENT,
    kepengurusan_id INT,
    bulan INT NOT NULL,
    tahun INT NOT NULL,
    status ENUM('aktif', 'tutup') DEFAULT 'aktif',
    UNIQUE (bulan, tahun),
    FOREIGN KEY (kepengurusan_id) REFERENCES tabel_kepengurusan(id_kepengurusan) ON DELETE CASCADE
);

-- 6. tabel_indikator
CREATE TABLE IF NOT EXISTS tabel_indikator (
    id_indikator INT PRIMARY KEY AUTO_INCREMENT,
    nama_indikator VARCHAR(255) NOT NULL,
    kategori ENUM('attitude', 'komunikasi') NOT NULL
);

-- 7. tabel_kegiatan
CREATE TABLE IF NOT EXISTS tabel_kegiatan (
    id_kegiatan INT PRIMARY KEY AUTO_INCREMENT,
    nama_kegiatan VARCHAR(255) NOT NULL,
    bulan INT NOT NULL,
    tahun INT NOT NULL
);

-- 8. tabel_kehadiran
CREATE TABLE IF NOT EXISTS tabel_kehadiran (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kegiatan_id INT,
    nokta_pengurus VARCHAR(20),
    status_hadir ENUM('hadir', 'izin', 'alpa', 'telat') DEFAULT 'alpa',
    FOREIGN KEY (kegiatan_id) REFERENCES tabel_kegiatan(id_kegiatan) ON DELETE CASCADE,
    FOREIGN KEY (nokta_pengurus) REFERENCES tabel_pengurus(nokta) ON DELETE CASCADE
);

-- Insert default indicators
INSERT INTO tabel_indikator (nama_indikator, kategori) VALUES 
('Integritas & Kejujuran', 'attitude'),
('Kerapihan & Kesopanan', 'attitude'),
('Loyalitas Organisasi', 'attitude'),
('Kualitas Komunikasi', 'komunikasi'),
('Koordinasi Teamwork', 'komunikasi'),
('Responsivitas', 'komunikasi');

-- 9. tabel_kas_pengurus
CREATE TABLE IF NOT EXISTS tabel_kas_pengurus (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nokta_pengurus VARCHAR(20),
    bulan INT NOT NULL,
    tahun INT NOT NULL,
    status_bayar ENUM('belum', 'sudah') DEFAULT 'belum',
    nominal DECIMAL(15,2) DEFAULT 0,
    tanggal_bayar DATE,
    FOREIGN KEY (nokta_pengurus) REFERENCES tabel_pengurus(nokta) ON DELETE CASCADE
);

-- 10. tabel_penilaian
CREATE TABLE IF NOT EXISTS tabel_penilaian (
    id_penilaian INT PRIMARY KEY AUTO_INCREMENT,
    penilai_nokta VARCHAR(20),
    dinilai_nokta VARCHAR(20),
    indikator_id INT,
    skor INT NOT NULL CHECK (skor BETWEEN 1 AND 4),
    bulan INT NOT NULL,
    tahun INT NOT NULL,
    FOREIGN KEY (penilai_nokta) REFERENCES tabel_pengurus(nokta) ON DELETE CASCADE,
    FOREIGN KEY (dinilai_nokta) REFERENCES tabel_pengurus(nokta) ON DELETE CASCADE,
    FOREIGN KEY (indikator_id) REFERENCES tabel_indikator(id_indikator) ON DELETE CASCADE
);

-- 11. tabel_nilai_kpi
CREATE TABLE IF NOT EXISTS tabel_nilai_kpi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nokta VARCHAR(20),
    bulan INT NOT NULL,
    tahun INT NOT NULL,
    kepengurusan_id INT,
    nilai_attitude DECIMAL(4,2),
    nilai_komunikasi DECIMAL(4,2),
    nilai_disiplin DECIMAL(4,2),
    nilai_kpi_total DECIMAL(4,2),
    FOREIGN KEY (nokta) REFERENCES tabel_pengurus(nokta) ON DELETE CASCADE,
    FOREIGN KEY (kepengurusan_id) REFERENCES tabel_kepengurusan(id_kepengurusan) ON DELETE CASCADE,
    UNIQUE KEY unique_kpi (nokta, bulan, tahun, kepengurusan_id)
);

-- 12. tabel_inventaris
CREATE TABLE IF NOT EXISTS tabel_inventaris (
    id_inventaris INT PRIMARY KEY AUTO_INCREMENT,
    nama_barang VARCHAR(100) NOT NULL,
    jumlah INT NOT NULL,
    kondisi VARCHAR(50),
    keterangan TEXT
);

-- 13. tabel_kas_umum (for Bendum)
CREATE TABLE IF NOT EXISTS tabel_kas_umum (
    id_transaksi INT PRIMARY KEY AUTO_INCREMENT,
    tanggal DATE NOT NULL,
    keterangan TEXT NOT NULL,
    jenis ENUM('masuk', 'keluar') NOT NULL,
    jumlah DECIMAL(15,2) NOT NULL
);

-- Default Super Admin (No. KTA: 07-23106, Nama: Moh Kemal Ainur Ardiansyah, password: admin123)
-- Plain text password as requested
INSERT INTO tabel_pengurus (nokta, nama, role_id, password) VALUES 
('07-23106', 'Moh Kemal Ainur Ardiansyah', 1, 'admin123');
