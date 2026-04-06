-- ==============================================================================
-- FAZ 2: CANLI MAÇ MOTORU 2.0 - YENİ TABLOLAR VE SÜTUNLAR
-- Bu dosyayı veritabanınıza bir kez import edin.
-- Özellikler: Hava Durumu, VAR Sistemi, Derbi, Kaleci Kurtarış, Altın Eldiven
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- 1. TÜM MAÇLAR TABLOLARINA YENİ SÜTUNLAR EKLE
--    (Hava Durumu, VAR Olayları, Kaleci Kurtarışları)
-- ------------------------------------------------------------------------------

-- Süper Lig
ALTER TABLE `maclar`
    ADD COLUMN IF NOT EXISTS `hava_durumu`   VARCHAR(20)  DEFAULT 'Güneşli',
    ADD COLUMN IF NOT EXISTS `var_olaylar`   TEXT         DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `ev_kurtaris`   INT          DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `dep_kurtaris`  INT          DEFAULT 0;

-- Premier Lig
ALTER TABLE `pl_maclar`
    ADD COLUMN IF NOT EXISTS `hava_durumu`   VARCHAR(20)  DEFAULT 'Güneşli',
    ADD COLUMN IF NOT EXISTS `var_olaylar`   TEXT         DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `ev_kurtaris`   INT          DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `dep_kurtaris`  INT          DEFAULT 0;

-- La Liga
ALTER TABLE `es_maclar`
    ADD COLUMN IF NOT EXISTS `hava_durumu`   VARCHAR(20)  DEFAULT 'Güneşli',
    ADD COLUMN IF NOT EXISTS `var_olaylar`   TEXT         DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `ev_kurtaris`   INT          DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `dep_kurtaris`  INT          DEFAULT 0;

-- Bundesliga
ALTER TABLE `de_maclar`
    ADD COLUMN IF NOT EXISTS `hava_durumu`   VARCHAR(20)  DEFAULT 'Güneşli',
    ADD COLUMN IF NOT EXISTS `var_olaylar`   TEXT         DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `ev_kurtaris`   INT          DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `dep_kurtaris`  INT          DEFAULT 0;

-- Serie A
ALTER TABLE `it_maclar`
    ADD COLUMN IF NOT EXISTS `hava_durumu`   VARCHAR(20)  DEFAULT 'Güneşli',
    ADD COLUMN IF NOT EXISTS `var_olaylar`   TEXT         DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `ev_kurtaris`   INT          DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `dep_kurtaris`  INT          DEFAULT 0;

-- Ligue 1
ALTER TABLE `fr_maclar`
    ADD COLUMN IF NOT EXISTS `hava_durumu`   VARCHAR(20)  DEFAULT 'Güneşli',
    ADD COLUMN IF NOT EXISTS `var_olaylar`   TEXT         DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `ev_kurtaris`   INT          DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `dep_kurtaris`  INT          DEFAULT 0;

-- Portekiz Ligi
ALTER TABLE `pt_maclar`
    ADD COLUMN IF NOT EXISTS `hava_durumu`   VARCHAR(20)  DEFAULT 'Güneşli',
    ADD COLUMN IF NOT EXISTS `var_olaylar`   TEXT         DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `ev_kurtaris`   INT          DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `dep_kurtaris`  INT          DEFAULT 0;

-- Şampiyonlar Ligi
ALTER TABLE `cl_maclar`
    ADD COLUMN IF NOT EXISTS `hava_durumu`   VARCHAR(20)  DEFAULT 'Güneşli',
    ADD COLUMN IF NOT EXISTS `var_olaylar`   TEXT         DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `ev_kurtaris`   INT          DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `dep_kurtaris`  INT          DEFAULT 0;

-- Avrupa Ligi
ALTER TABLE `uel_maclar`
    ADD COLUMN IF NOT EXISTS `hava_durumu`   VARCHAR(20)  DEFAULT 'Güneşli',
    ADD COLUMN IF NOT EXISTS `var_olaylar`   TEXT         DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `ev_kurtaris`   INT          DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `dep_kurtaris`  INT          DEFAULT 0;

