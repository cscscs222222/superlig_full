-- ==============================================================================
-- FAZ 4: GLOBAL TRANSFER BORSASI VE SCOUTİNG - YENİ TABLOLAR VE SÜTUNLAR
-- Bu dosyayı veritabanınıza bir kez import edin.
-- Özellikler: Deadline Day, Scout Ağı, Bosman, Kiralık Sistemi, Release Clause
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- 1. TÜM OYUNCULAR TABLOLARINA SÖZLEŞMİ VE SERBEST KALMA BEDELİ EKLE
--    sozlesme_ay   : Kaç ay kaldığı (örn. 36 = 3 yıl)
--    release_clause: Serbest kalma bedeli (NULL = yok)
-- ------------------------------------------------------------------------------

ALTER TABLE `oyuncular`
    ADD COLUMN IF NOT EXISTS `sozlesme_ay`    INT          DEFAULT 36,
    ADD COLUMN IF NOT EXISTS `release_clause` BIGINT       DEFAULT NULL;

ALTER TABLE `pl_oyuncular`
    ADD COLUMN IF NOT EXISTS `sozlesme_ay`    INT          DEFAULT 36,
    ADD COLUMN IF NOT EXISTS `release_clause` BIGINT       DEFAULT NULL;

ALTER TABLE `es_oyuncular`
    ADD COLUMN IF NOT EXISTS `sozlesme_ay`    INT          DEFAULT 36,
    ADD COLUMN IF NOT EXISTS `release_clause` BIGINT       DEFAULT NULL;

ALTER TABLE `de_oyuncular`
    ADD COLUMN IF NOT EXISTS `sozlesme_ay`    INT          DEFAULT 36,
    ADD COLUMN IF NOT EXISTS `release_clause` BIGINT       DEFAULT NULL;

ALTER TABLE `it_oyuncular`
    ADD COLUMN IF NOT EXISTS `sozlesme_ay`    INT          DEFAULT 36,
    ADD COLUMN IF NOT EXISTS `release_clause` BIGINT       DEFAULT NULL;

ALTER TABLE `fr_oyuncular`
    ADD COLUMN IF NOT EXISTS `sozlesme_ay`    INT          DEFAULT 36,
    ADD COLUMN IF NOT EXISTS `release_clause` BIGINT       DEFAULT NULL;

ALTER TABLE `pt_oyuncular`
    ADD COLUMN IF NOT EXISTS `sozlesme_ay`    INT          DEFAULT 36,
    ADD COLUMN IF NOT EXISTS `release_clause` BIGINT       DEFAULT NULL;

ALTER TABLE `cl_oyuncular`
    ADD COLUMN IF NOT EXISTS `sozlesme_ay`    INT          DEFAULT 36,
    ADD COLUMN IF NOT EXISTS `release_clause` BIGINT       DEFAULT NULL;

ALTER TABLE `uel_oyuncular`
    ADD COLUMN IF NOT EXISTS `sozlesme_ay`    INT          DEFAULT 36,
    ADD COLUMN IF NOT EXISTS `release_clause` BIGINT       DEFAULT NULL;

ALTER TABLE `uecl_oyuncular`
    ADD COLUMN IF NOT EXISTS `sozlesme_ay`    INT          DEFAULT 36,
    ADD COLUMN IF NOT EXISTS `release_clause` BIGINT       DEFAULT NULL;

-- ------------------------------------------------------------------------------
-- 2. TRANSFER PENCERESİ DURUM TABLOSU (Deadline Day)
--    durum: 'acik' | 'kapanis' (son 24 saat) | 'kapali'
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `transfer_pencere` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `sezon_yil`     INT         NOT NULL DEFAULT 2025,
  `durum`         VARCHAR(20) NOT NULL DEFAULT 'acik'
                              COMMENT 'acik | kapanis | kapali',
  `acilis_hafta`  INT         NOT NULL DEFAULT 1,
  `kapanis_hafta` INT         NOT NULL DEFAULT 20,
  `guncellendi`   TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_sezon` (`sezon_yil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `transfer_pencere` (`sezon_yil`, `durum`, `acilis_hafta`, `kapanis_hafta`)
VALUES (2025, 'acik', 1, 20)
ON DUPLICATE KEY UPDATE sezon_yil=sezon_yil;

