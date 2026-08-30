<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - Sistem Sedang Dalam Pembaruan</title>
    <meta name="description" content="Sistem sedang dalam pembaruan. Mohon maaf atas ketidaknyamanannya.">
    <!-- Google Fonts: Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>

        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html { font-size: 16px; scroll-behavior: smooth; }
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            color: #ffffff;
        }

        /* ============================================
           BACKGROUND - Hitam + Mesh Merah Hijau Emas
           ============================================ */
        body {
            background: #080808;
        }

        /* Mesh gradient merah, hijau, emas */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(ellipse 80% 60% at 10% 20%, rgba(180, 30, 30, 0.35) 0%, transparent 60%),
                radial-gradient(ellipse 70% 50% at 85% 75%, rgba(20, 130, 50, 0.30) 0%, transparent 55%),
                radial-gradient(ellipse 60% 40% at 50% 10%, rgba(212, 175, 55, 0.22) 0%, transparent 50%),
                radial-gradient(ellipse 50% 60% at 90% 15%, rgba(150, 20, 20, 0.22) 0%, transparent 50%),
                radial-gradient(ellipse 40% 40% at 20% 80%, rgba(30, 110, 60, 0.25) 0%, transparent 50%),
                radial-gradient(ellipse 90% 70% at 50% 50%, rgba(212, 175, 55, 0.06) 0%, transparent 70%);
            animation: meshMove 20s ease-in-out infinite alternate;
        }

        @keyframes meshMove {
            0% { opacity: 1; filter: hue-rotate(0deg); }
            50% { opacity: 0.85; filter: hue-rotate(5deg); }
            100% { opacity: 1; filter: hue-rotate(-5deg); }
        }

        /* Garis diagonal emas halus */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 80px,
                rgba(212, 175, 55, 0.02) 80px,
                rgba(212, 175, 55, 0.02) 81px
            );
        }

        /* ============================================
           PARTIKEL MULTI WARNA
           ============================================ */
        .particles {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }
        .particle {
            position: absolute;
            border-radius: 50%;
            animation: floatUp linear infinite;
        }
        .particle:nth-child(1)  { width: 4px; height: 4px; left: 8%;  background: rgba(220,50,50,0.45);  animation-duration: 18s; animation-delay: 0s; }
        .particle:nth-child(2)  { width: 6px; height: 6px; left: 22%; background: rgba(220,50,50,0.35);  animation-duration: 22s; animation-delay: 2s; }
        .particle:nth-child(3)  { width: 3px; height: 3px; left: 35%; background: rgba(50,180,80,0.45);  animation-duration: 16s; animation-delay: 4s; }
        .particle:nth-child(4)  { width: 5px; height: 5px; left: 48%; background: rgba(50,180,80,0.35);  animation-duration: 20s; animation-delay: 1s; }
        .particle:nth-child(5)  { width: 5px; height: 5px; left: 62%; background: rgba(212,175,55,0.50); animation-duration: 24s; animation-delay: 3s; }
        .particle:nth-child(6)  { width: 4px; height: 4px; left: 75%; background: rgba(212,175,55,0.40); animation-duration: 17s; animation-delay: 5s; }
        .particle:nth-child(7)  { width: 3px; height: 3px; left: 88%; background: rgba(255,255,255,0.25);animation-duration: 21s; animation-delay: 0s; }
        .particle:nth-child(8)  { width: 4px; height: 4px; left: 92%; background: rgba(255,255,255,0.20);animation-duration: 19s; animation-delay: 2s; }
        .particle:nth-child(9)  { width: 5px; height: 5px; left: 5%;  background: rgba(220,50,50,0.30);  animation-duration: 23s; animation-delay: 6s; }
        .particle:nth-child(10) { width: 3px; height: 3px; left: 42%; background: rgba(50,180,80,0.35);  animation-duration: 15s; animation-delay: 1s; }
        .particle:nth-child(11) { width: 6px; height: 6px; left: 56%; background: rgba(212,175,55,0.35); animation-duration: 25s; animation-delay: 3s; }
        .particle:nth-child(12) { width: 4px; height: 4px; left: 30%; background: rgba(255,255,255,0.20);animation-duration: 18s; animation-delay: 7s; }
        .particle:nth-child(13) { width: 3px; height: 3px; left: 15%; background: rgba(212,175,55,0.40); animation-duration: 20s; animation-delay: 4s; }
        .particle:nth-child(14) { width: 5px; height: 5px; left: 70%; background: rgba(220,50,50,0.25);  animation-duration: 22s; animation-delay: 8s; }
        .particle:nth-child(15) { width: 4px; height: 4px; left: 82%; background: rgba(50,180,80,0.30);  animation-duration: 19s; animation-delay: 2s; }

        @keyframes floatUp {
            0%   { transform: translateY(110vh) scale(0); opacity: 0; }
            10%  { opacity: 1; transform: translateY(90vh) scale(1); }
            90%  { opacity: 1; }
            100% { transform: translateY(-10vh) scale(0.5); opacity: 0; }
        }

        .geo { position: fixed; pointer-events: none; z-index: 0; opacity: 0.07; }
        .geo--diamond {
            width: 180px; height: 180px;
            border: 2px solid #d4af37;
            transform: rotate(45deg);
            top: 8%; right: 8%;
            animation: geoFloat 12s ease-in-out infinite;
        }
        .geo--circle {
            width: 260px; height: 260px;
            border: 2px solid #dc3232;
            border-radius: 50%;
            bottom: 6%; left: 5%;
            animation: geoFloat2 15s ease-in-out infinite;
        }
        .geo--cross {
            width: 120px; height: 120px;
            top: 72%; right: 12%;
            animation: geoFloat 10s ease-in-out infinite;
        }
        .geo--cross::before, .geo--cross::after {
            content: ''; position: absolute; background: #32b450;
        }
        .geo--cross::before { width: 2px; height: 100%; left: 50%; transform: translateX(-50%); }
        .geo--cross::after  { width: 100%; height: 2px; top: 50%; transform: translateY(-50%); }
        .geo--triangle {
            width: 0; height: 0;
            border-left: 70px solid transparent;
            border-right: 70px solid transparent;
            border-bottom: 120px solid rgba(212,175,55,0.1);
            top: 15%; left: 5%;
            animation: geoFloat2 18s ease-in-out infinite;
        }

        @keyframes geoFloat  { 0%,100% { transform: translateY(0) rotate(45deg); } 50% { transform: translateY(-20px) rotate(45deg); } }
        @keyframes geoFloat2 { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }

        /* ============================================
           FADE-IN
           ============================================ */
        .fade-in {
            animation: fadeInUp 1.2s cubic-bezier(0.23,1,0.32,1) forwards;
            opacity: 0;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* ============================================
           GLASSMORPHISM CARD - Aksen Emas
           ============================================ */
        .card {
            position: relative;
            z-index: 1;
            width: 90%;
            max-width: 680px;
            padding: 50px 40px;
            text-align: center;
            border-radius: 24px;
            background: rgba(20,20,20,0.6);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(212,175,55,0.18);
            box-shadow:
                0 8px 40px rgba(0,0,0,0.5),
                0 0 80px rgba(212,175,55,0.04),
                inset 0 1px 0 rgba(255,255,255,0.08);
        }

        /* Garis emas di atas card */
        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 30px; right: 30px;
            height: 3px;
            background: linear-gradient(90deg, transparent, #d4af37, #f0d060, #d4af37, transparent);
            border-radius: 0 0 4px 4px;
        }

        /* Glow merah-hijau di bawah card */
        .card::after {
            content: '';
            position: absolute;
            bottom: -1px; left: 40px; right: 40px;
            height: 2px;
            background: linear-gradient(90deg, transparent, #dc3232, transparent 40%, transparent 60%, #32b450, transparent);
            border-radius: 4px;
            opacity: 0.5;
        }

        /* ============================================
           PITA WARNA DI SISI CARD
           ============================================ */
        .ribbon {
            position: absolute;
            top: 50px; bottom: 50px; width: 4px;
            left: 0;
            border-radius: 0 4px 4px 0;
            background: linear-gradient(180deg, #dc3232, #d4af37, #ffffff, #32b450, #dc3232);
            background-size: 100% 200%;
            animation: ribbonFlow 5s linear infinite;
            opacity: 0.5;
        }
        .ribbon--r {
            left: auto; right: 0;
            border-radius: 4px 0 0 4px;
            animation-direction: reverse;
        }
        @keyframes ribbonFlow {
            0%   { background-position: 0% 0%; }
            100% { background-position: 0% 200%; }
        }

        /* ============================================
           LOGO
           ============================================ */
        .logo { margin-bottom: 28px; }
        .logo img {
            width: 90px; height: 90px;
            object-fit: contain;
            filter: drop-shadow(0 4px 16px rgba(212,175,55,0.3));
            transition: transform 0.3s ease;
        }
        .logo img:hover { transform: scale(1.05); }

        /* ============================================
           JUDUL & DESKRIPSI - Gradien Emas
           ============================================ */
        .title {
            font-size: 1.65rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            margin-bottom: 16px;
            line-height: 1.3;
            background: linear-gradient(135deg, #ffffff 20%, #f0d060 60%, #d4af37 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .desc {
            font-size: 0.95rem;
            font-weight: 300;
            line-height: 1.7;
            color: rgba(255,255,255,0.72);
            margin-bottom: 36px;
            max-width: 520px;
            margin-left: auto;
            margin-right: auto;
        }

        /* ============================================
           COUNTDOWN - Angka besar emas
           ============================================ */
        .cd-wrap {
            display: flex;
            justify-content: center;
            gap: 16px;
            margin-bottom: 36px;
            flex-wrap: wrap;
        }
        .cd-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 90px;
            padding: 12px 8px;
            background: rgba(255,255,255,0.03);
            border-radius: 16px;
            border: 1px solid rgba(212,175,55,0.12);
        }
        .cd-val {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1;
            background: linear-gradient(180deg, #ffffff 0%, #f0d060 50%, #d4af37 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1);
        }
        .cd-val.tick { animation: tickPulse 0.4s cubic-bezier(0.34,1.56,0.64,1); }
        @keyframes tickPulse {
            0%   { transform: scale(1) translateY(0); }
            30%  { transform: scale(1.15) translateY(-4px); }
            100% { transform: scale(1) translateY(0); }
        }
        .cd-sep {
            font-size: 3rem;
            font-weight: 700;
            color: rgba(212,175,55,0.4);
            align-self: center;
            user-select: none;
        }
        .cd-lbl {
            font-size: 0.72rem;
            font-weight: 500;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(212,175,55,0.65);
            margin-top: 10px;
        }

        /* ============================================
           SPINNER - Tri-color
           ============================================ */
        .spin-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 8px;
        }
        .spin {
            width: 24px; height: 24px;
            border-radius: 50%;
            border: 2.5px solid rgba(212,175,55,0.15);
            border-top-color: #d4af37;
            border-right-color: #dc3232;
            border-bottom-color: #32b450;
            animation: rotate 1.4s linear infinite;
        }
        @keyframes rotate { to { transform: rotate(360deg); } }
        .spin-txt {
            font-size: 0.8rem;
            font-weight: 400;
            color: rgba(255,255,255,0.45);
            letter-spacing: 0.5px;
        }

        /* ============================================
           PULSE RINGS - Merah Emas Hijau
           ============================================ */
        .ring {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%,-50%);
            border-radius: 50%;
            animation: ringPulse 5s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }
        .ring:nth-child(1) { width: 300px; height: 300px; border: 1px solid rgba(220,50,50,0.12); }
        .ring:nth-child(2) { width: 420px; height: 420px; border: 1px solid rgba(212,175,55,0.10); animation-delay: 1.7s; }
        .ring:nth-child(3) { width: 540px; height: 540px; border: 1px solid rgba(50,180,80,0.10);  animation-delay: 3.4s; }
        @keyframes ringPulse {
            0%,100% { opacity: 0; transform: translate(-50%,-50%) scale(0.8); }
            50%     { opacity: 1; transform: translate(-50%,-50%) scale(1); }
        }

        /* ============================================
           PESAN SELESAI
           ============================================ */
        .done { display: none; }
        .done.show { display: block; animation: fadeInUp 0.8s ease forwards; }
        .done-icon { font-size: 3rem; margin-bottom: 16px; animation: bounceIn 0.6s ease; }
        .done-title {
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            margin-bottom: 14px;
            background: linear-gradient(135deg, #32b450 0%, #f0d060 50%, #d4af37 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .done-desc {
            font-size: 0.95rem;
            font-weight: 300;
            color: rgba(255,255,255,0.75);
            line-height: 1.6;
        }
        @keyframes bounceIn {
            0%   { transform: scale(0); opacity: 0; }
            50%  { transform: scale(1.2); }
            100% { transform: scale(1); opacity: 1; }
        }

        /* ============================================
           FOOTER
           ============================================ */
        .footer {
            position: fixed;
            bottom: 20px; left: 0; right: 0;
            text-align: center;
            font-size: 0.75rem;
            color: rgba(255,255,255,0.2);
            z-index: 1;
        }

        /* ============================================
           RESPONSIF
           ============================================ */
        @media (max-width: 768px) {
            .card { padding: 36px 24px; border-radius: 20px; }
            .title { font-size: 1.3rem; letter-spacing: 1px; }
            .desc { font-size: 0.88rem; }
            .cd-val { font-size: 2.6rem; }
            .cd-sep { font-size: 2.2rem; }
            .cd-item { min-width: 70px; }
            .cd-wrap { gap: 10px; }
            .logo img { width: 72px; height: 72px; }
        }
        @media (max-width: 480px) {
            .card { padding: 28px 18px; width: 95%; border-radius: 16px; }
            .title { font-size: 1.1rem; }
            .desc { font-size: 0.82rem; margin-bottom: 28px; }
            .cd-val { font-size: 2rem; }
            .cd-sep { font-size: 1.6rem; }
            .cd-item { min-width: 55px; padding: 8px 4px; }
            .cd-lbl { font-size: 0.65rem; letter-spacing: 1px; }
            .logo img { width: 60px; height: 60px; }
            .done-title { font-size: 1.2rem; }
            .ribbon { top: 40px; bottom: 40px; width: 3px; }
            .geo { display: none; }
        }
    </style>
</head>
<body>

    <!-- Partikel multi-warna -->
    <div class="particles">
        <div class="particle"></div><div class="particle"></div><div class="particle"></div>
        <div class="particle"></div><div class="particle"></div><div class="particle"></div>
        <div class="particle"></div><div class="particle"></div><div class="particle"></div>
        <div class="particle"></div><div class="particle"></div><div class="particle"></div>
        <div class="particle"></div><div class="particle"></div><div class="particle"></div>
    </div>

    <!-- Bentuk geometris dekoratif -->
    <div class="geo geo--diamond"></div>
    <div class="geo geo--circle"></div>
    <div class="geo geo--cross"></div>
    <div class="geo geo--triangle"></div>

    <!-- Pulse rings -->
    <div class="ring"></div>
    <div class="ring"></div>
    <div class="ring"></div>

    <!-- Card Utama -->
    <main class="card fade-in">
        <div class="ribbon"></div>
        <div class="ribbon ribbon--r"></div>

        <!-- Logo -->
        <div class="logo">
            <img src="assets/img/logo.png" alt="Logo" onerror="this.style.display='none'">
        </div>

        <!-- Countdown aktif -->
        <div id="countdownSection">
            <h1 class="title">SISTEM SEDANG DALAM PEMBARUAN</h1>
            <p class="desc">SIPPI FLMPI STMI akan kembali tersedia dalam:</p>

            <div class="cd-wrap">
                <div class="cd-item">
                    <span class="cd-val" id="days">00</span>
                    <span class="cd-lbl">Hari</span>
                </div>
                <span class="cd-sep">:</span>
                <div class="cd-item">
                    <span class="cd-val" id="hours">00</span>
                    <span class="cd-lbl">Jam</span>
                </div>
                <span class="cd-sep">:</span>
                <div class="cd-item">
                    <span class="cd-val" id="minutes">00</span>
                    <span class="cd-lbl">Menit</span>
                </div>
                <span class="cd-sep">:</span>
                <div class="cd-item">
                    <span class="cd-val" id="seconds">00</span>
                    <span class="cd-lbl">Detik</span>
                </div>
            </div>

            <div class="spin-wrap">
                <div class="spin"></div>
                <span class="spin-txt">Pembaruan sedang berlangsung...</span>
            </div>
        </div>

        <!-- Countdown selesai -->
        <div class="done" id="completeSection">
            <div class="done-icon">&#10004;&#65039;</div>
            <h1 class="done-title">PEMBARUAN TELAH SELESAI</h1>
            <p class="done-desc">Silakan muat ulang halaman untuk mengakses sistem.</p>
        </div>
    </main>

    <div class="footer">&copy; 2026 &mdash; SIPPI Develop by tim PPI</div>

    <script>
        /* =============================================
           UBAH TARGET COUNTDOWN DI SINI
           Format: "YYYY-MM-DDTHH:MM:SS"
           ============================================= */
        const TARGET_DATE = "2026-07-15T08:00:00";

        const daysEl    = document.getElementById('days');
        const hoursEl   = document.getElementById('hours');
        const minutesEl = document.getElementById('minutes');
        const secondsEl = document.getElementById('seconds');
        const cdSection = document.getElementById('countdownSection');
        const doneSection = document.getElementById('completeSection');

        let prev = { days:'', hours:'', minutes:'', seconds:'' };

        function update() {
            const dist = new Date(TARGET_DATE).getTime() - Date.now();
            if (dist <= 0) {
                clearInterval(tmr);
                daysEl.textContent = hoursEl.textContent = minutesEl.textContent = secondsEl.textContent = '00';
                cdSection.style.display = 'none';
                doneSection.classList.add('show');
                return;
            }
            const d = Math.floor(dist / 86400000);
            const h = Math.floor((dist % 86400000) / 3600000);
            const m = Math.floor((dist % 3600000) / 60000);
            const s = Math.floor((dist % 60000) / 1000);
            tick(daysEl,    String(d).padStart(2,'0'), 'days');
            tick(hoursEl,   String(h).padStart(2,'0'), 'hours');
            tick(minutesEl, String(m).padStart(2,'0'), 'minutes');
            tick(secondsEl, String(s).padStart(2,'0'), 'seconds');
        }

        function tick(el, val, key) {
            if (prev[key] !== val) {
                el.textContent = val;
                prev[key] = val;
                el.classList.remove('tick');
                void el.offsetWidth;
                el.classList.add('tick');
            }
        }

        update();
        const tmr = setInterval(update, 1000);
    </script>
</body>
</html>