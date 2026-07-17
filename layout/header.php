<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Dashboard' ?> - SIPPI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= base_url('assets/css/style.css') ?>" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= base_url('assets/img/logo.png') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('assets/img/logo.png') ?>">
    <meta name="description" content="SIPPI - Sistem Informasi Performa Pengurus Intern FLMPI">
    <meta name="author" content="FLMPI">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div id="sidebar-overlay"></div>

    <div class="d-flex" id="wrapper">

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        const btnToggle = document.getElementById('sidebarCollapse');
        const btnClose = document.getElementById('sidebarClose');

        function toggleSidebar() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : 'auto';
        }

        const contentDiv = document.getElementById('content');
        if (contentDiv) {
            const mobileHeader = `
                <div class="mobile-header d-lg-none">
                    <div class="d-flex align-items-center">
                        <img src="<?= base_url('assets/img/logo.png') ?>" alt="Logo" class="rounded-circle me-3 shadow-sm" style="width: 32px; background: #fff; padding: 2px; border: 1px solid #eee;">
                        <h6 class="mb-0 fw-800 text-brand-red">SIPPI</h6>
                    </div>
                    <button id="sidebarCollapse" class="btn btn-light-soft shadow-sm border py-1 px-2">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                </div>
            `;
            contentDiv.insertAdjacentHTML('afterbegin', mobileHeader);
            
            const btnToggle = document.getElementById('sidebarCollapse');
            if(btnToggle) btnToggle.addEventListener('click', toggleSidebar);
        }

        if(btnClose) btnClose.addEventListener('click', toggleSidebar);
        if(overlay) overlay.addEventListener('click', toggleSidebar);
    });
    </script>


