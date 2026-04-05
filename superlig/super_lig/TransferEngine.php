<?php
// ==============================================================================
// CORE TRANSFER ENGINE - LİGLER ARASI GLOBAL YAPAY ZEKA TRANSFER BORSASI
// ==============================================================================

class TransferEngine {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function ai_transfer_tetikle($hafta) {
        $deneme_sayisi = rand(1, 3);

        for ($i = 0; $i < $deneme_sayisi; $i++) {
            if (rand(1, 100) > 35) continue; 

            $ligler = ['', 'pl_'];
            $alici_prefix = $ligler[array_rand($ligler)];
            $satici_prefix = $ligler[array_rand($ligler)];

            $alici_takim = $this->pdo->query("SELECT id, takim_adi, butce FROM {$alici_prefix}takimlar WHERE butce > 10000000 ORDER BY RAND() LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if (!$alici_takim) continue;

            $max_harcama = $alici_takim['butce'] * 0.8;
            
            $hedef_oyuncu = $this->pdo->query("SELECT o.*, t.takim_adi as eski_takim_adi FROM {$satici_prefix}oyuncular o JOIN {$satici_prefix}takimlar t ON o.takim_id = t.id WHERE o.fiyat BETWEEN 2000000 AND $max_harcama AND o.ovr >= 70 ORDER BY RAND() LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            
            if ($hedef_oyuncu) {
                if ($alici_prefix === $satici_prefix && $hedef_oyuncu['takim_id'] == $alici_takim['id']) continue;

                $fiyat = $hedef_oyuncu['fiyat'];

                $this->pdo->exec("UPDATE {$alici_prefix}takimlar SET butce = butce - $fiyat WHERE id = {$alici_takim['id']}");
                $this->pdo->exec("UPDATE {$satici_prefix}takimlar SET butce = butce + $fiyat WHERE id = {$hedef_oyuncu['takim_id']}");

                if ($alici_prefix === $satici_prefix) {
                    $this->pdo->exec("UPDATE {$alici_prefix}oyuncular SET takim_id = {$alici_takim['id']}, ilk_11 = 0, yedek = 1 WHERE id = {$hedef_oyuncu['id']}");
                } else {
                    $stmt = $this->pdo->prepare("INSERT INTO {$alici_prefix}oyuncular (takim_id, isim, mevki, ovr, yas, form, fitness, moral, fiyat, ilk_11, yedek) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)");
                    $stmt->execute([
                        $alici_takim['id'], 
                        $hedef_oyuncu['isim'], 
                        $hedef_oyuncu['mevki'], 
                        $hedef_oyuncu['ovr'], 
                        $hedef_oyuncu['yas'] ?? 24, 
                        6, 100, 80, $fiyat
                    ]);
                    $this->pdo->exec("DELETE FROM {$satici_prefix}oyuncular WHERE id = {$hedef_oyuncu['id']}");
                }

                $fiyat_milyon = number_format($fiyat / 1000000, 1);
                $haber_metni = "🌍 GLOBAL TRANSFER: {$alici_takim['takim_adi']}, {$hedef_oyuncu['eski_takim_adi']} forması giyen {$hedef_oyuncu['isim']}'i €{$fiyat_milyon}M karşılığında transfer etti!";
                
                try { $this->pdo->exec("INSERT INTO haberler (hafta, metin, tip) VALUES ($hafta, '$haber_metni', 'transfer')"); } catch(Throwable $e){}
                try { $this->pdo->exec("INSERT INTO pl_haberler (hafta, metin, tip) VALUES ($hafta, '$haber_metni', 'transfer')"); } catch(Throwable $e){}
            }
        }
    }
}