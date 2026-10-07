<?php

namespace App\Modules\Tenancy\Contracts;

/** Looks up DNS records. A contract so domain verification can be tested without the network. */
interface DnsResolver
{
    /** @return list<string> TXT record values of the host (long records already joined) */
    public function txt(string $host): array;

    /** @return list<string> targets of the host's CNAME records, lower-case, without the trailing dot */
    public function cname(string $host): array;
}
