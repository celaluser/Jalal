<?php

namespace App\Modules\Ai\Services;

use App\Modules\Ai\Contracts\AiProvider;
use App\Modules\Ai\Exceptions\AiException;
use App\Modules\Ai\Providers\AnthropicProvider;
use App\Modules\Ai\Providers\GeminiProvider;
use App\Modules\Ai\Providers\OpenAiProvider;
use App\Modules\Core\Services\SettingsService;

/**
 * Picks the AI provider the platform admin configured (Settings > AI). Keys live encrypted in the
 * settings table, never in code or .env.
 */
class AiManager
{
    public const DEFAULT_MODELS = ['openai' => 'gpt-4o-mini', 'anthropic' => 'claude-haiku-4-5-20251001', 'gemini' => 'gemini-2.0-flash'];

    public function __construct(private readonly SettingsService $settings) {}

    public function code(): ?string
    {
        $code = (string) $this->settings->get('ai.provider');

        return isset(self::DEFAULT_MODELS[$code]) ? $code : null;
    }

    /** Whether a provider is chosen and has a key: the switch behind every AI button. */
    public function configured(): bool
    {
        return $this->code() !== null && (string) $this->settings->get('ai.'.$this->code().'_key') !== '';
    }

    /** @throws AiException */
    public function provider(): AiProvider
    {
        if (! $this->configured()) {
            throw new AiException('not_configured');
        }

        $code = $this->code();
        $key = (string) $this->settings->get("ai.{$code}_key");
        $model = (string) ($this->settings->get("ai.{$code}_model") ?: self::DEFAULT_MODELS[$code]);

        return match ($code) {
            'openai' => new OpenAiProvider($key, $model),
            'anthropic' => new AnthropicProvider($key, $model),
            'gemini' => new GeminiProvider($key, $model),
        };
    }
}
