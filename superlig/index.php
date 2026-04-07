<?php
// ==============================================================================
// ULTIMATE MANAGER - NEXT-GEN MERKEZ HUB (ULTRA MODERN GLASSMORPHISM THEME)
// ==============================================================================
include 'db.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ultimate Manager | Ana Merkez</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- YENİ NESİL ARKA PLAN VE GENEL AYARLAR --- */
        body, html {
            margin: 0; padding: 0; min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            background-color: #050505;
            color: #fff;
            overflow-x: hidden;
        }

        /* Dinamik ve sinematik stadyum arka planı */
        .bg-image {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: url('https://images.unsplash.com/photo-1508344928928-7137b29de216?q=80&w=2000') no-repeat center center;
            background-size: cover;
            z-index: -2;
            animation: slowZoom 20s infinite alternate;
        }
        @keyframes slowZoom { 0% { transform: scale(1); } 100% { transform: scale(1.05); } }

        /* Karanlık Filtre (Cam Efekti için zemin) */
        .bg-overlay {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: linear-gradient(135deg, rgba(5,5,5,0.9) 0%, rgba(20,20,30,0.75) 100%);
            backdrop-filter: blur(8px);
            z-index: -1;
        }

        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        /* --- MODERN NAVBAR --- */
        .modern-nav {
            padding: 25px 50px;
            display: flex; justify-content: center; align-items: center;
            background: transparent;
        }
        .nav-brand {
            font-size: 2rem; font-weight: 900; color: #fff; text-decoration: none; letter-spacing: 3px;
            text-shadow: 0 0 20px rgba(212, 175, 55, 0.5);
            display: flex; align-items: center; gap: 15px;
        }
        .nav-brand i { color: #d4af37; font-size: 2.2rem; }

        /* --- HERO (BAŞLIK) KISMI --- */
        .hero-section {
            text-align: center; padding: 40px 20px;
        }
        .hero-title {
            font-size: 4.5rem; font-weight: 900; color: #fff; line-height: 1.1; margin-bottom: 10px;
            text-shadow: 0 10px 30px rgba(0,0,0,0.8);
        }
        .hero-subtitle {
            font-size: 1.2rem; font-weight: 300; color: #cbd5e1; letter-spacing: 2px;
        }
        .gold-text { background: linear-gradient(45deg, #d4af37, #fde047); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        /* --- GLASSMORPHISM KART GRID --- */
        .game-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 32px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px 30px;
        }

        /* 3D Etkileşimli Kartlar */
        .mode-card {
            position: relative;
            border-radius: 28px;
            overflow: hidden;
            text-decoration: none;
            height: 300px;
            display: flex; flex-direction: column; justify-content: flex-end;
            padding: 28px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            transition: all 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 20px 40px rgba(0,0,0,0.6), inset 0 1px 0 rgba(255,255,255,0.1);
        }

        /* Kart Arka Plan Resimleri (Saydam ve Sinematik) */
        .mode-card::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background-size: cover; background-position: center;
            opacity: 0.25; transition: 0.6s ease; z-index: 0; filter: grayscale(60%) blur(1px);
        }
        .mode-card.tr::before { background-image: url('https://images.unsplash.com/photo-1518605368461-1e1e38ce8ba4?q=80&w=800'); }
        .mode-card.pl::before { background-image: url('https://images.unsplash.com/photo-1489944440615-453fc2b6a9a9?q=80&w=800'); }
        .mode-card.cl::before { background-image: url('https://images.unsplash.com/photo-1556816214-cb3ce168c076?q=80&w=800'); }
        .mode-card.gl::before { background-image: url('https://images.unsplash.com/photo-1522778526097-ce0a22ceb253?q=80&w=800'); }
        .mode-card.es::before { background-image: url('https://images.unsplash.com/photo-1546519638-68e109498ffc?q=80&w=800'); }
        .mode-card.de::before { background-image: url('https://images.unsplash.com/photo-1551958219-acbc595d0b17?q=80&w=800'); }
        .mode-card.fr::before { background-image: url('https://images.unsplash.com/photo-1508344928928-7137b29de216?q=80&w=800'); }
        .mode-card.it::before { background-image: url('https://images.unsplash.com/photo-1516382799247-87df95d790b7?q=80&w=800'); }
        .mode-card.pt::before { background-image: url('https://images.unsplash.com/photo-1574629810360-7efbbe195018?q=80&w=800'); }

        /* Kart Rengi Glow Efektleri (Hover) */
        .mode-card:hover {
            transform: translateY(-18px) scale(1.02);
            background: rgba(255, 255, 255, 0.09);
            border-color: rgba(255, 255, 255, 0.3);
        }
        .mode-card:hover::before { opacity: 0.65; filter: grayscale(0%) blur(0px); transform: scale(1.08); }

        .mode-card.tr:hover { box-shadow: 0 25px 60px rgba(225, 29, 72, 0.45), inset 0 1px 0 rgba(255,255,255,0.15); border-color: rgba(225, 29, 72, 0.6); }
        .mode-card.pl:hover { box-shadow: 0 25px 60px rgba(123, 44, 191, 0.45), inset 0 1px 0 rgba(255,255,255,0.15); border-color: rgba(123, 44, 191, 0.6); }
        .mode-card.cl:hover { box-shadow: 0 25px 60px rgba(0, 229, 255, 0.45), inset 0 1px 0 rgba(255,255,255,0.15); border-color: rgba(0, 229, 255, 0.6); }
        .mode-card.gl:hover { box-shadow: 0 25px 60px rgba(212, 175, 55, 0.45), inset 0 1px 0 rgba(255,255,255,0.15); border-color: rgba(212, 175, 55, 0.6); }
        .mode-card.es:hover { box-shadow: 0 25px 60px rgba(245, 158, 11, 0.45), inset 0 1px 0 rgba(255,255,255,0.15); border-color: rgba(245, 158, 11, 0.6); }
        .mode-card.de:hover { box-shadow: 0 25px 60px rgba(229, 57, 53, 0.45), inset 0 1px 0 rgba(255,255,255,0.15); border-color: rgba(229, 57, 53, 0.6); }
        .mode-card.fr:hover { box-shadow: 0 25px 60px rgba(59, 130, 246, 0.45), inset 0 1px 0 rgba(255,255,255,0.15); border-color: rgba(59, 130, 246, 0.6); }
        .mode-card.it:hover { box-shadow: 0 25px 60px rgba(16, 185, 129, 0.45), inset 0 1px 0 rgba(255,255,255,0.15); border-color: rgba(16, 185, 129, 0.6); }
        .mode-card.pt:hover { box-shadow: 0 25px 60px rgba(139, 92, 246, 0.45), inset 0 1px 0 rgba(255,255,255,0.15); border-color: rgba(139, 92, 246, 0.6); }

        /* --- LİG LOGO ALANI --- */
        .card-logo-wrapper {
            position: absolute; top: 0; left: 0; width: 100%; height: 60%;
            display: flex; align-items: center; justify-content: center; z-index: 1;
        }
        .card-logo-bg {
            width: 110px; height: 110px; border-radius: 50%;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            display: flex; align-items: center; justify-content: center;
            backdrop-filter: blur(10px);
            transition: all 0.4s ease;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
        }
        .mode-card:hover .card-logo-bg {
            transform: scale(1.12);
            background: rgba(255,255,255,0.14);
            border-color: rgba(255,255,255,0.3);
            box-shadow: 0 12px 40px rgba(0,0,0,0.5);
        }
        .card-logo-bg img {
            width: 72px; height: 72px; object-fit: contain;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.5)) brightness(1.05);
            transition: all 0.4s ease;
        }
        .mode-card:hover .card-logo-bg img { filter: drop-shadow(0 6px 20px rgba(0,0,0,0.6)) brightness(1.15); transform: scale(1.05); }

        /* Glow renkleri logo çevresinde (hover) */
        .mode-card.tr:hover .card-logo-bg { box-shadow: 0 0 30px rgba(225,29,72,0.5), 0 8px 32px rgba(0,0,0,0.4); border-color: rgba(225,29,72,0.5); }
        .mode-card.pl:hover .card-logo-bg { box-shadow: 0 0 30px rgba(123,44,191,0.5), 0 8px 32px rgba(0,0,0,0.4); border-color: rgba(123,44,191,0.5); }
        .mode-card.cl:hover .card-logo-bg { box-shadow: 0 0 30px rgba(0,229,255,0.5), 0 8px 32px rgba(0,0,0,0.4); border-color: rgba(0,229,255,0.5); }
        .mode-card.gl:hover .card-logo-bg { box-shadow: 0 0 30px rgba(212,175,55,0.5), 0 8px 32px rgba(0,0,0,0.4); border-color: rgba(212,175,55,0.5); }
        .mode-card.es:hover .card-logo-bg { box-shadow: 0 0 30px rgba(245,158,11,0.5), 0 8px 32px rgba(0,0,0,0.4); border-color: rgba(245,158,11,0.5); }
        .mode-card.de:hover .card-logo-bg { box-shadow: 0 0 30px rgba(229,57,53,0.5), 0 8px 32px rgba(0,0,0,0.4); border-color: rgba(229,57,53,0.5); }
        .mode-card.it:hover .card-logo-bg { box-shadow: 0 0 30px rgba(16,185,129,0.5), 0 8px 32px rgba(0,0,0,0.4); border-color: rgba(16,185,129,0.5); }

        /* Kart İçi İçerik (Metinler) */
        .card-content { position: relative; z-index: 2; transition: 0.3s; }
        .card-title {
            font-family: 'Oswald', sans-serif; font-size: 1.9rem; font-weight: 800; color: #fff; margin: 0; line-height: 1.2; text-shadow: 0 2px 10px rgba(0,0,0,0.8);
        }
        .card-desc {
            font-size: 0.88rem; font-weight: 400; color: #94a3b8; margin-top: 6px; text-shadow: 0 1px 5px rgba(0,0,0,0.8); opacity: 0.85; transition: 0.3s;
        }
        .mode-card:hover .card-desc { opacity: 1; color: #e2e8f0; }

        /* Play CTA okı */
        .card-cta {
            display: inline-flex; align-items: center; gap: 6px;
            margin-top: 12px; font-size: 0.8rem; font-weight: 700; letter-spacing: 1.5px;
            text-transform: uppercase; opacity: 0; transform: translateY(8px); transition: 0.35s;
        }
        .mode-card:hover .card-cta { opacity: 1; transform: translateY(0); }
        .mode-card.tr .card-cta { color: #e11d48; }
        .mode-card.pl .card-cta { color: #a855f7; }
        .mode-card.cl .card-cta { color: #00e5ff; }
        .mode-card.gl .card-cta { color: #d4af37; }
        .mode-card.es .card-cta { color: #f59e0b; }
        .mode-card.de .card-cta { color: #ef4444; }
        .mode-card.it .card-cta { color: #10b981; }

        /* Alt Futbol Bilgisi */
        .footer-note { text-align: center; margin-top: 60px; color: rgba(255,255,255,0.3); font-size: 0.85rem; letter-spacing: 1px; padding-bottom: 20px; }

        /* Yakında Gelecek Rozeti */
        .coming-soon-badge {
            position: absolute; top: 15px; right: 15px; z-index: 3;
            background: rgba(0,0,0,0.7); color: rgba(255,255,255,0.6);
            font-size: 0.65rem; font-weight: 800; padding: 4px 10px;
            border-radius: 20px; border: 1px solid rgba(255,255,255,0.2);
            text-transform: uppercase; letter-spacing: 1px;
        }

    </style>
</head>
<body>

    <div class="bg-image"></div>
    <div class="bg-overlay"></div>

    <div class="modern-nav">
        <a href="index.php" class="nav-brand font-oswald">
            <i class="fa-solid fa-chess-knight"></i> 
            ULTIMATE <span class="gold-text">MANAGER</span>
        </a>
    </div>

    <div class="hero-section">
        <h1 class="hero-title font-oswald">SAHNEYE ÇIKMA VAKTİ</h1>
        <p class="hero-subtitle">Dünyanın en iyi futbol arenasında kulübünün kaderini belirle.</p>
    </div>

    <div class="container-fluid">
        <div class="game-grid">
            
            <a href="super_lig/superlig.php" class="mode-card tr">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/5/55/S%C3%BCper_Lig_logo.svg/200px-S%C3%BCper_Lig_logo.svg.png"
                             alt="Süper Lig Logo"
                             onerror="logoFallback(this,'fa-solid fa-moon','#e11d48')">
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">SÜPER LİG</h2>
                    <div class="card-desc">Türkiye'nin en iyisi olmak için kıyasıya rekabet et.</div>
                    <div class="card-cta"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <a href="premier_lig/premier_lig.php" class="mode-card pl">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <img src="https://upload.wikimedia.org/wikipedia/en/thumb/f/f2/Premier_League_Logo.svg/200px-Premier_League_Logo.svg.png"
                             alt="Premier League Logo"
                             onerror="logoFallback(this,'fa-solid fa-crown','#a855f7')">
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">PREMIER LEAGUE</h2>
                    <div class="card-desc">Ada futbolunun sert ve yüksek bütçeli dünyasına gir.</div>
                    <div class="card-cta"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <a href="champions_league/cl.php" class="mode-card cl">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <img src="https://upload.wikimedia.org/wikipedia/en/thumb/b/bf/UEFA_Champions_League_logo_2.svg/200px-UEFA_Champions_League_logo_2.svg.png"
                             alt="Champions League Logo"
                             onerror="logoFallback(this,'fa-solid fa-trophy','#00e5ff')">
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">CHAMPIONS LEAGUE</h2>
                    <div class="card-desc">Avrupa devleriyle şampiyonluk ve ülke puanı için savaş.</div>
                    <div class="card-cta"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <a href="global_transfer.php" class="mode-card gl">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-globe" style="font-size:3rem; color:#d4af37;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">GLOBAL PAZAR</h2>
                    <div class="card-desc">Tüm dünya yıldızlarını tara ve kulübüne transfer et.</div>
                    <div class="card-cta"><i class="fa-solid fa-play"></i> GİT</div>
                </div>
            </a>

            <a href="la_liga/la_liga.php" class="mode-card es">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/LaLiga_logo_2023.svg/200px-LaLiga_logo_2023.svg.png"
                             alt="La Liga Logo"
                             onerror="logoFallback(this,'fa-solid fa-sun','#f59e0b')">
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">LA LIGA</h2>
                    <div class="card-desc">İspanya'nın zirvesine ulaş. Klasik derbiler seni bekliyor.</div>
                    <div class="card-cta"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <a href="bundesliga/bundesliga.php" class="mode-card de">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <img src="https://upload.wikimedia.org/wikipedia/en/thumb/d/df/Bundesliga_logo_%282017%29.svg/200px-Bundesliga_logo_%282017%29.svg.png"
                             alt="Bundesliga Logo"
                             onerror="logoFallback(this,'fa-solid fa-bolt','#ef4444')">
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">BUNDESLIGA</h2>
                    <div class="card-desc">Alman futbolunun gücü ve disiplini ile şampiyonluğa yürü.</div>
                    <div class="card-cta"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <div class="mode-card fr" style="cursor: not-allowed; opacity: 0.65;">
                <span class="coming-soon-badge">Yakında</span>
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/b/b1/Ligue_1_Uber_Eats_logo.svg/200px-Ligue_1_Uber_Eats_logo.svg.png"
                             alt="Ligue 1 Logo"
                             onerror="logoFallback(this,'fa-solid fa-tower-observation','#3b82f6')">
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">LIGUE 1</h2>
                    <div class="card-desc">Fransa'nın elit sahalarında stil ve zarafeti birleştir.</div>
                </div>
            </div>

            <a href="serie_a/serie_a.php" class="mode-card it">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/e9/Serie_A_logo_2022.svg/200px-Serie_A_logo_2022.svg.png"
                             alt="Serie A Logo"
                             onerror="logoFallback(this,'fa-solid fa-shield-halved','#10b981')">
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">SERIE A</h2>
                    <div class="card-desc">İtalya'nın taktiksel derin futbolunda bir efsane ol.</div>
                    <div class="card-cta"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <div class="mode-card pt" style="cursor: not-allowed; opacity: 0.65;">
                <span class="coming-soon-badge">Yakında</span>
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-star" style="font-size:3rem; color:#8b5cf6;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">LIGA NOS</h2>
                    <div class="card-desc">Portekiz'in yeteneğini keşfet ve Avrupa'ya taşı.</div>
                </div>
            </div>

            <!-- FAZ 1: AVRUPA LİGİ -->
            <a href="uel/uel.php" class="mode-card" style="--card-color:#f04e23;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <img src="https://upload.wikimedia.org/wikipedia/en/thumb/3/35/UEFA_Europa_League_logo_%282.0%29.svg/200px-UEFA_Europa_League_logo_%282.0%29.svg.png"
                             alt="Europa League Logo"
                             onerror="logoFallback(this,'fa-solid fa-fire','#f04e23')">
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">EUROPA LEAGUE</h2>
                    <div class="card-desc">5. ve 6. sıra takımların Perşembe gecesi yarışması.</div>
                    <div class="card-cta" style="color:#f04e23;"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <!-- FAZ 1: KONFERANS LİGİ -->
            <a href="uecl/uecl.php" class="mode-card" style="--card-color:#2ecc71;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <img src="https://upload.wikimedia.org/wikipedia/en/thumb/4/45/UEFA_Europa_Conference_League_logo.svg/200px-UEFA_Europa_Conference_League_logo.svg.png"
                             alt="Conference League Logo"
                             onerror="logoFallback(this,'fa-solid fa-earth-europe','#2ecc71')">
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">CONFERENCE LEAGUE</h2>
                    <div class="card-desc">7. ve 8. sıraların Avrupa serüveni başlıyor.</div>
                    <div class="card-cta" style="color:#2ecc71;"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <!-- FAZ 1: UEFA SÜPER KUPA -->
            <a href="super_cup/super_cup.php" class="mode-card gl">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-trophy" style="font-size:3rem; color:#d4af37;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">SÜPER KUPA</h2>
                    <div class="card-desc">UCL şampiyonu × UEL şampiyonu — Sezonun açılış maçı!</div>
                    <div class="card-cta"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <!-- FAZ 1: GLOBAL TAKVİM -->
            <a href="takvim.php" class="mode-card" style="--card-color:#94a3b8;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-calendar-days" style="font-size:2.8rem; color:#94a3b8;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">GLOBAL TAKVİM</h2>
                    <div class="card-desc">Tüm liglerin ve Avrupa kupalarının haftalık özeti.</div>
                    <div class="card-cta" style="color:#94a3b8;"><i class="fa-solid fa-arrow-right"></i> GİT</div>
                </div>
            </a>

            <!-- FAZ 4: DEADLINE DAY -->
            <a href="deadline_day.php" class="mode-card" style="--card-color:#ef4444;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-clock-rotate-left" style="font-size:2.8rem; color:#ef4444;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">DEADLINE DAY</h2>
                    <div class="card-desc">Transfer penceresinin son 24 saati! Panik tekliflerini yönet.</div>
                    <div class="card-cta" style="color:#ef4444;"><i class="fa-solid fa-bolt"></i> GİT</div>
                </div>
            </a>

            <!-- FAZ 4: SCOUT AĞI -->
            <a href="scout.php" class="mode-card" style="--card-color:#10b981;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-binoculars" style="font-size:2.8rem; color:#10b981;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">SCOUT AĞI</h2>
                    <div class="card-desc">Brezilya, Afrika, Balkanlar'a gözlemci gönder. Wonderkid keşfet!</div>
                    <div class="card-cta" style="color:#10b981;"><i class="fa-solid fa-plane-departure"></i> GİT</div>
                </div>
            </a>

            <!-- FAZ 4: BOSMAN / SERBEST OYUNCULAR -->
            <a href="serbest_oyuncular.php" class="mode-card" style="--card-color:#8b5cf6;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-handshake" style="font-size:2.8rem; color:#8b5cf6;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">SERBEST OYUNCULAR</h2>
                    <div class="card-desc">Sözleşmesi biten yıldızları bonservis ödemeden kadrana kat!</div>
                    <div class="card-cta" style="color:#8b5cf6;"><i class="fa-solid fa-file-contract"></i> GİT</div>
                </div>
            </a>

            <!-- FAZ 4: KİRALIK SİSTEMİ -->
            <a href="kiralik.php" class="mode-card" style="--card-color:#3b82f6;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-arrows-rotate" style="font-size:2.8rem; color:#3b82f6;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">KİRALIK SİSTEMİ</h2>
                    <div class="card-desc">Genç oyuncuları kiralık gönder, maaş kazan, gelişimlerini hızlandır.</div>
                    <div class="card-cta" style="color:#3b82f6;"><i class="fa-solid fa-arrow-right-arrow-left"></i> GİT</div>
                </div>
            </a>

        </div>
    </div>

    <div class="footer-note font-oswald">
        V4.0.0 PHASE 4 — KÜRESEL TRANSFER BORSASI AKTİF • DEADLINE DAY / SCOUT / BOSMAN / KİRALIK / RELEASE CLAUSE
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function logoFallback(img, iconClass, color) {
            img.style.display = 'none';
            var el = document.createElement('i');
            el.className = iconClass;
            el.style.fontSize = '2.8rem';
            el.style.color = color;
            img.parentElement.appendChild(el);
        }
    </script>
</body>
</html>