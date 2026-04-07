-- ==============================================================================
-- FAZ 6: GÖRKEMLİ ÖDÜLLER, ŞÖHRETLER MÜZESİ VE DÜNYA KUPASI - YENİ TABLOLAR
-- Bu dosyayı veritabanınıza bir kez import edin.
-- Özellikler: Ballon d'Or, Altın Ayakkabı, Hall of Fame, TOTW/TOTS, Dünya Kupası
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- 1. OYUNCU KARİYER İSTATİSTİK SÜTUNLARI (Tüm ligler)
--    toplam_mac   : Kulüp tarihindeki toplam maç sayısı (Hall of Fame için)
--    toplam_gol   : Kulüp tarihindeki toplam gol sayısı (Hall of Fame için)
--    sezon_gol    : Bu sezon attığı gol (Altın Ayakkabı / Ballon d'Or için)
--    sezon_asist  : Bu sezon yaptığı asist
--    mac_puani_ort: Sezonluk ortalama maç puanı (Ballon d'Or için)
-- ------------------------------------------------------------------------------

ALTER TABLE `oyuncular`
    ADD COLUMN IF NOT EXISTS `toplam_mac`    INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `toplam_gol`    INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `sezon_gol`     INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `sezon_asist`   INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `mac_puani_ort` DECIMAL(4,2) NOT NULL DEFAULT 6.00;

ALTER TABLE `pl_oyuncular`
    ADD COLUMN IF NOT EXISTS `toplam_mac`    INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `toplam_gol`    INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `sezon_gol`     INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `sezon_asist`   INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `mac_puani_ort` DECIMAL(4,2) NOT NULL DEFAULT 6.00;

ALTER TABLE `es_oyuncular`
    ADD COLUMN IF NOT EXISTS `toplam_mac`    INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `toplam_gol`    INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `sezon_gol`     INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `sezon_asist`   INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `mac_puani_ort` DECIMAL(4,2) NOT NULL DEFAULT 6.00;

ALTER TABLE `de_oyuncular`
    ADD COLUMN IF NOT EXISTS `toplam_mac`    INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `toplam_gol`    INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `sezon_gol`     INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `sezon_asist`   INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `mac_puani_ort` DECIMAL(4,2) NOT NULL DEFAULT 6.00;

ALTER TABLE `it_oyuncular`
    ADD COLUMN IF NOT EXISTS `toplam_mac`    INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `toplam_gol`    INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `sezon_gol`     INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `sezon_asist`   INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `mac_puani_ort` DECIMAL(4,2) NOT NULL DEFAULT 6.00;

ALTER TABLE `fr_oyuncular`
    ADD COLUMN IF NOT EXISTS `toplam_mac`    INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `toplam_gol`    INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `sezon_gol`     INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `sezon_asist`   INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `mac_puani_ort` DECIMAL(4,2) NOT NULL DEFAULT 6.00;

ALTER TABLE `pt_oyuncular`
    ADD COLUMN IF NOT EXISTS `toplam_mac`    INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `toplam_gol`    INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `sezon_gol`     INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `sezon_asist`   INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `mac_puani_ort` DECIMAL(4,2) NOT NULL DEFAULT 6.00;

ALTER TABLE `cl_oyuncular`
    ADD COLUMN IF NOT EXISTS `toplam_mac`    INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `toplam_gol`    INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `sezon_gol`     INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `sezon_asist`   INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `mac_puani_ort` DECIMAL(4,2) NOT NULL DEFAULT 6.00;

-- ------------------------------------------------------------------------------
-- 2. DÜNYA KUPASI DÖNGÜ SAYACI (ayar tablosuna)
--    sezon_sayaci : 1-4 arası döner; 4. sezonda Dünya Kupası düzenlenir
-- ------------------------------------------------------------------------------

ALTER TABLE `ayar`
    ADD COLUMN IF NOT EXISTS `sezon_sayaci` INT NOT NULL DEFAULT 1
    COMMENT '1-4 arası döngü; 4. sezonda Dünya Kupası';

-- ------------------------------------------------------------------------------
-- 3. ÖDÜLLER TARİHİ TABLOSU (Ballon d'Or ve diğer ödüller)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `awards_history` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `sezon_yil`     INT          NOT NULL DEFAULT 2025,
  `odul_turu`     VARCHAR(50)  NOT NULL DEFAULT 'ballon_dor'
                               COMMENT 'ballon_dor | altin_ayakkabi | sezon_11_kaleci | sezon_11_def | ...',
  `oyuncu_isim`   VARCHAR(100) NOT NULL DEFAULT '',
  `takim_adi`     VARCHAR(100) NOT NULL DEFAULT '',
  `takim_lig`     VARCHAR(20)  NOT NULL DEFAULT 'tr',
  `ovr`           INT          NOT NULL DEFAULT 70,
  `gol`           INT          NOT NULL DEFAULT 0,
  `asist`         INT          NOT NULL DEFAULT 0,
  `mac_puani`     DECIMAL(4,2) NOT NULL DEFAULT 6.00,
  `kupa_bonusu`   INT          NOT NULL DEFAULT 0
                               COMMENT 'UCL=3, Lig=2, diğer kupa=1',
  `toplam_puan`   DECIMAL(8,2) NOT NULL DEFAULT 0.00
                               COMMENT 'Hesaplanan Ballon d\'Or toplam puanı',
  `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_sezon_odul` (`sezon_yil`, `odul_turu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------
-- 4. AVRUPA ALTIN AYAKKABI LOGU
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `altin_ayakkabi_log` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `sezon_yil`     INT          NOT NULL DEFAULT 2025,
  `oyuncu_isim`   VARCHAR(100) NOT NULL DEFAULT '',
  `takim_adi`     VARCHAR(100) NOT NULL DEFAULT '',
  `lig_kodu`      VARCHAR(20)  NOT NULL DEFAULT 'tr',
  `lig_adi`       VARCHAR(60)  NOT NULL DEFAULT 'Süper Lig',
  `ham_gol`       INT          NOT NULL DEFAULT 0,
  `katsayi`       DECIMAL(3,1) NOT NULL DEFAULT 1.0,
  `agirlikli_gol` DECIMAL(6,1) NOT NULL DEFAULT 0.0
                               COMMENT 'ham_gol * katsayi',
  `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_sezon` (`sezon_yil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------
-- 5. ŞÖHRETLER MÜZESİ / HALL OF FAME TABLOSU
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `hall_of_fame` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `takim_id`      INT          NOT NULL,
  `takim_lig`     VARCHAR(20)  NOT NULL DEFAULT 'tr',
  `takim_adi`     VARCHAR(100) NOT NULL DEFAULT '',
  `oyuncu_isim`   VARCHAR(100) NOT NULL DEFAULT '',
  `mevki`         VARCHAR(20)  NOT NULL DEFAULT 'ORT',
  `ovr`           INT          NOT NULL DEFAULT 70,
  `toplam_mac`    INT          NOT NULL DEFAULT 0,
  `toplam_gol`    INT          NOT NULL DEFAULT 0,
  `basari_notu`   VARCHAR(200) DEFAULT NULL
                               COMMENT 'Örn: "UCL Şampiyonu, 3x Lig Şampiyonu"',
  `emeklilik_yil` INT          NOT NULL DEFAULT 2025,
  `inducted_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------
-- 6. HAFTANIN 11'İ - TEAM OF THE WEEK (TOTW) TABLOSU
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `totw_secim` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `sezon_yil`     INT          NOT NULL DEFAULT 2025,
  `hafta`         INT          NOT NULL DEFAULT 1,
  `lig_kodu`      VARCHAR(20)  NOT NULL DEFAULT 'tr',
  `oyuncu_isim`   VARCHAR(100) NOT NULL DEFAULT '',
  `takim_adi`     VARCHAR(100) NOT NULL DEFAULT '',
  `mevki`         VARCHAR(20)  NOT NULL DEFAULT 'ORT',
  `mac_puani`     DECIMAL(4,2) NOT NULL DEFAULT 6.00,
  `gol`           INT          NOT NULL DEFAULT 0,
  `asist`         INT          NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_hafta` (`sezon_yil`, `hafta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------
-- 7. SEZONUN 11'İ - TEAM OF THE SEASON (TOTS) TABLOSU
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `tots_secim` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `sezon_yil`     INT          NOT NULL DEFAULT 2025,
  `lig_kodu`      VARCHAR(20)  NOT NULL DEFAULT 'tr',
  `oyuncu_isim`   VARCHAR(100) NOT NULL DEFAULT '',
  `takim_adi`     VARCHAR(100) NOT NULL DEFAULT '',
  `mevki`         VARCHAR(20)  NOT NULL DEFAULT 'ORT',
  `ovr`           INT          NOT NULL DEFAULT 70,
  `sezon_gol`     INT          NOT NULL DEFAULT 0,
  `sezon_asist`   INT          NOT NULL DEFAULT 0,
  `mac_puani_ort` DECIMAL(4,2) NOT NULL DEFAULT 6.00,
  `puan`          DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_sezon_lig` (`sezon_yil`, `lig_kodu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------
-- 8. DÜNYA KUPASI ANA TABLOSU
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `dunya_kupasi` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `sezon_yil`     INT          NOT NULL DEFAULT 2028,
  `durum`         VARCHAR(30)  NOT NULL DEFAULT 'kurulum'
                               COMMENT 'kurulum | grup_asamasi | son_16 | ceyrek | yari | final | tamamlandi',
  `sampion`       VARCHAR(100) DEFAULT NULL,
  `menajer_milli_takim` VARCHAR(100) DEFAULT NULL
                               COMMENT 'Kullanıcının yönettiği milli takım',
  `menajer_katildi` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_sezon` (`sezon_yil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------
-- 9. DÜNYA KUPASI TAKIMLARI TABLOSU
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `dunya_kupasi_takim` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `kupasi_id`     INT          NOT NULL,
  `takim_adi`     VARCHAR(100) NOT NULL DEFAULT '',
  `ulke_kodu`     VARCHAR(5)   NOT NULL DEFAULT '',
  `grup`          VARCHAR(5)   NOT NULL DEFAULT 'A'
                               COMMENT 'A-H arası 8 grup',
  `guc`           INT          NOT NULL DEFAULT 70
                               COMMENT 'Takımın genel gücü (50-95)',
  `puan`          INT          NOT NULL DEFAULT 0,
  `atilan_gol`    INT          NOT NULL DEFAULT 0,
  `yenilen_gol`   INT          NOT NULL DEFAULT 0,
  `elendi`        TINYINT(1)   NOT NULL DEFAULT 0,
  FOREIGN KEY (`kupasi_id`) REFERENCES `dunya_kupasi`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------
-- 10. DÜNYA KUPASI MAÇ TABLOSU
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `dunya_kupasi_mac` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `kupasi_id`     INT          NOT NULL,
  `tur`           VARCHAR(30)  NOT NULL DEFAULT 'grup'
                               COMMENT 'grup | son_16 | ceyrek | yari | uc_birincisi | final',
  `grup`          VARCHAR(5)   DEFAULT NULL,
  `ev_takim`      VARCHAR(100) NOT NULL DEFAULT '',
  `dep_takim`     VARCHAR(100) NOT NULL DEFAULT '',
  `ev_gol`        INT          DEFAULT NULL,
  `dep_gol`       INT          DEFAULT NULL,
  `uzatma`        TINYINT(1)   NOT NULL DEFAULT 0,
  `penalti`       TINYINT(1)   NOT NULL DEFAULT 0,
  `oynandi`       TINYINT(1)   NOT NULL DEFAULT 0,
  `olusturuldu`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`kupasi_id`) REFERENCES `dunya_kupasi`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
