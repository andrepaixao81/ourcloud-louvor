<?php

namespace App\Models;

class WhatsappSetting extends BaseModel
{
    public const PROVIDER_META = 'meta';
    public const PROVIDER_EVOLUTION = 'evolution';

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'access_token' => 'encrypted',
            'evolution_api_key' => 'encrypted',
            'evolution_delay_ms' => 'integer',
            'evolution_link_preview' => 'boolean',
        ];
    }

    public function selectedProvider(): string
    {
        return in_array($this->provider, [self::PROVIDER_META, self::PROVIDER_EVOLUTION], true)
            ? (string) $this->provider
            : self::PROVIDER_META;
    }

    public function isConfigured(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        return match ($this->selectedProvider()) {
            self::PROVIDER_EVOLUTION => filled($this->evolution_base_url)
                && filled($this->evolution_instance)
                && filled($this->evolution_api_key),
            default => filled($this->phone_number_id) && filled($this->access_token),
        };
    }
}
