-- ==============================================================================
-- FAZ 1: GLOBAL TAKVİM VE UEFA EKOSİSTEMİ - YENİ TABLOLAR
-- Bu dosyayı veritabanınıza import edin veya PHP dosyaları otomatik oluşturacak
-- ==============================================================================

-- UEFA Europa League Ayarları
CREATE TABLE IF NOT EXISTS `uel_ayar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hafta` int(11) DEFAULT 1,
  `sezon_yil` int(11) DEFAULT 2025,
  `kullanici_takim_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `uel_ayar` (`id`, `hafta`, `sezon_yil`) VALUES (1, 1, 2025)
ON DUPLICATE KEY UPDATE id=id;

-- UEFA Europa League Takımları
CREATE TABLE IF NOT EXISTS `uel_takimlar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `takim_adi` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `hucum` int(11) DEFAULT 70,
  `savunma` int(11) DEFAULT 70,
  `butce` bigint(20) DEFAULT 20000000,
  `lig` varchar(50) DEFAULT 'Avrupa',
  `puan` int(11) DEFAULT 0,
  `galibiyet` int(11) DEFAULT 0,
  `beraberlik` int(11) DEFAULT 0,
  `malubiyet` int(11) DEFAULT 0,
  `atilan_gol` int(11) DEFAULT 0,
  `yenilen_gol` int(11) DEFAULT 0,
  `dizilis` varchar(20) DEFAULT '4-3-3',
  `oyun_tarzi` varchar(50) DEFAULT 'Dengeli',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- UEFA Europa League Oyuncuları
CREATE TABLE IF NOT EXISTS `uel_oyuncular` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `takim_id` int(11) DEFAULT NULL,
  `isim` varchar(100) DEFAULT NULL,
  `mevki` varchar(10) DEFAULT NULL,
  `ovr` int(11) DEFAULT 70,
  `yas` int(11) DEFAULT 25,
  `fiyat` bigint(20) DEFAULT 5000000,
  `lig` varchar(50) DEFAULT 'Avrupa',
  `ilk_11` tinyint(1) DEFAULT 0,
  `yedek` tinyint(1) DEFAULT 0,
  `form` int(11) DEFAULT 6,
  `fitness` int(11) DEFAULT 100,
  `moral` int(11) DEFAULT 80,
  `ceza_hafta` int(11) DEFAULT 0,
  `sakatlik_hafta` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- UEFA Europa League Maçları
CREATE TABLE IF NOT EXISTS `uel_maclar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ev` int(11) DEFAULT NULL,
  `dep` int(11) DEFAULT NULL,
  `hafta` int(11) DEFAULT NULL,
  `sezon_yil` int(11) DEFAULT 2025,
  `ev_skor` int(11) DEFAULT NULL,
  `dep_skor` int(11) DEFAULT NULL,
  `ev_olaylar` text,
  `dep_olaylar` text,
  `ev_kartlar` text,
  `dep_kartlar` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- UEFA Europa League Haberleri
CREATE TABLE IF NOT EXISTS `uel_haberler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hafta` int(11) DEFAULT NULL,
  `metin` text,
  `tip` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- UEFA Conference League Ayarları
CREATE TABLE IF NOT EXISTS `uecl_ayar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hafta` int(11) DEFAULT 1,
  `sezon_yil` int(11) DEFAULT 2025,
  `kullanici_takim_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `uecl_ayar` (`id`, `hafta`, `sezon_yil`) VALUES (1, 1, 2025)
ON DUPLICATE KEY UPDATE id=id;

-- UEFA Conference League Takımları
CREATE TABLE IF NOT EXISTS `uecl_takimlar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `takim_adi` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `hucum` int(11) DEFAULT 65,
  `savunma` int(11) DEFAULT 65,
  `butce` bigint(20) DEFAULT 12000000,
  `lig` varchar(50) DEFAULT 'Avrupa',
  `puan` int(11) DEFAULT 0,
  `galibiyet` int(11) DEFAULT 0,
  `beraberlik` int(11) DEFAULT 0,
  `malubiyet` int(11) DEFAULT 0,
  `atilan_gol` int(11) DEFAULT 0,
  `yenilen_gol` int(11) DEFAULT 0,
  `dizilis` varchar(20) DEFAULT '4-4-2',
  `oyun_tarzi` varchar(50) DEFAULT 'Dengeli',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- UEFA Conference League Oyuncuları
