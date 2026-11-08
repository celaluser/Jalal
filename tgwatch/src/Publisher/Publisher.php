<?php
declare(strict_types=1);

namespace TgWatch\Publisher;

interface Publisher
{
    /**
     * Onaylanmış işi yayınlar.
     * status: 'published' (sitede yayınlandı, varsayılan) ya da 'approved' (onaylandı, yayın bekliyor).
     * @return array{ok:bool, message:string, status?:string}
     */
    public function publish(array $job): array;

    public static function name(): string;
}
