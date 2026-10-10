<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Marketing\Models\Review;

/** Net Promoter Score of the current restaurant: % promoters (9-10) minus % detractors (0-6). */
class Nps
{
    /** @return array{score: ?int, count: int, promoters: int, passives: int, detractors: int} */
    public function summary(): array
    {
        $scores = Review::whereNotNull('nps')->pluck('nps');
        $count = $scores->count();
        $p = $scores->filter(fn ($n) => $n >= 9)->count();
        $d = $scores->filter(fn ($n) => $n <= 6)->count();

        return ['score' => $count ? (int) round(($p - $d) / $count * 100) : null, 'count' => $count, 'promoters' => $p, 'passives' => $count - $p - $d, 'detractors' => $d];
    }
}