CREATE TABLE IF NOT EXISTS `uecl_oyuncular` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `takim_id` int(11) DEFAULT NULL,
  `isim` varchar(100) DEFAULT NULL,
  `mevki` varchar(10) DEFAULT NULL,
  `ovr` int(11) DEFAULT 65,
  `yas` int(11) DEFAULT 26,
  `fiyat` bigint(20) DEFAULT 3000000,
  `lig` varchar(50) DEFAULT 'Avrupa',
  `ilk_11` tinyint(1) DEFAULT 0,
  `yedek` tinyint(1) DEFAULT 0,
  `form` int(11) DEFAULT 6,
  `fitness` int(11) DEFAULT 100,
  `moral` int(11) DEFAULT 80,
  `ceza_hafta` int(11) DEFAULT 0,
  `sakatlik_hafta` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- UEFA Conference League Maçları
CREATE TABLE IF NOT EXISTS `uecl_maclar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ev` int(11) DEFAULT NULL,
  `dep` int(11) DEFAULT NULL,
  `hafta` int(11) DEFAULT NULL,
  `sezon_yil` int(11) DEFAULT 2025,
  `ev_skor` int(11) DEFAULT NULL,
  `dep_skor` int(11) DEFAULT NULL,
  `ev_olaylar` text,
  `dep_olaylar` text,
  `ev_kartlar` text,
  `dep_kartlar` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- UEFA Conference League Haberleri
CREATE TABLE IF NOT EXISTS `uecl_haberler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hafta` int(11) DEFAULT NULL,
  `metin` text,
  `tip` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Turnuva Şampiyonları (Super Cup için)
CREATE TABLE IF NOT EXISTS `tournaments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `turnuva` varchar(50) DEFAULT NULL,
  `sezon_yil` int(11) DEFAULT NULL,
  `sampiyon_id` int(11) DEFAULT NULL,
  `sampiyon_adi` varchar(100) DEFAULT NULL,
  `sampiyon_lig` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_turnuva_sezon` (`turnuva`,`sezon_yil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- UEFA Süper Kupa Maçları
CREATE TABLE IF NOT EXISTS `super_cup_maclar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sezon_yil` int(11) DEFAULT NULL,
  `ucl_takim` varchar(100) DEFAULT NULL,
  `ucl_logo` varchar(255) DEFAULT NULL,
  `ucl_skor` int(11) DEFAULT NULL,
  `uel_takim` varchar(100) DEFAULT NULL,
  `uel_logo` varchar(255) DEFAULT NULL,
  `uel_skor` int(11) DEFAULT NULL,
  `kazanan` varchar(100) DEFAULT NULL,
  `tarih` timestamp NOT NULL DEFAULT current_timestamp(),
  `ucl_olaylar` text,
  `uel_olaylar` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- UEFA Ülke Katsayıları
CREATE TABLE IF NOT EXISTS `uefa_coefficients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ulke_adi` varchar(50) DEFAULT NULL,
  `toplam_puan` decimal(10,3) DEFAULT 0.000,
  `sezon_puan` decimal(10,3) DEFAULT 0.000,
  `ucl_kota` int(11) DEFAULT 2,
  `uel_kota` int(11) DEFAULT 2,
  `uecl_kota` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ulke_adi` (`ulke_adi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Başlangıç UEFA Katsayı Verileri (UEFA 2024-25 baz alınmıştır)
INSERT INTO `uefa_coefficients` (`ulke_adi`, `toplam_puan`, `ucl_kota`, `uel_kota`, `uecl_kota`) VALUES
('İngiltere', 103.160, 4, 2, 1),
('İspanya',   96.231, 4, 2, 1),
('Almanya',   82.946, 4, 2, 1),
('İtalya',    82.946, 4, 2, 1),
('Fransa',    67.164, 3, 2, 1),
('Türkiye',   38.500, 2, 2, 1),
('Portekiz',  61.866, 3, 2, 1)
ON DUPLICATE KEY UPDATE ulke_adi=ulke_adi;
