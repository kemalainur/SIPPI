<?php
require_once '../config/database.php';
session_start();

if (isset($_SESSION['user'])) {
    header("Location: ../dashboard/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nokta = $_POST['nokta'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT p.*, r.nama_role, j.biro_id, j.divisi_id, j.jabatan 
                           FROM tabel_pengurus p 
                           LEFT JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta 
                           LEFT JOIN tabel_role r ON j.role_id = r.id_role 
                           LEFT JOIN tabel_kepengurusan k ON j.kepengurusan_id = k.id_kepengurusan 
                           WHERE p.nokta = ? 
                           AND (k.status = 'aktif' OR r.nama_role = 'Super Admin' OR j.id IS NULL)
                           ORDER BY (CASE WHEN k.status = 'aktif' THEN 1 WHEN r.nama_role = 'Super Admin' THEN 2 ELSE 3 END) ASC
                           LIMIT 1");
    $stmt->execute([$nokta]);
    $user = $stmt->fetch();

    if ($user && $password === $user['password']) {
        $_SESSION['user'] = [
            'nokta' => $user['nokta'],
            'nama' => $user['nama'],
            'role_id' => $user['role_id'],
            'nama_role' => $user['nama_role']
        ];
        header("Location: ../dashboard/dashboard.php");
        exit;
    } else {
        $error = 'No. KTA atau Password salah!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SIPPI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #F1F5F9;
            /* Professional Light Background */
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
            position: relative;
        }

        /* Subtle Geometric Background Pattern */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 35vh;
            background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%);
            z-index: 0;
        }

        .login-container {
            width: 100%;
            max-width: 440px;
            padding: 0;
            border-radius: 1.25rem;
            background: #FFFFFF;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
            z-index: 10;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .accent-bar {
            height: 6px;
            background: linear-gradient(90deg, #DC2626 0%, #16A34A 100%);
            width: 100%;
        }

        .login-content {
            padding: 3rem 2.5rem;
        }

        .logo-box {
            background: #F8FAFC;
            width: 90px;
            height: 90px;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.75rem;
            border: 1px solid #E2E8F0;
        }

        .logo-box img {
            width: 65px;
            height: 65px;
            object-fit: contain;
        }

        h2 {
            color: #0F172A;
            font-weight: 800;
            letter-spacing: -1px;
            text-align: center;
            margin-bottom: 0.5rem;
        }

        p.subtitle {
            color: #64748B;
            text-align: center;
            font-size: 0.9rem;
            margin-bottom: 2.25rem;
            line-height: 1.4;
        }

        .form-label {
            color: #475569;
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-group-text {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            color: #94A3B8;
            border-right: none;
            border-radius: 0.85rem 0 0 0.85rem;
        }

        .form-control {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            color: #0F172A;
            border-radius: 0 0.85rem 0.85rem 0;
            padding: 0.85rem 1rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            background: #FFFFFF;
            border-color: #DC2626;
            box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.05);
            color: #0F172A;
        }

        .form-control::placeholder {
            color: #CBD5E1;
        }

        .btn-login {
            background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%);
            border: none;
            color: white;
            padding: 1rem;
            border-radius: 0.85rem;
            font-weight: 800;
            width: 100%;
            margin-top: 1.25rem;
            transition: all 0.3s ease;
            box-shadow: 0 10px 15px -3px rgba(220, 38, 38, 0.25);
            letter-spacing: 0.5px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px -5px rgba(220, 38, 38, 0.3);
            filter: brightness(1.1);
        }

        .alert-error {
            background: #FEF2F2;
            border: 1px solid #FEE2E2;
            color: #991B1B;
            padding: 0.85rem;
            border-radius: 0.85rem;
            font-size: 0.85rem;
            margin-bottom: 2rem;
            text-align: center;
            font-weight: 600;
        }

        .credits {
            position: absolute;
            bottom: 2rem;
            color: #94A3B8;
            font-size: 0.75rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-weight: 600;
        }
    </style>
</head>

<body>

    <div class="login-container">
        <div class="accent-bar"></div>
        <div class="login-content">
            <div class="logo-box">
                <img src="../assets/img/logo.png" alt="Logo SIPPI">
            </div>
            <h2>SIPPI</h2>
            <p class="subtitle">Sistem Informasi Pengawas & Pengendali Internal<br>Forum Lembaga Mahasiswa
                Perindustrian<br>Politeknik STMI Jakarta</p>

            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle me-1"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Nomor KTA</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-id-badge"></i></span>
                        <input type="text" name="nokta" class="form-control" placeholder="XX-XXXXX" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Sandi Akses</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-shield-alt"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                </div>
                <button type="submit" class="btn-login">LOGIN</button>
            </form>
        </div>
    </div>

    <div class="credits">
        &copy; <?= date('Y') ?> SIPPI - Develop by tim PPI
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
</body>

</html>