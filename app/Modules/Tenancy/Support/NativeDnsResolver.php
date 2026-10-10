<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Contracts\DnsResolver;

/** DNS lookups through PHP's resolver; a failed lookup is simply "no records". */
class NativeDnsResolver implements DnsResolver
{
    public function txt(string $host): array
    {
        return array_values(array_map(fn ($r) => (string) ($r['txt'] ?? ''), $this->records($host, DNS_TXT)));
    }

    public function cname(string $host): array
    {
        return array_values(array_map(fn ($r) => strtolower(rtrim((string) ($r['target'] ?? ''), '.')), $this->records($host, DNS_CNAME)));
    }

    /** @return list<array<string, mixed>> */
    private function records(string $host, int $type): array
    {
        $records = @dns_get_record($host, $type);

        return is_array($records) ? $records : [];
    }
}
