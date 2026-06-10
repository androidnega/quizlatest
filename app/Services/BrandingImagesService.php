<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

/**
 * Manages super-admin uploadable marketing/runtime imagery:
 *
 *   1. The full-screen background photo behind the gamified ("arena") exam
 *      runtime. Drops straight into the `--qs-arena-bg-image` CSS variable
 *      the arena layout already reads.
 *
 *   2. The homepage hero photo, with a visibility toggle so admins can pick
 *      whether the photo renders on desktop only, mobile only, or both.
 *
 * Both assets re-encode the upload via {@see OptimizedImageService} so EXIF
 * and any embedded payloads are stripped before the JPEG lands in /public.
 */
class BrandingImagesService
{
    // ---------- Arena (gamified) exam runtime background ----------

    public const ARENA_BG_SETTING_KEY = 'arena_runtime_background_image_path';

    public const ARENA_BG_CUSTOM_RELATIVE_PATH = 'images/branding/quizsnap-arena-runtime-background.jpg';

    public const ARENA_BG_DEFAULT_RELATIVE_PATH = 'images/home/quizsnap-homepage-hero-desktop-student-laptop.jpg';

    private const ARENA_BG_MAX_WIDTH = 1920;

    private const ARENA_BG_MAX_HEIGHT = 1080;

    private const ARENA_BG_MAX_BYTES = 320_000;

    // ---------- Homepage hero photo + visibility ----------

    public const HOMEPAGE_HERO_SETTING_KEY = 'homepage_hero_image_path';

    public const HOMEPAGE_HERO_VISIBILITY_KEY = 'homepage_hero_visibility';

    public const HOMEPAGE_HERO_CUSTOM_RELATIVE_PATH = 'images/branding/quizsnap-homepage-hero.jpg';

    public const HOMEPAGE_HERO_DEFAULT_RELATIVE_PATH = 'images/home/quizsnap-homepage-hero-desktop-student-laptop.jpg';

    /**
     * Default visibility keeps the marketing site's existing behaviour: the
     * desktop hero shows the photo, the mobile hero stays a clean type-only
     * layout. Existing snapshot/integration tests rely on this.
     */
    public const HOMEPAGE_HERO_VISIBILITY_DEFAULT = 'desktop';

    /**
     * @var array<string, array{label: string, description: string}>
     */
    public const HOMEPAGE_HERO_VISIBILITY_OPTIONS = [
        'both' => [
            'label' => 'Desktop and mobile',
            'description' => 'Photo appears on every device. Mobile gets a banner at the top of the hero.',
        ],
        'desktop' => [
            'label' => 'Desktop only',
            'description' => 'Photo appears beside the headline on desktop / tablet. Mobile stays a clean text-only hero.',
        ],
        'mobile' => [
            'label' => 'Mobile only',
            'description' => 'Photo appears as a banner on phones. Desktop / tablet show a centred text-only hero.',
        ],
    ];

    private const HOMEPAGE_HERO_MAX_WIDTH = 1600;

    private const HOMEPAGE_HERO_MAX_HEIGHT = 1200;

    private const HOMEPAGE_HERO_MAX_BYTES = 220_000;

    public function __construct(
        private readonly SystemSettingsService $systemSettings,
        private readonly OptimizedImageService $images,
    ) {}

    // =========================================================
    //   Arena runtime background
    // =========================================================

    public function arenaBackgroundUrl(): string
    {
        return asset($this->activeArenaBackgroundRelativePath());
    }

    public function hasCustomArenaBackground(): bool
    {
        $stored = trim((string) $this->readSetting(self::ARENA_BG_SETTING_KEY));

        return $stored !== ''
            && $stored === self::ARENA_BG_CUSTOM_RELATIVE_PATH
            && File::isFile(public_path($stored));
    }

    public function storeArenaBackground(UploadedFile $file, User $admin): void
    {
        $binary = $this->images->encodeJpegWithinBudget(
            $file,
            self::ARENA_BG_MAX_WIDTH,
            self::ARENA_BG_MAX_HEIGHT,
            self::ARENA_BG_MAX_BYTES,
        );

        $absolute = public_path(self::ARENA_BG_CUSTOM_RELATIVE_PATH);
        File::ensureDirectoryExists(dirname($absolute));
        File::put($absolute, $binary);

        $this->systemSettings->set(self::ARENA_BG_SETTING_KEY, self::ARENA_BG_CUSTOM_RELATIVE_PATH, $admin);
    }

