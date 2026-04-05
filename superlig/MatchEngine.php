<?php
// ==============================================================================
// CORE MATCH ENGINE V2.1 - DİNAMİK GELİŞİM, YORGUNLUK VE KENDİ KENDİNE YETEN AI
// Tüm ligler (tr, pl, cl, es, de, fr, it, pt) için ortaktır.
// ==============================================================================

class MatchEngine {
    private $pdo;
    private $prefix;

    public function __construct($pdo, $prefix = '') {
        $this->pdo = $pdo;
        $this->prefix = $prefix;
    }

    // --- 1. xG (BEKLENEN GOL) TABANLI GERÇEKÇİ SKOR HESAPLAYICI ---
    public function gercekci_skor_hesapla($ev_id, $dep_id, $mac_bilgisi = null) {
        $tbl_oyuncular = $this->prefix . 'oyuncular';
        $tbl_takimlar = $this->prefix . 'takimlar';
        
        // 1. Sahadaki ilk 11'in form ve yorgunluğa göre anlık ortalamasını al
        $ev_ort = $this->pdo->query("SELECT AVG(ovr + (form-5) + ((fitness-100)*0.1)) FROM $tbl_oyuncular WHERE takim_id={$ev_id} AND ilk_11=1")->fetchColumn();
        $dep_ort = $this->pdo->query("SELECT AVG(ovr + (form-5) + ((fitness-100)*0.1)) FROM $tbl_oyuncular WHERE takim_id={$dep_id} AND ilk_11=1")->fetchColumn();
        
        // 2. Takımların veritabanındaki sabit hücum/savunma güçlerini al (Hata vermemesi için motor kendi bulur)
        $ev_takim = $this->pdo->query("SELECT hucum, savunma FROM $tbl_takimlar WHERE id={$ev_id}")->fetch(PDO::FETCH_ASSOC);
        $dep_takim = $this->pdo->query("SELECT hucum, savunma FROM $tbl_takimlar WHERE id={$dep_id}")->fetch(PDO::FETCH_ASSOC);
        
        // 3. Nihai Gücü Hesapla
        $ev_guc = ($ev_ort ?: (($ev_takim['hucum'] + $ev_takim['savunma']) / 2)) + 3; // Ev sahibi taraftar avantajı (+3)
        $dep_guc = ($dep_ort ?: (($dep_takim['hucum'] + $dep_takim['savunma']) / 2));

        // 4. xG Algoritması
        $guc_farki = $ev_guc - $dep_guc;
        $ev_beklenen = max(0.1, 1.4 + ($guc_farki * 0.08));
        $dep_beklenen = max(0.1, 1.2 - ($guc_farki * 0.08));

        $ev_skor = 0; $dep_skor = 0;
        for($i=0; $i<6; $i++) { if((rand(0,100)/100) < ($ev_beklenen / 4.5)) $ev_skor++; }
        for($i=0; $i<6; $i++) { if((rand(0,100)/100) < ($dep_beklenen / 4.5)) $dep_skor++; }
        
        // Sürpriz/Şans Faktörü
        if(rand(1, 100) <= 12) { 
            if($ev_guc < $dep_guc) $ev_skor += rand(1, 2);
            else $dep_skor += rand(1, 2);
        }

        // MAÇ SONUCU BELLİ OLDU! ŞİMDİ OYUNCULARI GELİŞTİR/YOR/DÜŞÜR
        $this->dinamik_mac_sonu_etkisi($ev_id, $dep_id, $ev_skor, $dep_skor);

        return ['ev' => $ev_skor, 'dep' => $dep_skor];
    }