-- Konferans Ligi
ALTER TABLE `uecl_maclar`
    ADD COLUMN IF NOT EXISTS `hava_durumu`   VARCHAR(20)  DEFAULT 'Güneşli',
    ADD COLUMN IF NOT EXISTS `var_olaylar`   TEXT         DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `ev_kurtaris`   INT          DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `dep_kurtaris`  INT          DEFAULT 0;

-- ------------------------------------------------------------------------------
-- 2. TÜM OYUNCULAR TABLOLARINA KURTARİŞ SÜTUNU EKLE (Kaleci İstatistikleri)
-- ------------------------------------------------------------------------------

ALTER TABLE `oyuncular`     ADD COLUMN IF NOT EXISTS `kurtaris` INT DEFAULT 0;
ALTER TABLE `pl_oyuncular`  ADD COLUMN IF NOT EXISTS `kurtaris` INT DEFAULT 0;
ALTER TABLE `es_oyuncular`  ADD COLUMN IF NOT EXISTS `kurtaris` INT DEFAULT 0;
ALTER TABLE `de_oyuncular`  ADD COLUMN IF NOT EXISTS `kurtaris` INT DEFAULT 0;
ALTER TABLE `it_oyuncular`  ADD COLUMN IF NOT EXISTS `kurtaris` INT DEFAULT 0;
ALTER TABLE `fr_oyuncular`  ADD COLUMN IF NOT EXISTS `kurtaris` INT DEFAULT 0;
ALTER TABLE `pt_oyuncular`  ADD COLUMN IF NOT EXISTS `kurtaris` INT DEFAULT 0;
ALTER TABLE `cl_oyuncular`  ADD COLUMN IF NOT EXISTS `kurtaris` INT DEFAULT 0;
ALTER TABLE `uel_oyuncular` ADD COLUMN IF NOT EXISTS `kurtaris` INT DEFAULT 0;
ALTER TABLE `uecl_oyuncular` ADD COLUMN IF NOT EXISTS `kurtaris` INT DEFAULT 0;

-- ------------------------------------------------------------------------------
-- 3. ALTIN ELDİVEN SEZON İSTATİSTİKLERİ TABLOSU
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `golden_glove_stats` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `oyuncu_id`      INT          NOT NULL,
  `oyuncu_isim`    VARCHAR(100) NOT NULL,
  `takim_id`       INT          NOT NULL,
  `takim_adi`      VARCHAR(100) DEFAULT NULL,
  `lig`            VARCHAR(20)  NOT NULL,
  `sezon_yil`      INT          NOT NULL DEFAULT 2025,
  `kurtaris`       INT          NOT NULL DEFAULT 0,
  `gol_yenilen`    INT          NOT NULL DEFAULT 0,
  `mac_sayisi`     INT          NOT NULL DEFAULT 0,
  `odul_kazandi`   TINYINT(1)   DEFAULT 0,
  UNIQUE KEY `uniq_oyuncu_sezon` (`oyuncu_id`, `lig`, `sezon_yil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------
-- 4. TAKTİK DEĞİŞİKLİĞİ: TAKİMLAR TABLOSUNA DİZİLİŞ VE OYUN TARZI SÜTUNLARI
--    (Yoksa ekle; kadro.php zaten bunları kullanıyor)
-- ------------------------------------------------------------------------------

ALTER TABLE `takimlar`
    ADD COLUMN IF NOT EXISTS `dizilis`     VARCHAR(20) DEFAULT '4-3-3',
    ADD COLUMN IF NOT EXISTS `oyun_tarzi`  VARCHAR(50) DEFAULT 'Dengeli',
    ADD COLUMN IF NOT EXISTS `pres`        VARCHAR(20) DEFAULT 'Orta',
    ADD COLUMN IF NOT EXISTS `tempo`       VARCHAR(20) DEFAULT 'Normal';
