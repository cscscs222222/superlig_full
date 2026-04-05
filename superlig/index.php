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
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* 3D Etkileşimli Kartlar */
        .mode-card {
            position: relative;
            border-radius: 24px;
            overflow: hidden;
            text-decoration: none;
            height: 250px;
            display: flex; flex-direction: column; justify-content: flex-end;
            padding: 30px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 15px 35px rgba(0,0,0,0.5);
            group: hover;
        }

        /* Kart Arka Plan Resimleri (Saydam ve Sinematik) */
        .mode-card::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background-size: cover; background-position: center;
            opacity: 0.4; transition: 0.5s ease; z-index: 0; filter: grayscale(50%);
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
            transform: translateY(-15px) scale(1.02);
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.3);
        }
        .mode-card:hover::before { opacity: 0.8; filter: grayscale(0%); transform: scale(1.1); }

        .mode-card.tr:hover { box-shadow: 0 20px 50px rgba(225, 29, 72, 0.4); border-color: rgba(225, 29, 72, 0.5); }
        .mode-card.pl:hover { box-shadow: 0 20px 50px rgba(0, 255, 133, 0.4); border-color: rgba(0, 255, 133, 0.5); }
        .mode-card.cl:hover { box-shadow: 0 20px 50px rgba(0, 229, 255, 0.4); border-color: rgba(0, 229, 255, 0.5); }
        .mode-card.gl:hover { box-shadow: 0 20px 50px rgba(212, 175, 55, 0.4); border-color: rgba(212, 175, 55, 0.5); }
        .mode-card.es:hover { box-shadow: 0 20px 50px rgba(245, 158, 11, 0.4); border-color: rgba(245, 158, 11, 0.5); }
        .mode-card.de:hover { box-shadow: 0 20px 50px rgba(217, 119, 6, 0.4);  border-color: rgba(217, 119, 6, 0.5); }
        .mode-card.fr:hover { box-shadow: 0 20px 50px rgba(59, 130, 246, 0.4); border-color: rgba(59, 130, 246, 0.5); }
        .mode-card.it:hover { box-shadow: 0 20px 50px rgba(16, 185, 129, 0.4); border-color: rgba(16, 185, 129, 0.5); }
        .mode-card.pt:hover { box-shadow: 0 20px 50px rgba(139, 92, 246, 0.4); border-color: rgba(139, 92, 246, 0.5); }

        /* Kart İçi İçerik (Metinler) */
        .card-content { position: relative; z-index: 2; transition: 0.3s; }
        
        .card-icon {
            font-size: 2.5rem; margin-bottom: 15px; opacity: 0.7; transition: 0.3s;
        }
        .mode-card.tr .card-icon { color: #e11d48; }
        .mode-card.pl .card-icon { color: #00ff85; }
        .mode-card.cl .card-icon { color: #00e5ff; }
        .mode-card.gl .card-icon { color: #d4af37; }
        .mode-card.es .card-icon { color: #f59e0b; }
        .mode-card.de .card-icon { color: #d97706; }
        .mode-card.fr .card-icon { color: #3b82f6; }
        .mode-card.it .card-icon { color: #10b981; }
        .mode-card.pt .card-icon { color: #8b5cf6; }

        .mode-card:hover .card-icon { opacity: 1; transform: scale(1.1) rotate(5deg); }

        .card-title {
            font-family: 'Oswald', sans-serif; font-size: 2.2rem; font-weight: 800; color: #fff; margin: 0; line-height: 1.2; text-shadow: 0 2px 10px rgba(0,0,0,0.8);
        }
        .card-desc {
            font-size: 0.95rem; font-weight: 400; color: #cbd5e1; margin-top: 5px; text-shadow: 0 1px 5px rgba(0,0,0,0.8); opacity: 0.8; transition: 0.3s;
        }
        .mode-card:hover .card-desc { opacity: 1; color: #fff; }

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
                <div class="card-content">
                    <div class="card-icon"><i class="fa-solid fa-moon"></i></div>
                    <h2 class="card-title">SÜPER LİG</h2>
                    <div class="card-desc">Türkiye'nin en iyisi olmak için kıyasıya rekabet et.</div>
                </div>
            </a>

            <a href="premier_lig/premier_lig.php" class="mode-card pl">
                <div class="card-content">
                    <div class="card-icon"><i class="fa-solid fa-crown"></i></div>
                    <h2 class="card-title">PREMIER LEAGUE</h2>
                    <div class="card-desc">Ada futbolunun sert ve yüksek bütçeli dünyasına gir.</div>
                </div>
            </a>

            <a href="champions_league/cl.php" class="mode-card cl">
                <div class="card-content">
                    <div class="card-icon"><i class="fa-solid fa-trophy"></i></div>
                    <h2 class="card-title">CHAMPIONS LEAGUE</h2>
                    <div class="card-desc">Avrupa devleriyle şampiyonluk ve ülke puanı için savaş.</div>
                </div>
            </a>

            <a href="global_transfer.php" class="mode-card gl">
                <div class="card-content">
                    <div class="card-icon"><i class="fa-solid fa-globe"></i></div>
                    <h2 class="card-title">GLOBAL PAZAR</h2>
                    <div class="card-desc">Tüm dünya yıldızlarını tara ve kulübüne transfer et.</div>
                </div>
            </a>

            <a href="la_liga/la_liga.php" class="mode-card es">
                <div class="card-content">
                    <div class="card-icon"><i class="fa-solid fa-sun"></i></div>
                    <h2 class="card-title">LA LIGA</h2>
                    <div class="card-desc">İspanya'nın zirvesine ulaş. Klasik derbiler seni bekliyor.</div>
                </div>
            </a>

            <div class="mode-card de" style="cursor: not-allowed; opacity: 0.7;">
                <span class="coming-soon-badge">Yakında</span>
                <div class="card-content">
                    <div class="card-icon"><i class="fa-solid fa-bolt"></i></div>
                    <h2 class="card-title">BUNDESLIGA</h2>
                    <div class="card-desc">Alman futbolunun gücü ve disiplini ile şampiyonluğa yürü.</div>
                </div>
            </div>

            <div class="mode-card fr" style="cursor: not-allowed; opacity: 0.7;">
                <span class="coming-soon-badge">Yakında</span>
                <div class="card-content">
                    <div class="card-icon"><i class="fa-solid fa-tower-observation"></i></div>
                    <h2 class="card-title">LIGUE 1</h2>
                    <div class="card-desc">Fransa'nın elit sahalarında stil ve zarafeti birleştir.</div>
                </div>
            </div>

            <div class="mode-card it" style="cursor: not-allowed; opacity: 0.7;">
                <span class="coming-soon-badge">Yakında</span>
                <div class="card-content">
                    <div class="card-icon"><i class="fa-solid fa-shield-halved"></i></div>
                    <h2 class="card-title">SERIE A</h2>
                    <div class="card-desc">İtalya'nın taktiksel derin futbolunda bir efsane ol.</div>
                </div>
            </div>

            <div class="mode-card pt" style="cursor: not-allowed; opacity: 0.7;">
                <span class="coming-soon-badge">Yakında</span>
                <div class="card-content">
                    <div class="card-icon"><i class="fa-solid fa-star"></i></div>
                    <h2 class="card-title">LIGA NOS</h2>
                    <div class="card-desc">Portekiz'in yeteneğini keşfet ve Avrupa'ya taşı.</div>
                </div>
            </div>

        </div>
    </div>

    <div class="footer-note font-oswald">
        V1.0.0 CORE ENGINE • BÜTÜN LİGLER AKTİF • TRANSFER DÖNEMİ AÇIK
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>