    // --- 2. DİNAMİK GELİŞİM, YAŞLANMA VE FİTNESS ---
    private function dinamik_mac_sonu_etkisi($ev_id, $dep_id, $ev_skor, $dep_skor) {
        $tbl = $this->prefix . 'oyuncular';
        $takimlar = [
            ['id' => $ev_id, 'kazandi' => ($ev_skor > $dep_skor), 'skor' => $ev_skor],
            ['id' => $dep_id, 'kazandi' => ($dep_skor > $ev_skor), 'skor' => $dep_skor]
        ];

        foreach($takimlar as $t) {
            $oyuncular = $this->pdo->query("SELECT * FROM $tbl WHERE takim_id = {$t['id']} AND ilk_11 = 1")->fetchAll(PDO::FETCH_ASSOC);
            
            foreach($oyuncular as $o) {
                $yorgunluk = ($o['yas'] >= 32) ? rand(15, 22) : rand(8, 15);
                $yeni_fitness = max(30, $o['fitness'] - $yorgunluk);
                
                $yeni_form = $o['form'];
                if($t['kazandi']) { $yeni_form = min(9, $yeni_form + rand(0, 1)); }
                else { $yeni_form = max(1, $yeni_form - rand(0, 1)); }

                $yeni_ovr = $o['ovr'];
                $yeni_fiyat = $o['fiyat'];

                // Gençlerin Gelişimi
                if($o['yas'] <= 23 && $t['skor'] >= 0) {
                    if(rand(1, 100) <= 8) { 
                        $yeni_ovr++;
                        $yeni_fiyat = intval($yeni_fiyat * 1.15); 
                    }
                }
                // Yaşlıların Düşüşü
                elseif($o['yas'] >= 32) {
                    if(rand(1, 100) <= 4) { 
                        $yeni_ovr--;
                        $yeni_fiyat = intval($yeni_fiyat * 0.85); 
                    }
                }

                $this->pdo->exec("UPDATE $tbl SET fitness=$yeni_fitness, form=$yeni_form, ovr=$yeni_ovr, fiyat=$yeni_fiyat WHERE id={$o['id']}");
            }
            // Yedekleri dinlendir
            $this->pdo->exec("UPDATE $tbl SET fitness = LEAST(100, fitness + 20) WHERE takim_id = {$t['id']} AND ilk_11 = 0");
        }
    }

    // --- 3. AKILLI MAÇ OLAYI (GOL, KART, ASİST) ÜRETİCİSİ ---
    public function mac_olay_uret($takim_id, $skor) {
        $tbl_oyuncular = $this->prefix . 'oyuncular';

        $oyuncular = $this->pdo->query("SELECT isim, mevki FROM $tbl_oyuncular WHERE takim_id=$takim_id AND ilk_11=1")->fetchAll(PDO::FETCH_ASSOC);
        if(!$oyuncular || count($oyuncular) == 0) { $oyuncular = $this->pdo->query("SELECT isim, mevki FROM $tbl_oyuncular WHERE takim_id=$takim_id")->fetchAll(PDO::FETCH_ASSOC); }
        if(!$oyuncular || count($oyuncular) == 0) { $oyuncular = [['isim' => 'Altyapı (AI)', 'mevki' => 'F']]; }
        
        $golculer = [];
        foreach($oyuncular as $o) {
            if($o['mevki'] == 'F') { $golculer[] = $o; $golculer[] = $o; $golculer[] = $o; } 
            elseif($o['mevki'] == 'OS') { $golculer[] = $o; $golculer[] = $o; } 
            else { $golculer[] = $o; } 
        }
        
        $olaylar = []; $kartlar = []; $sakatlar = [];
        
        for($i=0; $i<$skor; $i++) {
            $gol_atan = $golculer[array_rand($golculer)]['isim'];
            $asist_yapan = (rand(0,10) > 3) ? $oyuncular[array_rand($oyuncular)]['isim'] : '-';
            if($gol_atan == $asist_yapan) $asist_yapan = '-'; 
            $olaylar[] = ['tip' => 'gol', 'dakika' => rand(2, 95), 'oyuncu' => $gol_atan, 'asist' => $asist_yapan];
        }
        
        $kart_sayisi = rand(0, 3);
        for($i=0; $i<$kart_sayisi; $i++) {
            $kart_goren = $oyuncular[array_rand($oyuncular)]['isim'];
            $tip = (rand(0, 10) > 8) ? 'Kırmızı' : 'Sarı';
            $kartlar[] = ['tip' => 'kart', 'detay' => $tip, 'dakika' => rand(5, 90), 'oyuncu' => $kart_goren];
        }
        
        usort($olaylar, function($a, $b) { return $a['dakika'] <=> $b['dakika']; });
        usort($kartlar, function($a, $b) { return $a['dakika'] <=> $b['dakika']; });
        
        return [
            'olaylar' => json_encode($olaylar, JSON_UNESCAPED_UNICODE), 
            'kartlar' => json_encode($kartlar, JSON_UNESCAPED_UNICODE), 
            'sakatlar' => json_encode($sakatlar, JSON_UNESCAPED_UNICODE)
        ];
    }
}
?>