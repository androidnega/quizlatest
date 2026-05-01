<?php

namespace App\Services;

/**
 * AI credentials and model name sourced exclusively from system settings (when AI features call into LLMs).
 */
class AiIntegrationSettings
{
    public function __construct(
        private readonly SystemSettingsService $systemSettings,
    ) {}

    public function apiKey(): ?string
    {
        return $this->systemSettings->get('ai_api_key');
    }

    public function modelName(): ?string
    {
        return $this->systemSettings->get('ai_model_name');
    }

    /**
     * @return array{api_key: ?string, model: ?string}
     */
    public function clientConfig(): array
    {
        return [
            'api_key' => $this->apiKey(),
            'model' => $this->modelName(),
        ];
    }
}
