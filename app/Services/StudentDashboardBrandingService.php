<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class StudentDashboardBrandingService
{
    public const SETTING_KEY = 'student_dashboard_profile_banner_path';

    public const CUSTOM_RELATIVE_PATH = 'images/branding/quizsnap-student-dashboard-profile-banner.jpg';

    public const DEFAULT_RELATIVE_PATH = 'images/student/quizsnap-student-dashboard-profile-banner-background.jpg';

    /**
     * System-settings key that picks which color theme the wallet-style mobile
     * student dashboard uses. The same theme is applied to the mobile chrome
     * (header + FAB) on every student page so the experience feels consistent.
     */
    public const WALLET_THEME_SETTING_KEY = 'student_dashboard_mobile_wallet_theme';

    public const WALLET_THEME_DEFAULT = 'teal';

    /**
     * Whitelist of theme slugs the wallet UI knows how to render. The CSS
     * defines matching `[data-theme="..."]` rule sets in student-dashboard.css.
     *
     * @var array<string, array{label: string, description: string}>
     */
    public const WALLET_THEMES = [
        'teal' => [
            'label' => 'QuizSnap (default)',
            'description' => 'Main website teal/cyan palette with a warm coral accent.',
        ],
        'forest' => [
            'label' => 'Forest',
            'description' => 'Deep forest-green hero with a lime action chip — the original wallet look.',
        ],
        'indigo' => [
            'label' => 'Midnight indigo',
            'description' => 'Navy indigo hero with a cool sky-blue accent — calm and focused.',
        ],
        'coral' => [
            'label' => 'Sunset coral',
            'description' => 'Warm coral hero with a cream accent — bright and friendly.',
        ],
        'noir' => [
            'label' => 'Noir wallet',
            'description' => 'Sleek matte-black wallet hero with multi-color ring accents — like a modern fintech app, themed around your assessments.',
        ],
    ];

    private const MAX_BANNER_BYTES = 180000;

    private const BANNER_MAX_WIDTH = 1280;

    private const BANNER_MAX_HEIGHT = 720;

    public function __construct(
        private readonly SystemSettingsService $systemSettings,
        private readonly OptimizedImageService $images,
    ) {}

    /**
     * Slug of the currently chosen wallet theme. Falls back to the default if
     * the persisted value is missing or has been removed from the whitelist
     * (which keeps legacy data safe across deploys).
     */
    public function walletTheme(): string
    {
        $raw = strtolower(trim((string) ($this->systemSettings->get(self::WALLET_THEME_SETTING_KEY) ?? '')));
        if ($raw === '' || ! array_key_exists($raw, self::WALLET_THEMES)) {
            return self::WALLET_THEME_DEFAULT;
        }

        return $raw;
    }

    /**
     * @return list<array{slug: string, label: string, description: string}>
     */
    public function walletThemeOptions(): array
    {
        $out = [];
        foreach (self::WALLET_THEMES as $slug => $meta) {
            $out[] = [
                'slug' => $slug,
                'label' => $meta['label'],
                'description' => $meta['description'],
            ];
        }

        return $out;
    }

    public function bannerUrl(): string
    {
        $path = $this->activeRelativePath();

        return asset($path);
    }

    public function hasCustomBanner(): bool
    {
        $stored = trim((string) ($this->systemSettings->get(self::SETTING_KEY) ?? ''));

        return $stored !== ''
            && $stored === self::CUSTOM_RELATIVE_PATH
            && File::isFile(public_path($stored));
    }

    public function storeCustomBanner(UploadedFile $file, User $admin): void
    {
        $binary = $this->images->encodeJpegWithinBudget(
            $file,
            self::BANNER_MAX_WIDTH,
            self::BANNER_MAX_HEIGHT,
            self::MAX_BANNER_BYTES,
        );

        $absolute = public_path(self::CUSTOM_RELATIVE_PATH);
        File::ensureDirectoryExists(dirname($absolute));
        File::put($absolute, $binary);

        $this->systemSettings->set(self::SETTING_KEY, self::CUSTOM_RELATIVE_PATH, $admin);
    }

    public function resetCustomBanner(User $admin): void
    {
        $absolute = public_path(self::CUSTOM_RELATIVE_PATH);
        if (File::isFile($absolute)) {
            File::delete($absolute);
        }

        $this->systemSettings->set(self::SETTING_KEY, '', $admin);
    }

    private function activeRelativePath(): string
    {
        if ($this->hasCustomBanner()) {
            return self::CUSTOM_RELATIVE_PATH;
        }

        if (File::isFile(public_path(self::DEFAULT_RELATIVE_PATH))) {
            return self::DEFAULT_RELATIVE_PATH;
        }

        return self::CUSTOM_RELATIVE_PATH;
    }
}
