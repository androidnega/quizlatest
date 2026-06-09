<?php

namespace App\Services;

/**
 * Single source of truth for the AI provider credentials + model the whole
 * system uses (examiner question generation, lecturer grading assist, student
 * practice quizzes, study summaries). Every AI caller should resolve its
 * key/model/provider through here so there's exactly ONE place an admin
 * configures the integration.
 *
 * Backwards compatibility: older installs may have saved their key under
 * `deepseek_api_key` / `deepseek_model` (the old "Practice" section). Reads
 * fall back to those legacy keys so existing setups keep working without
 * forcing an admin to re-enter the credential.
 */
class AiIntegrationSettings
{
    public const string DEFAULT_PROVIDER = 'deepseek';

    public const string DEFAULT_MODEL = 'deepseek-chat';

    /**
     * Canonical setting keys written by the admin UI.
     */
    public const string CANONICAL_KEY = 'ai_api_key';

    public const string CANONICAL_MODEL = 'ai_model_name';

    public const string CANONICAL_PROVIDER = 'ai_provider';

    /**
     * Legacy setting keys retained for read-only fallback so previous
     * super-admins do not have to re-enter their DeepSeek key.
     */
    public const string LEGACY_KEY = 'deepseek_api_key';

    public const string LEGACY_MODEL = 'deepseek_model';

    public const string LEGACY_PROVIDER = 'practice_ai_provider';

    public function __construct(
        private readonly SystemSettingsService $systemSettings,
    ) {}

    public function apiKey(): ?string
    {
        $canonical = trim((string) ($this->systemSettings->get(self::CANONICAL_KEY) ?? ''));
        if ($canonical !== '') {
            return $canonical;
        }
        $legacy = trim((string) ($this->systemSettings->get(self::LEGACY_KEY) ?? ''));

        return $legacy !== '' ? $legacy : null;
    }

    public function modelName(): string
    {
        $canonical = trim((string) ($this->systemSettings->get(self::CANONICAL_MODEL) ?? ''));
        if ($canonical !== '') {
            return $canonical;
        }
        $legacy = trim((string) ($this->systemSettings->get(self::LEGACY_MODEL) ?? ''));

        return $legacy !== '' ? $legacy : self::DEFAULT_MODEL;
    }

    public function provider(): string
    {
        $canonical = strtolower(trim((string) ($this->systemSettings->get(self::CANONICAL_PROVIDER) ?? '')));
        if ($canonical !== '') {
            return $canonical;
        }
        $legacy = strtolower(trim((string) ($this->systemSettings->get(self::LEGACY_PROVIDER) ?? '')));

        return $legacy !== '' ? $legacy : self::DEFAULT_PROVIDER;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    /**
     * @return array{api_key: ?string, model: string, provider: string}
     */
    public function clientConfig(): array
    {
        return [
            'api_key' => $this->apiKey(),
            'model' => $this->modelName(),
            'provider' => $this->provider(),
        ];
    }
}
