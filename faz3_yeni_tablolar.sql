-- ==============================================================================
-- FAZ 3: OYUNCU PSİKOLOJİSİ VE YAPAY ZEKA - YENİ TABLOLAR VE SÜTUNLAR
-- Bu dosyayı veritabanınıza bir kez import edin.
-- Özellikler: PlayStyles, Derin Sakatlık, Tesis (Sağlık Merkezi), Regen, Kimya
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- 1. TÜM OYUNCULAR TABLOLARINA YENİ SÜTUNLAR EKLE
--    play_styles  : JSON dizi olarak oyuncu rozetleri (Frikik Ustası vb.)
--    ulke         : Oyuncu milliyeti (kimya hesabı için)
--    sakatlik_turu: Detaylı sakatlık adı (Çapraz Bağ Kopması vb.)
-- ------------------------------------------------------------------------------

ALTER TABLE `oyuncular`
    ADD COLUMN IF NOT EXISTS `play_styles`   VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `ulke`          VARCHAR(60)  DEFAULT 'Türkiye',
    ADD COLUMN IF NOT EXISTS `sakatlik_turu` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `pl_oyuncular`
    ADD COLUMN IF NOT EXISTS `play_styles`   VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `ulke`          VARCHAR(60)  DEFAULT 'İngiltere',
    ADD COLUMN IF NOT EXISTS `sakatlik_turu` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `es_oyuncular`
    ADD COLUMN IF NOT EXISTS `play_styles`   VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `ulke`          VARCHAR(60)  DEFAULT 'İspanya',
    ADD COLUMN IF NOT EXISTS `sakatlik_turu` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `de_oyuncular`
    ADD COLUMN IF NOT EXISTS `play_styles`   VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `ulke`          VARCHAR(60)  DEFAULT 'Almanya',
    ADD COLUMN IF NOT EXISTS `sakatlik_turu` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `it_oyuncular`
    ADD COLUMN IF NOT EXISTS `play_styles`   VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `ulke`          VARCHAR(60)  DEFAULT 'İtalya',
    ADD COLUMN IF NOT EXISTS `sakatlik_turu` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `cl_oyuncular`
    ADD COLUMN IF NOT EXISTS `play_styles`   VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `ulke`          VARCHAR(60)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `sakatlik_turu` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `uel_oyuncular`
    ADD COLUMN IF NOT EXISTS `play_styles`   VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `ulke`          VARCHAR(60)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `sakatlik_turu` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `uecl_oyuncular`
    ADD COLUMN IF NOT EXISTS `play_styles`   VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `ulke`          VARCHAR(60)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `sakatlik_turu` VARCHAR(100) DEFAULT NULL;

-- ------------------------------------------------------------------------------
-- 2. TAKİMLAR TABLOLARINA SAĞLIK MERKEZİ SEVİYESİ EKLE
--    saglik_merkezi_seviye: 1-10 arası. Sakatlık sürelerini kısaltır.
-- ------------------------------------------------------------------------------

ALTER TABLE `takimlar`
    ADD COLUMN IF NOT EXISTS `saglik_merkezi_seviye` INT DEFAULT 1;

ALTER TABLE `pl_takimlar`
    ADD COLUMN IF NOT EXISTS `saglik_merkezi_seviye` INT DEFAULT 1;

ALTER TABLE `es_takimlar`
    ADD COLUMN IF NOT EXISTS `saglik_merkezi_seviye` INT DEFAULT 1;

ALTER TABLE `de_takimlar`
    ADD COLUMN IF NOT EXISTS `saglik_merkezi_seviye` INT DEFAULT 1;

ALTER TABLE `it_takimlar`
    ADD COLUMN IF NOT EXISTS `saglik_merkezi_seviye` INT DEFAULT 1;

ALTER TABLE `cl_takimlar`
    ADD COLUMN IF NOT EXISTS `saglik_merkezi_seviye` INT DEFAULT 1;

ALTER TABLE `uel_takimlar`
    ADD COLUMN IF NOT EXISTS `saglik_merkezi_seviye` INT DEFAULT 1;

ALTER TABLE `uecl_takimlar`
    ADD COLUMN IF NOT EXISTS `saglik_merkezi_seviye` INT DEFAULT 1;

-- ------------------------------------------------------------------------------
-- 3. REGEN LOG TABLOSU
--    Emekli olan yıldızlar için oluşturulan genç oyuncuların kaydını tutar.
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `regen_log` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `sezon_yil`       INT          NOT NULL DEFAULT 2025,
  `lig`             VARCHAR(20)  NOT NULL DEFAULT 'Süper Lig',
  `emekli_isim`     VARCHAR(100) NOT NULL,
  `emekli_ovr`      INT          NOT NULL DEFAULT 70,
  `emekli_ulke`     VARCHAR(60)  DEFAULT NULL,
  `regen_isim`      VARCHAR(100) NOT NULL,
  `regen_yas`       INT          NOT NULL DEFAULT 16,
  `regen_ovr`       INT          NOT NULL DEFAULT 65,
  `regen_potansiyel`INT          NOT NULL DEFAULT 85,
  `created_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------
-- 4. MEDYA SIZINTISI LOG TABLOSU
--    Yedekte kalan yıldızların basına sızdırdığı olayları kaydeder.
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `medya_sizinti_log` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `sezon_yil`   INT          NOT NULL DEFAULT 2025,
  `hafta`       INT          NOT NULL DEFAULT 1,
  `takim_id`    INT          NOT NULL,
  `oyuncu_id`   INT          NOT NULL,
  `oyuncu_isim` VARCHAR(100) NOT NULL,
  `ovr`         INT          NOT NULL DEFAULT 75,
  `etki`        VARCHAR(255) DEFAULT NULL,
  `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------
-- 5. OYUNCU KİMYASI SEZON TABLOSU (opsiyonel: önceden hesaplanmış kimya değerleri)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `kimya_bonuslari` (
  `id`        INT AUTO_INCREMENT PRIMARY KEY,
  `takim_id`  INT  NOT NULL,
  `sezon_yil` INT  NOT NULL DEFAULT 2025,
  `hafta`     INT  NOT NULL DEFAULT 1,
  `bonus_ovr` INT  NOT NULL DEFAULT 0,
  `aciklama`  VARCHAR(255) DEFAULT NULL,
  UNIQUE KEY `uniq_takim_hafta` (`takim_id`, `sezon_yil`, `hafta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
