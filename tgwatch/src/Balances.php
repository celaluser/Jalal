<?php
declare(strict_types=1);

namespace TgWatch;

/** Sağlayıcı bakiyeleri: her sağlayıcının API'sine action=balance (bot /balans ve Chrome eklentisi kullanır). */
final class Balances
{
    /**
     * @return array{items:list<array{id:int,name:string,balance:?float,currency:string,error:string}>,totals:array<string,float>,at:int}
     */
    public static function check(Db $db): array
    {
        $items = [];
        $totals = [];
        foreach ($db->all("SELECT * FROM sources WHERE kind = 'provider' ORDER BY name COLLATE NOCASE") as $p) {
            $it = ['id' => (int)$p['id'], 'name' => (string)$p['name'], 'balance' => null, 'currency' => '', 'error' => ''];
            try {
                $b = SmmApi::balance((string)$p['api_url'], (string)$p['api_key']);
                $it['balance'] = $b['balance'];
                $it['currency'] = $b['currency'];
                $totals[$b['currency']] = ($totals[$b['currency']] ?? 0) + $b['balance'];
            } catch (\Throwable $e) {
                $it['error'] = mb_substr($e->getMessage(), 0, 120);
                $db->log('warn', 'bot', 'Bakiye (' . $p['name'] . '): ' . $e->getMessage());
            }
            $items[] = $it;
        }
        ksort($totals);
        return ['items' => $items, 'totals' => $totals, 'at' => time()];
    }

    public static function money(float $v): string
    {
        return number_format($v, $v != 0 && abs($v) < 1 ? 4 : 2, '.', ' ');
    }
}