    public function resetArenaBackground(User $admin): void
    {
        $absolute = public_path(self::ARENA_BG_CUSTOM_RELATIVE_PATH);
        if (File::isFile($absolute)) {
            File::delete($absolute);
        }

        $this->systemSettings->set(self::ARENA_BG_SETTING_KEY, '', $admin);
    }

    private function activeArenaBackgroundRelativePath(): string
    {
        if ($this->hasCustomArenaBackground()) {
            return self::ARENA_BG_CUSTOM_RELATIVE_PATH;
        }

        return self::ARENA_BG_DEFAULT_RELATIVE_PATH;
    }

    // =========================================================
    //   Homepage hero photo + visibility
    // =========================================================

    public function homepageHeroUrl(): string
    {
        return asset($this->activeHomepageHeroRelativePath());
    }

    public function hasCustomHomepageHero(): bool
    {
        $stored = trim((string) $this->readSetting(self::HOMEPAGE_HERO_SETTING_KEY));

        return $stored !== ''
            && $stored === self::HOMEPAGE_HERO_CUSTOM_RELATIVE_PATH
            && File::isFile(public_path($stored));
    }

    public function storeHomepageHero(UploadedFile $file, User $admin): void
    {
        $binary = $this->images->encodeJpegWithinBudget(
            $file,
            self::HOMEPAGE_HERO_MAX_WIDTH,
            self::HOMEPAGE_HERO_MAX_HEIGHT,
            self::HOMEPAGE_HERO_MAX_BYTES,
        );

        $absolute = public_path(self::HOMEPAGE_HERO_CUSTOM_RELATIVE_PATH);
        File::ensureDirectoryExists(dirname($absolute));
        File::put($absolute, $binary);

        $this->systemSettings->set(self::HOMEPAGE_HERO_SETTING_KEY, self::HOMEPAGE_HERO_CUSTOM_RELATIVE_PATH, $admin);
    }

    public function resetHomepageHero(User $admin): void
    {
        $absolute = public_path(self::HOMEPAGE_HERO_CUSTOM_RELATIVE_PATH);
        if (File::isFile($absolute)) {
            File::delete($absolute);
        }

        $this->systemSettings->set(self::HOMEPAGE_HERO_SETTING_KEY, '', $admin);
    }

    /**
     * @return 'both'|'desktop'|'mobile'
     */
    public function homepageHeroVisibility(): string
    {
        $raw = strtolower(trim((string) $this->readSetting(self::HOMEPAGE_HERO_VISIBILITY_KEY)));
        if ($raw === '' || ! array_key_exists($raw, self::HOMEPAGE_HERO_VISIBILITY_OPTIONS)) {
            return self::HOMEPAGE_HERO_VISIBILITY_DEFAULT;
        }

        /** @var 'both'|'desktop'|'mobile' $raw */
        return $raw;
    }

    public function setHomepageHeroVisibility(string $value, User $admin): void
    {
        $normalised = strtolower(trim($value));
        if (! array_key_exists($normalised, self::HOMEPAGE_HERO_VISIBILITY_OPTIONS)) {
            return;
        }

        $this->systemSettings->set(self::HOMEPAGE_HERO_VISIBILITY_KEY, $normalised, $admin);
    }

    /**
     * @return list<array{slug: string, label: string, description: string}>
     */
    public function homepageHeroVisibilityOptions(): array
    {
        $out = [];
        foreach (self::HOMEPAGE_HERO_VISIBILITY_OPTIONS as $slug => $meta) {
            $out[] = [
                'slug' => $slug,
                'label' => $meta['label'],
                'description' => $meta['description'],
            ];
        }

        return $out;
    }

    public function homepageHeroShowsOnDesktop(): bool
    {
        return in_array($this->homepageHeroVisibility(), ['both', 'desktop'], true);
    }

    public function homepageHeroShowsOnMobile(): bool
    {
        return in_array($this->homepageHeroVisibility(), ['both', 'mobile'], true);
    }

    private function activeHomepageHeroRelativePath(): string
    {
        if ($this->hasCustomHomepageHero()) {
            return self::HOMEPAGE_HERO_CUSTOM_RELATIVE_PATH;
        }

        return self::HOMEPAGE_HERO_DEFAULT_RELATIVE_PATH;
    }

    /**
     * Defensive wrapper around the encrypted system-settings store.
     *
     * The homepage and the arena exam layout BOTH read this service every
     * request. We must never let a missing migration, a transient DB
     * connection drop, or a stray decrypt failure break the public site
     * or a live exam — fall back to defaults instead.
     */
    private function readSetting(string $key): ?string
    {
        try {
            return $this->systemSettings->get($key);
        } catch (\Throwable) {
            return null;
        }
    }
}
