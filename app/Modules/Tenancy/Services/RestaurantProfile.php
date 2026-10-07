<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Services\FileUploader;
use App\Modules\Tenancy\Models\Restaurant;
use DateTimeZone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Profile and branding of a restaurant. Shared by the setup wizard and the settings screen so both
 * validate and save in exactly the same way.
 */
class RestaurantProfile
{
    public function __construct(private readonly FileUploader $uploader) {}

    /** @return array<string, array<int, mixed>> */
    public function profileRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s.]*$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'currency_code' => ['nullable', Rule::in($this->currencies()->keys()->all())],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'locale' => ['required', Rule::in($this->languages()->keys()->all())],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public function brandingRules(): array
    {
        return [
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', 'max:4096'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    /** @param array<string, mixed> $data validated with profileRules() */
    public function saveProfile(Restaurant $restaurant, array $data): void
    {
        $restaurant->update(array_intersect_key($data, $this->profileRules()));
    }

    /** @param array<string, mixed> $data validated with brandingRules() */
    public function saveBranding(Restaurant $restaurant, array $data, ?UploadedFile $logo): void
    {
        $branding = $restaurant->branding ?? [];
        $branding['color'] = strtolower($data['color']);
        $attributes = ['branding' => $branding];

        if ($logo) {
            $attributes['logo_media_id'] = $this->uploader->store($logo, 'logos', maxDimension: 600)->id;
        } elseif (! empty($data['remove_logo'])) {
            $attributes['logo_media_id'] = null;
        }

        $restaurant->update($attributes);
    }

    /** @return Collection<string, string> code => "CODE — name" */
    public function currencies()
    {
        return Currency::where('is_active', true)->orderBy('code')->get()->mapWithKeys(fn ($c) => [$c->code => $c->code.' — '.$c->name]);
    }

    /** @return Collection<string, string> code => native name */
    public function languages()
    {
        return Language::active()->get()->mapWithKeys(fn ($l) => [$l->code => $l->native_name ?: $l->name]);
    }

    /** @return list<string> */
    public function timezones(): array
    {
        return DateTimeZone::listIdentifiers();
    }
}
