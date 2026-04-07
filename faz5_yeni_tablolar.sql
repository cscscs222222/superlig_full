-- ==============================================================================
-- FAZ 5: CEO KULÜP YÖNETİMİ VE FİNANSAL STRATEJİ - YENİ TABLOLAR VE SÜTUNLAR
-- Bu dosyayı veritabanınıza bir kez import edin.
-- Özellikler: FFP, Bilet Fiyatları, Sponsorluk, Forma Satışları, Menajer Kariyer
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- 1. KULÜP FİNANS TABLOSU (FFP - Finansal Fair Play Takibi)
--    Her sezon için gelir/gider/bakiye kaydı ve FFP ceza durumu
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `kulup_finans` (
  `id`                 INT AUTO_INCREMENT PRIMARY KEY,
  `takim_id`           INT          NOT NULL,
  `takim_lig`          VARCHAR(20)  NOT NULL DEFAULT 'tr',
  `sezon_yil`          INT          NOT NULL DEFAULT 2025,
  `toplam_gelir`       BIGINT       NOT NULL DEFAULT 0
                                    COMMENT 'Bilet + Sponsorluk + Forma + Transfer Geliri (EUR)',
  `toplam_gider`       BIGINT       NOT NULL DEFAULT 0
                                    COMMENT 'Maaşlar + Transfer Giderleri (EUR)',
  `ffp_bakiye`         BIGINT       NOT NULL DEFAULT 0
                                    COMMENT 'toplam_gelir - toplam_gider',
  `ffp_ceza`           VARCHAR(30)  NOT NULL DEFAULT 'yok'
                                    COMMENT 'yok | transfer_yasagi | puan_silme',
  `puan_silme_miktari` INT          NOT NULL DEFAULT 0,
  `guncellendi`        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_takim_sezon` (`takim_id`, `takim_lig`, `sezon_yil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------
-- 2. SPONSORLUK TEKLİFLERİ VE AKTİF SPONSOR (Her Sezon Yeni Teklifler)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `kulup_sponsor` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `takim_id`            INT          NOT NULL,
  `takim_lig`           VARCHAR(20)  NOT NULL DEFAULT 'tr',
  `sezon_yil`           INT          NOT NULL DEFAULT 2025,
  `sponsor_adi`         VARCHAR(100) NOT NULL DEFAULT '',
  `teklif_tipi`         VARCHAR(20)  NOT NULL DEFAULT 'A'
                                     COMMENT 'A=Garantili Yüksek | B=Düşük Garanti + Büyük Bonus | C=Orta',
  `garantili_odeme`     BIGINT       NOT NULL DEFAULT 0
                                     COMMENT 'Sezon başında verilen garantili para (EUR)',
  `sampiyon_bonusu`     BIGINT       NOT NULL DEFAULT 0
                                     COMMENT 'Lig şampiyonluğu bonusu (EUR)',
  `ucl_sf_bonusu`       BIGINT       NOT NULL DEFAULT 0
                                     COMMENT 'UCL yarı final bonusu (EUR)',
  `aktif`               TINYINT(1)   NOT NULL DEFAULT 0
                                     COMMENT '1 = Bu sezon aktif sponsor',
  `garantili_odendi`    TINYINT(1)   NOT NULL DEFAULT 0,
  `sampiyon_odendi`     TINYINT(1)   NOT NULL DEFAULT 0,
  `ucl_sf_odendi`       TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_takim_sezon_tip` (`takim_id`, `takim_lig`, `sezon_yil`, `teklif_tipi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------
-- 3. STADYUM BİLET FİYATLANDIRMA (Stadyum Yönetimi)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `stadyum_ayar` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `takim_id`            INT          NOT NULL,
  `takim_lig`           VARCHAR(20)  NOT NULL DEFAULT 'tr',
  `stadyum_kapasitesi`  INT          NOT NULL DEFAULT 40000,
  `bilet_fiyati`        INT          NOT NULL DEFAULT 200
                                     COMMENT 'Bilet fiyatı (TL/EUR)',
  `beklenen_doluluk`    INT          NOT NULL DEFAULT 80
                                     COMMENT 'Hesaplanan doluluk yüzdesi',
  `atmosfer_carpani`    DECIMAL(4,2) NOT NULL DEFAULT 1.00
                                     COMMENT '0.70 - 1.30 arası ev sahibi avantaj çarpanı',
  `guncellendi`         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_takim_lig` (`takim_id`, `takim_lig`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------
-- 4. MENAJER KARİYER TABLOSU (Kovulma ve İşsiz Menajerlik)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `menajer_kariyer` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `takim_id`            INT          NOT NULL DEFAULT 0,
  `takim_lig`           VARCHAR(20)  NOT NULL DEFAULT 'tr',
  `takim_adi`           VARCHAR(100) NOT NULL DEFAULT '',
  `sezon_yil`           INT          NOT NULL DEFAULT 2025,
  `beklenti`            VARCHAR(50)  NOT NULL DEFAULT 'ilk_yari'
                                     COMMENT 'sampiyonluk | ilk_4 | ilk_yarı | kupayi_kazan | kupaliga_kal',
  `beklenti_min_sira`   INT          NOT NULL DEFAULT 5,
  `mevcut_sira`         INT          NOT NULL DEFAULT 10,
  `guven_puani`         INT          NOT NULL DEFAULT 70
                                     COMMENT '0-100 arası. 30 altı = kovulma riski',
  `durum`               VARCHAR(20)  NOT NULL DEFAULT 'aktif'
                                     COMMENT 'aktif | issiz | emekli',
  `kovulma_hafta`       INT          DEFAULT NULL,
  `kovulma_sebebi`      VARCHAR(200) DEFAULT NULL,
  `guncellendi`         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_takim_sezon` (`takim_id`, `takim_lig`, `sezon_yil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------
-- 5. İŞSİZ MENAJER İŞ TEKLİFLERİ (Unemployed Mode)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `is_teklifleri` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `menajer_kariyer_id`  INT          NOT NULL,
  `teklif_takim_adi`    VARCHAR(100) NOT NULL,
  `teklif_lig`          VARCHAR(20)  NOT NULL DEFAULT 'tr',
  `teklif_butce`        BIGINT       NOT NULL DEFAULT 5000000,
  `teklif_beklentisi`   VARCHAR(50)  NOT NULL DEFAULT 'kupaliga_kal',
  `teklif_suresi_sezon` INT          NOT NULL DEFAULT 2
                                     COMMENT 'Kaç sezonluk sözleşme',
  `durum`               VARCHAR(20)  NOT NULL DEFAULT 'beklemede'
                                     COMMENT 'beklemede | kabul | ret',
  `olusturuldu`         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`menajer_kariyer_id`) REFERENCES `menajer_kariyer`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------
-- 6. FORMA SATIŞ LOGU (Merchandising & Shirt Sales)
-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `forma_satis_log` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `takim_id`     INT          NOT NULL,
  `takim_lig`    VARCHAR(20)  NOT NULL DEFAULT 'tr',
  `sezon_yil`    INT          NOT NULL DEFAULT 2025,
  `hafta`        INT          NOT NULL DEFAULT 1,
  `gelir`        BIGINT       NOT NULL DEFAULT 0
                              COMMENT 'O haftaki forma/merchandising geliri (EUR)',
  `tetikleyen`   VARCHAR(100) DEFAULT NULL
                              COMMENT 'NULL=normal haftalık, oyuncu_adi=süperstar transferi',
  `aciklama`     VARCHAR(200) DEFAULT NULL,
  `created_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------
-- 7. TAKIMLAR TABLOLARINA FFP / STADYUM İÇİN SÜTUNLAR EKLE
-- ------------------------------------------------------------------------------

ALTER TABLE `takimlar`
    ADD COLUMN IF NOT EXISTS `stadyum_kapasitesi` INT          DEFAULT 40000,
    ADD COLUMN IF NOT EXISTS `bilet_fiyati`        INT          DEFAULT 200,
    ADD COLUMN IF NOT EXISTS `ffp_ceza`            VARCHAR(30)  DEFAULT 'yok',
    ADD COLUMN IF NOT EXISTS `transfer_yasagi`     TINYINT(1)   DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `guven_puani`         INT          DEFAULT 70;

ALTER TABLE `pl_takimlar`
    ADD COLUMN IF NOT EXISTS `stadyum_kapasitesi` INT          DEFAULT 55000,
    ADD COLUMN IF NOT EXISTS `bilet_fiyati`        INT          DEFAULT 60,
    ADD COLUMN IF NOT EXISTS `ffp_ceza`            VARCHAR(30)  DEFAULT 'yok',
    ADD COLUMN IF NOT EXISTS `transfer_yasagi`     TINYINT(1)   DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `guven_puani`         INT          DEFAULT 70;

ALTER TABLE `es_takimlar`
    ADD COLUMN IF NOT EXISTS `stadyum_kapasitesi` INT          DEFAULT 50000,
    ADD COLUMN IF NOT EXISTS `bilet_fiyati`        INT          DEFAULT 50,
    ADD COLUMN IF NOT EXISTS `ffp_ceza`            VARCHAR(30)  DEFAULT 'yok',
    ADD COLUMN IF NOT EXISTS `transfer_yasagi`     TINYINT(1)   DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `guven_puani`         INT          DEFAULT 70;

ALTER TABLE `de_takimlar`
    ADD COLUMN IF NOT EXISTS `stadyum_kapasitesi` INT          DEFAULT 50000,
    ADD COLUMN IF NOT EXISTS `bilet_fiyati`        INT          DEFAULT 40,
    ADD COLUMN IF NOT EXISTS `ffp_ceza`            VARCHAR(30)  DEFAULT 'yok',
    ADD COLUMN IF NOT EXISTS `transfer_yasagi`     TINYINT(1)   DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `guven_puani`         INT          DEFAULT 70;

ALTER TABLE `it_takimlar`
    ADD COLUMN IF NOT EXISTS `stadyum_kapasitesi` INT          DEFAULT 45000,
    ADD COLUMN IF NOT EXISTS `bilet_fiyati`        INT          DEFAULT 45,
    ADD COLUMN IF NOT EXISTS `ffp_ceza`            VARCHAR(30)  DEFAULT 'yok',
    ADD COLUMN IF NOT EXISTS `transfer_yasagi`     TINYINT(1)   DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `guven_puani`         INT          DEFAULT 70;

ALTER TABLE `fr_takimlar`
    ADD COLUMN IF NOT EXISTS `stadyum_kapasitesi` INT          DEFAULT 40000,
    ADD COLUMN IF NOT EXISTS `bilet_fiyati`        INT          DEFAULT 45,
    ADD COLUMN IF NOT EXISTS `ffp_ceza`            VARCHAR(30)  DEFAULT 'yok',
    ADD COLUMN IF NOT EXISTS `transfer_yasagi`     TINYINT(1)   DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `guven_puani`         INT          DEFAULT 70;

ALTER TABLE `pt_takimlar`
    ADD COLUMN IF NOT EXISTS `stadyum_kapasitesi` INT          DEFAULT 35000,
    ADD COLUMN IF NOT EXISTS `bilet_fiyati`        INT          DEFAULT 35,
    ADD COLUMN IF NOT EXISTS `ffp_ceza`            VARCHAR(30)  DEFAULT 'yok',
    ADD COLUMN IF NOT EXISTS `transfer_yasagi`     TINYINT(1)   DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `guven_puani`         INT          DEFAULT 70;

ALTER TABLE `cl_takimlar`
    ADD COLUMN IF NOT EXISTS `stadyum_kapasitesi` INT          DEFAULT 60000,
    ADD COLUMN IF NOT EXISTS `bilet_fiyati`        INT          DEFAULT 80,
    ADD COLUMN IF NOT EXISTS `ffp_ceza`            VARCHAR(30)  DEFAULT 'yok',
    ADD COLUMN IF NOT EXISTS `transfer_yasagi`     TINYINT(1)   DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `guven_puani`         INT          DEFAULT 70;
