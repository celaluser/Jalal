<?php
declare(strict_types=1);

namespace TgWatch\Web;

use TgWatch\Dictionary;
use TgWatch\Learner;

/** Öğrenme laboratuvarı sayfası (Admin içinde kullanılır). */
trait LearnPages
{
    private const LEARN_KINDS = ['opt' => 'Ad kalıbı', 'phrase' => 'Sözlük', 'rule' => 'Sayılı kalıp', 'remove' => 'Silinecek ifade'];

    private function learnPage(): void
    {
        $L = new Learner($this->db);
        $rep = $this->db->getJson('learn_report');
        $decided = $L->decided();
        $nPairs = count($L->pairs());

        $h = '<div class="panel panel-pad stack"><p class="muted">Çeviri bankasındaki ve eşleşen servislerdeki ad çiftlerinden (sağlayıcı adı ↔ senin adın) çeviri mantığını çıkarır: neyi çevirmişsin, neyi silmişsin, neyi eklemişsin. '
            . 'Önerileri sen onaylamadan hiçbir şey değişmez. Öğrenmeye yalnızca senin kararların girer: otomatik eşleşme, onayladığın eşleşme ve elle eklediklerin.</p>'
            . '<dl class="kv"><dt>Öğrenmeye uygun ad çifti</dt><dd>' . $nPairs . '</dd>'
            . ($rep ? '<dt>Son analiz</dt><dd>' . \TgWatch\Web\Ui::ago((int)$rep['at']) . '</dd>' : '') . '</dl>'
            . '<div class="row">' . $this->ui->action('learn_run', $rep ? 'Yeniden analiz et' : 'Analizi çalıştır', [], 'primary', 'play') . '</div></div>';

        if ($nPairs < 20 && !$rep) {
            $h .= Ui::empty('Henüz yeterli çift yok', 'Çeviri bankası sayfasından kendi panelini ve sağlayıcıyı ekleyip eşleştirmeyi çalıştır. En az 20–30 çift gerekir; 200+ olunca sonuçlar güvenilir olur.');
            $this->render('Öğrenme', $h, ['active' => 'learn', 'sub' => 'Çeviri mantığını senin geçmiş kararlarından öğrenir.']);
            return;
        }
        if (!$rep) {
            $this->render('Öğrenme', $h, ['active' => 'learn', 'sub' => 'Çeviri mantığını senin geçmiş kararlarından öğrenir.']);
            return;
        }

        // Doğruluk
        $ev = $rep['eval'] ?? ['ready' => false];
        $h .= '<h2>Doğruluk</h2>';
        if (!empty($ev['ready'])) {
            $pb = (int)round($ev['base'] * 100 / $ev['test']);
            $pa = (int)round($ev['after'] * 100 / $ev['test']);
            $h .= '<section class="strip"><div><div class="k">Şu anki kurallarla</div><div class="v">%' . $pb . '</div></div>'
                . '<div><div class="k">Önerilerin hepsi uygulanırsa</div><div class="v">%' . $pa . '</div></div>'
                . '<div><div class="k">Ölçülen ad sayısı</div><div class="v">' . (int)$ev['test'] . '</div></div></section>'
                . '<p class="muted">Çiftlerin yaklaşık beşte biri saklanır, öneriler kalanından üretilir ve saklananlar çevrilip senin adınla birebir karşılaştırılır (büyük/küçük harf ve boşluk farkı sayılmaz). Banka kullanılmaz, yalnızca kurallar ve sözlük ölçülür.</p>';
            if (!empty($ev['miss'])) {
                $h .= '<details class="panel panel-pad"><summary>Önerilerden sonra da tutmayan örnekler (' . count($ev['miss']) . ')</summary><div class="table-wrap"><table><thead><tr><th>Kaynak ad</th><th>Senin adın</th><th>Çıkan</th></tr></thead><tbody>';
                foreach ($ev['miss'] as $m) {
                    $h .= '<tr><td>' . Ui::e($m['en']) . '</td><td>' . Ui::e($m['want']) . '</td><td>' . Ui::e($m['got']) . '</td></tr>';
                }
                $h .= '</tbody></table></div></details>';
            }
        } else {
            $h .= '<p class="muted">Ölçüm için en az 25 kadar çift gerekir (şu an test için ' . (int)($ev['test'] ?? 0) . ' ad ayrılabildi).</p>';
        }

        // Öneriler
        $open = array_values(array_filter($rep['suggestions'] ?? [], static fn($s) => !isset($decided[$s['id']])));
        $h .= '<h2>Öneriler</h2>';
        if ($open) {
            $h .= '<p class="muted">En güçlü kanıttan başlar. “Uygula” sözlüğe ya da ad kalıbına işler ve açık işlerin çevirisini yeniler.</p><div class="table-wrap"><table><thead><tr><th>Öneri</th><th>Kanıt</th><th></th></tr></thead><tbody>';
            foreach (array_slice($open, 0, 60) as $s) {
                $h .= '<tr><td><span class="badge">' . Ui::e(self::LEARN_KINDS[$s['kind']] ?? $s['kind']) . '</span> <b>' . Ui::e($s['title']) . '</b>'
                    . ($s['ex'] !== '' ? '<div class="src">Örnek: ' . Ui::e($s['ex']) . '</div>' : '') . '</td>'
                    . '<td class="muted">' . Ui::e($s['detail']) . '</td><td class="tight"><div class="row">'
                    . $this->ui->action('learn_apply', 'Uygula', ['sid' => $s['id']], 'quiet sm', 'check')
                    . $this->ui->action('learn_reject', 'Gerekmez', ['sid' => $s['id']], 'ghost sm', 'x') . '</div></td></tr>';
            }
            $h .= '</tbody></table></div>';
            if (count($open) > 1) {
                $h .= '<div class="row gap-top">' . $this->ui->action('learn_apply_all', 'Hepsini uygula (' . count($open) . ')', [], 'quiet', 'check', 'Listedeki tüm öneriler sözlüğe ve ad kalıbına işlensin mi?') . '</div>';
            }
        } else {
            $h .= '<p class="muted">Bekleyen öneri yok. Yeni çift geldikçe analizi yeniden çalıştırabilirsin.</p>';
        }

        // Neler görüldü
        $st = $rep['stats'] ?? [];
        $h .= '<h2>Neler gördüm</h2><div class="panel panel-pad stack"><dl class="kv"><dt>İncelenen ad çifti</dt><dd>' . (int)($rep['pairs'] ?? 0) . '</dd>'
            . '<dt>İncelenen parça</dt><dd>' . (int)($st['segments'] ?? 0) . ' <span class="muted">(' . (int)($st['same'] ?? 0) . ' tanesinde mevcut kurallar zaten seninle aynı çeviriyi veriyor)</span></dd>';
        $tl = [];
        foreach (\TgWatch\NameTemplate::TYPES as $t => [$label]) {
            if (!empty($st['types'][$t]['n'])) {
                $n = (int)$st['types'][$t]['n'];
                $d = (int)($st['types'][$t]['drop'] ?? 0);
                $tl[] = Ui::e($label) . ': ' . $n . ' parçadan ' . $d . ' tanesi silinmiş (%' . round($d * 100 / $n) . ')';
            }
        }
        if ($tl) {
            $h .= '<dt>Parça türüne göre silme</dt><dd>' . implode('<br>', $tl) . '</dd>';
        }
        if (!empty($st['added'])) {
            $a = [];
            foreach ($st['added'] as $k => $n) {
                $a[] = Ui::e((string)$k) . ' (' . (int)$n . ')';
            }
            $h .= '<dt>Senin eklediğin parçalar</dt><dd>' . implode(', ', $a) . '<div class="src">Kaynak adda olmayıp senin adında olan parçalar. Otomatik kural yapılmaz, bilgi olarak gösterilir.</div></dd>';
        }
        $h .= '</dl></div>';

        $this->render('Öğrenme', $h, ['active' => 'learn', 'sub' => 'Çeviri mantığını senin geçmiş kararlarından öğrenir.']);
    }

