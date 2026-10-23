<?php
declare(strict_types=1);

namespace TgWatch\Publisher;

use TgWatch\Telegram;

/**
 * Perfect Panel servis API'si olmadığı için: onaydan sonra panelde elle yapılacak
 * "import paketi"ni (ID → Azerice ad) bota mesaj + .txt dosyası olarak gönderir.
 */
final class ManualPublisher implements Publisher
{
    public function __construct(private Telegram $tg, private int $ownerId)
    {
    }

    public static function name(): string
    {
        return 'manual';
    }

    public static function package(array $job): string
    {
        $lines = [];
        $lines[] = "# tgwatch iş #{$job['id']} — @{$job['channel']} / {$job['msg_id']}";
        $cats = [];
        foreach ($job['items'] as $it) {
            if (!empty($it['category_az'])) {
                $cats[$it['category_az']] = true;
            }
        }
        if ($cats) {
            $lines[] = '# Kategori: ' . implode(' / ', array_keys($cats));
        }
        $lines[] = '# ID<TAB>Ad<TAB>Fiyat(sağlayıcı)';
        foreach ($job['items'] as $it) {
            $lines[] = $it['id'] . "\t" . $it['az'] . "\t" . ($it['price'] ?? '');
        }
        return implode("\n", $lines) . "\n";
    }

    public function publish(array $job): array
    {
        $ids = implode(',', array_map(static fn($i) => $i['id'], $job['items']));
        $body = "📦 <b>Import paketi — iş #{$job['id']}</b>\n"
            . "Panel → Services → Import services → sağlayıcıyı seç → bu ID'leri işaretle:\n"
            . '<code>' . Telegram::esc($ids) . "</code>\n\n";
        $list = '';
        foreach ($job['items'] as $it) {
            $list .= $it['id'] . ' → ' . $it['az'] . "\n";
        }
        $msg = $body . '<pre>' . Telegram::esc($list) . '</pre>';
        if (mb_strlen($msg) <= 4000) {
            $this->tg->sendMessage($this->ownerId, $msg);
        } else {
            $this->tg->sendMessage($this->ownerId, $body . 'Liste uzun, dosyaya bak.');
        }
        $this->tg->sendDocument($this->ownerId, "import_{$job['id']}.txt", self::package($job), "İş #{$job['id']} — ID / ad");
        return ['ok' => true, 'message' => 'Import paketi bota gönderildi (elle yayın)'];
    }
}
