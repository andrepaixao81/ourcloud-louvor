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


    public function batchSpacingSeconds(): int
    {
        if ($this->selectedProvider() !== self::PROVIDER_EVOLUTION) {
            return 1;
        }

        // Evolution pode encerrar a conexão quando recebe muitos POSTs ao mesmo
        // tempo. O delay configurado continua indo no payload e também orienta o
        // espaçamento do lote, com piso seguro de 2 segundos entre requisições.
        return max(2, (int) ceil(max(0, (int) ($this->evolution_delay_ms ?? 500)) / 1000));
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