    private function postLearn(string $p): bool
    {
        if (!str_starts_with($p, 'learn_')) {
            return false;
        }
        $L = new Learner($this->db);
        switch ($p) {
            case 'learn_run':
                $r = $L->run();
                $this->db->setJson('learn_decided', []);
                $this->say('ok', 'Analiz bitti: ' . $r['pairs'] . ' çiftten ' . count($r['suggestions']) . ' öneri çıktı.');
                break;
            case 'learn_apply':
            case 'learn_reject':
                $s = $this->learnFind((string)($_POST['sid'] ?? ''));
                if (!$s) {
                    throw new \InvalidArgumentException('Öneri bulunamadı. Analizi yeniden çalıştır.');
                }
                if ($p === 'learn_apply') {
                    Dictionary::save($this->db, Learner::applyTo(Dictionary::load($this->db), $s));
                    $L->decide($s['id'], 'applied');
                    $n = $this->refreshOpenJobs();
                    $this->say('ok', 'Uygulandı.' . ($n ? " $n açık iş yeniden çevrildi." : ''));
                } else {
                    $L->decide($s['id'], 'rejected');
                    $this->say('ok', 'Öneri kapatıldı.');
                }
                break;
            case 'learn_apply_all':
                $rep = $this->db->getJson('learn_report');
                $dict = Dictionary::load($this->db);
                $done = $L->decided();
                $n = 0;
                foreach ($rep['suggestions'] ?? [] as $s) {
                    if (!isset($done[$s['id']])) {
                        $dict = Learner::applyTo($dict, $s);
                        $L->decide($s['id'], 'applied');
                        $n++;
                    }
                }
                Dictionary::save($this->db, $dict);
                $jobs = $this->refreshOpenJobs();
                $this->say('ok', "$n öneri uygulandı." . ($jobs ? " $jobs açık iş yeniden çevrildi." : ''));
                break;
            default:
                return false;
        }
        $this->redirect('learn');
        return true;
    }

    private function learnFind(string $id): ?array
    {
        foreach ($this->db->getJson('learn_report')['suggestions'] ?? [] as $s) {
            if ($s['id'] === $id) {
                return $s;
            }
        }
        return null;
    }
}
