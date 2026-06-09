<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\AiIntegrationSettings;
use App\Services\AssignmentEssayAiGradingService;
use App\Services\PracticeModuleSettings;
use App\Services\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the unified AI integration: every AI feature in
 * the product (examiner question generation, lecturer essay grading,
 * student practice quizzes, study summaries) must resolve credentials from
 * ONE place — `AiIntegrationSettings`. The duplicate `deepseek_*` /
 * `practice_ai_provider` fields are kept ONLY as a read-fallback so old
 * installs do not break, never as a separate config surface.
 */
class UnifiedAiIntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin);

        return $admin;
    }

    public function test_setting_only_the_canonical_ai_api_key_makes_grading_callable(): void
    {
        // Older code paths required deepseek_api_key. With the unified
        // integration the canonical ai_api_key alone must be enough.
        $admin = $this->actingAdmin();
        $settings = app(SystemSettingsService::class);
        $settings->set(AiIntegrationSettings::CANONICAL_KEY, 'sk-unified-001', $admin);

        $ai = app(AiIntegrationSettings::class);
        $this->assertSame('sk-unified-001', $ai->apiKey());
        $this->assertTrue($ai->isConfigured());
        $this->assertSame(AiIntegrationSettings::DEFAULT_MODEL, $ai->modelName());

        // Practice module and grading service must observe the SAME key
        // without a separate `deepseek_api_key` ever being set.
        $this->assertTrue(app(PracticeModuleSettings::class)->deepseekConfigured());
        $this->assertSame('sk-unified-001', $ai->clientConfig()['api_key']);

        // No legacy keys were written.
        $this->assertSame('', (string) ($settings->get(AiIntegrationSettings::LEGACY_KEY) ?? ''));

        // Service class is constructible (sanity check that the DI chain
        // through AiIntegrationSettings → DeepSeek → grading still resolves).
        $this->assertInstanceOf(AssignmentEssayAiGradingService::class, app(AssignmentEssayAiGradingService::class));
    }

    public function test_legacy_deepseek_api_key_is_used_as_a_read_fallback_for_existing_installs(): void
    {
        // An admin who installed a previous version may have set
        // deepseek_api_key only. The unified service must still expose it.
        $admin = $this->actingAdmin();
        $settings = app(SystemSettingsService::class);
        $settings->set(AiIntegrationSettings::LEGACY_KEY, 'sk-legacy-deepseek', $admin);
        $settings->set(AiIntegrationSettings::LEGACY_MODEL, 'deepseek-coder', $admin);

        $ai = app(AiIntegrationSettings::class);
        $this->assertSame('sk-legacy-deepseek', $ai->apiKey());
        $this->assertSame('deepseek-coder', $ai->modelName());
        $this->assertTrue($ai->isConfigured());
    }

    public function test_canonical_value_takes_precedence_over_legacy_when_both_are_present(): void
    {
        // After admin enters a new key in the unified section, the
        // legacy value must NOT override it.
        $admin = $this->actingAdmin();
        $settings = app(SystemSettingsService::class);
        $settings->set(AiIntegrationSettings::LEGACY_KEY, 'sk-legacy-stale', $admin);
        $settings->set(AiIntegrationSettings::CANONICAL_KEY, 'sk-new-canonical', $admin);

        $this->assertSame('sk-new-canonical', app(AiIntegrationSettings::class)->apiKey());
    }

    public function test_admin_settings_page_no_longer_renders_the_duplicate_deepseek_credential_fields(): void
    {
        // The duplicate "DeepSeek API key", "DeepSeek model" and "Provider
        // identifier" inputs in the Practice section are gone. Only the
        // unified AI section accepts credentials now.
        $this->actingAdmin();

        $response = $this->get(route('admin.settings.index', absolute: false));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringNotContainsString('name="deepseek_api_key"', $html, 'Practice section must no longer render its own deepseek_api_key input.');
        $this->assertStringNotContainsString('name="deepseek_model"', $html, 'Practice section must no longer render its own deepseek_model input.');
        $this->assertStringNotContainsString('name="practice_ai_provider"', $html, 'Practice section must no longer render a practice_ai_provider input.');

        $this->assertStringContainsString('name="ai_api_key"', $html, 'Unified AI section must still expose the canonical ai_api_key input.');
        $this->assertStringContainsString('name="ai_model_name"', $html, 'Unified AI section must still expose the canonical ai_model_name input.');
        $this->assertStringContainsString('name="ai_provider"', $html, 'Unified AI section must expose the new ai_provider picker.');
        $this->assertStringContainsString('AI integration (system-wide)', $html, 'Section heading must signal that this is the one place credentials live.');
    }

    public function test_admin_can_set_unified_ai_provider_and_it_persists(): void
    {
        $admin = $this->actingAdmin();

        $this->put(route('admin.settings.update', absolute: false), [
            'ai_api_key' => 'sk-test',
            'ai_model_name' => 'gpt-4o-mini',
            'ai_provider' => 'openai',
            'enable_ai' => '1',
        ])->assertRedirect(route('admin.settings.index', absolute: false));

        $settings = app(SystemSettingsService::class);
        $this->assertSame('sk-test', $settings->get(AiIntegrationSettings::CANONICAL_KEY));
        $this->assertSame('gpt-4o-mini', $settings->get(AiIntegrationSettings::CANONICAL_MODEL));
        $this->assertSame('openai', $settings->get(AiIntegrationSettings::CANONICAL_PROVIDER));

        $ai = app(AiIntegrationSettings::class);
        $this->assertSame('openai', $ai->provider());
        $this->assertSame('gpt-4o-mini', $ai->modelName());

        // Practice module reads the SAME provider/model — no per-feature
        // override exists anymore.
        $practice = app(PracticeModuleSettings::class);
        $this->assertSame('openai', $practice->practiceAiProvider());
        $this->assertSame('gpt-4o-mini', $practice->deepseekModel());
    }

    public function test_unknown_provider_is_rejected_by_validation(): void
    {
        $this->actingAdmin();

        $this->put(route('admin.settings.update', absolute: false), [
            'ai_provider' => 'fake-provider',
        ])->assertSessionHasErrors('ai_provider');
    }
}
