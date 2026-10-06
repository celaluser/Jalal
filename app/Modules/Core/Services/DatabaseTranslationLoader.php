<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Translation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\FileLoader;

/**
 * File based translations with panel-edited overrides from the `translations` table on top.
 * JSON strings use the group "*".
 */
class DatabaseTranslationLoader extends FileLoader
{
    private ?bool $tableExists = null;

    public function load($locale, $group, $namespace = null): array
    {
        $lines = parent::load($locale, $group, $namespace);

        if ($namespace !== null && $namespace !== '*') {
            return $lines;
        }

        if ($this->tableExists === null) {
            try {
                $this->tableExists = Schema::hasTable('translations');
            } catch (\Throwable) {
                $this->tableExists = false;
            }
        }

        if (! $this->tableExists) {
            return $lines;
        }

        $overrides = Translation::query()
            ->where('locale', $locale)
            ->where('group', $group)
            ->pluck('value', 'key')
            ->all();

        foreach ($overrides as $key => $value) {
            if ($group === '*') {
                $lines[$key] = $value;
            } else {
                data_set($lines, $key, $value);
            }
        }

        return $lines;
    }
}