-- ------------------------------------------------------------------------------
-- 3. DEADLINE DAY PANİK TEKLİFLERİ LOGU
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `deadline_panik_teklif` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `sezon_yil`     INT          NOT NULL DEFAULT 2025,
  `hafta`         INT          NOT NULL DEFAULT 1,
  `teklif_eden`   VARCHAR(100) NOT NULL,
  `oyuncu_id`     INT          NOT NULL,
  `oyuncu_isim`   VARCHAR(100) NOT NULL,
  `oyuncu_lig`    VARCHAR(20)  NOT NULL,
  `teklif_tutari` BIGINT       NOT NULL DEFAULT 0,
  `durum`         VARCHAR(20)  NOT NULL DEFAULT 'beklemede'
                              COMMENT 'beklemede | kabul | ret',
  `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------
-- 4. SCOUT GÖREVLERİ TABLOSU (Global Scout Ağı)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `scout_gorevleri` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `takim_id`        INT          NOT NULL,
  `takim_lig`       VARCHAR(20)  NOT NULL DEFAULT 'tr',
  `bolge`           VARCHAR(60)  NOT NULL
                                 COMMENT 'Güney Amerika | Afrika | Doğu Avrupa | Asya | Kuzey Amerika',
  `maliyet`         INT          NOT NULL DEFAULT 500000,
  `gonderildi_hafta`INT          NOT NULL DEFAULT 1,
  `donus_hafta`     INT          NOT NULL DEFAULT 5,
  `sezon_yil`       INT          NOT NULL DEFAULT 2025,
  `durum`           VARCHAR(20)  NOT NULL DEFAULT 'yolda'
                                 COMMENT 'yolda | tamamlandi | imzalandi',
  `wonderkidler`    LONGTEXT     DEFAULT NULL
                                 COMMENT 'JSON: [{isim, yas, mevki, ovr, potansiyel, ulke}]',
  `created_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------
-- 5. KİRALIK OYUNCULAR TABLOSU (Kiralık Sistemi)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `kiralik_oyuncular` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `oyuncu_id`         INT          NOT NULL,
  `oyuncu_isim`       VARCHAR(100) NOT NULL,
  `mevki`             VARCHAR(10)  NOT NULL DEFAULT 'ORT',
  `ovr`               INT          NOT NULL DEFAULT 70,
  `yas`               INT          NOT NULL DEFAULT 21,
  `kaynak_lig`        VARCHAR(20)  NOT NULL DEFAULT 'tr',
  `kaynak_takim_id`   INT          NOT NULL,
  `kiralik_takim_adi` VARCHAR(100) NOT NULL,
  `kiralik_lig`       VARCHAR(20)  NOT NULL DEFAULT 'tr',
  `baslangic_hafta`   INT          NOT NULL DEFAULT 1,
  `bitis_hafta`       INT          NOT NULL DEFAULT 20,
  `sezon_yil`         INT          NOT NULL DEFAULT 2025,
  `maas_katki_yuzde`  INT          NOT NULL DEFAULT 50
                                   COMMENT 'Kiralayan takım maaşın yüzde kaçını öder',
  `mac_sayisi`        INT          NOT NULL DEFAULT 0,
  `durum`             VARCHAR(20)  NOT NULL DEFAULT 'aktif'
                                   COMMENT 'aktif | bitti | geri_cagrildi',
  `created_at`        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------
-- 6. ÖN ANLAŞMA (BOSMAN / SERBEST OYUNCU) TABLOSU
--    Sözleşmesi 6 ay veya daha az kalan oyuncularla yapılan ön anlaşmalar
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `on_anlasma` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `oyuncu_id`     INT          NOT NULL,
  `oyuncu_isim`   VARCHAR(100) NOT NULL,
  `mevki`         VARCHAR(10)  NOT NULL DEFAULT 'ORT',
  `ovr`           INT          NOT NULL DEFAULT 70,
  `yas`           INT          NOT NULL DEFAULT 25,
  `eski_takim_id` INT          NOT NULL,
  `eski_lig`      VARCHAR(20)  NOT NULL DEFAULT 'tr',
  `yeni_takim_id` INT          NOT NULL,
  `yeni_lig`      VARCHAR(20)  NOT NULL DEFAULT 'tr',
  `imzalandi_hafta` INT        NOT NULL DEFAULT 1,
  `sezon_yil`     INT          NOT NULL DEFAULT 2025,
  `gecerli`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_oyuncu_sezon` (`oyuncu_id`, `eski_lig`, `sezon_yil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
