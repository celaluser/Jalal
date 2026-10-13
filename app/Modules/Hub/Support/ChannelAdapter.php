<?php

namespace App\Modules\Hub\Support;

/**
 * Turns what another platform sends into the hub's plain order format. The built-in "generic" adapter takes the format
 * as it is (enough for Zapier, Make and your own bridge). A connector for a specific platform implements this and
 * registers itself from a service provider or an add-on:  ChannelRegistry::register(new MyPlatformAdapter);
 *
 * The plain format:
 *   external_id   string, the other platform's order number (makes a repeated delivery harmless)
 *   type          delivery | takeaway | dine_in | curbside | room_service
 *   customer_name, customer_phone, customer_email, delivery_address, note
 *   paid          true when the platform already collected the money
 *   lines         [{ product_id | name, qty, note?, variant_id?, options? }]  (prices always come from your menu)
 */
interface ChannelAdapter
{
    /** Short code stored with the channel, for example "generic". */
    public function kind(): string;

    /** Name shown when choosing the kind of channel. */
    public function label(): string;

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed> the plain format
     */
    public function normalize(array $raw): array;
}
