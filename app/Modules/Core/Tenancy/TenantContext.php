<?php

namespace App\Modules\Core\Tenancy;

use App\Modules\Tenancy\Models\Restaurant;
use Closure;

/**
 * Holds the restaurant ("tenant") the current request, job or console task acts for.
 *
 * Bound as a scoped singleton so it is reset between requests and queue jobs.
 */
class TenantContext
{
    private ?Restaurant $restaurant = null;

    private int $bypassDepth = 0;

    public function set(?Restaurant $restaurant): void
    {
        $this->restaurant = $restaurant;
    }

    public function get(): ?Restaurant
    {
        return $this->restaurant;
    }

    public function id(): ?int
    {
        return $this->restaurant?->getKey();
    }

    public function has(): bool
    {
        return $this->restaurant !== null;
    }

    public function forget(): void
    {
        $this->restaurant = null;
    }

    /**
     * Run a callback while acting as the given restaurant, then restore the previous one.
     */
    public function runAs(?Restaurant $restaurant, Closure $callback): mixed
    {
        $previous = $this->restaurant;
        $this->restaurant = $restaurant;

        try {
            return $callback();
        } finally {
            $this->restaurant = $previous;
        }
    }

    /**
     * Run a callback with tenant isolation explicitly disabled (platform-level work only,
     * e.g. super admin reports). Every use of this method should be deliberate.
     */
    public function bypass(Closure $callback): mixed
    {
        $this->bypassDepth++;

        try {
            return $callback();
        } finally {
            $this->bypassDepth--;
        }
    }

    public function isBypassed(): bool
    {
        return $this->bypassDepth > 0;
    }
}
