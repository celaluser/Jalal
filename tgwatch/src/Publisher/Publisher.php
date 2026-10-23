<?php
declare(strict_types=1);

namespace TgWatch\Publisher;

interface Publisher
{
    /**
     * Onaylanmış işi yayınlar.
     * @return array{ok:bool, message:string}
     */
    public function publish(array $job): array;

    public static function name(): string;
}
