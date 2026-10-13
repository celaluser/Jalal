<?php

namespace App\Modules\Hub\Support;

/** Takes the plain order format as it is. */
final class GenericAdapter implements ChannelAdapter
{
    public function kind(): string
    {
        return 'generic';
    }

    public function label(): string
    {
        return 'Generic (Zapier, Make, your own bridge)';
    }

    public function normalize(array $raw): array
    {
        return $raw;
    }
}
