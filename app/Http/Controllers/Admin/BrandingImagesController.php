<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BrandingImagesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Super-admin endpoints for the two uploadable site-wide images:
 *  - the arena (gamified) exam runtime background
 *  - the marketing homepage hero photo (with a desktop / mobile visibility toggle)
 */
class BrandingImagesController extends Controller
{
    public function __construct(
        private readonly BrandingImagesService $branding,
    ) {}

    public function updateArenaBackground(Request $request): RedirectResponse
    {
        $this->authorize('manageSystemSettings');

        $user = $request->user();
        if ($user === null || ! $user->isSuperAdmin()) {
            abort(403);
        }

        $request->validate([
            'background_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'remove_background' => ['nullable', 'boolean'],
        ]);

        try {
            if ($request->boolean('remove_background')) {
                $this->branding->resetArenaBackground($user);

                return redirect()
                    ->route('admin.settings.index')
                    ->with('status', __('Arena exam background reset to the default image.'));
            }

            if ($request->hasFile('background_image')) {
                $this->branding->storeArenaBackground($request->file('background_image'), $user);

                return redirect()
                    ->route('admin.settings.index')
                    ->with('status', __('Arena exam background updated.'));
            }
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.settings.index')
                ->withErrors(['background_image' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.settings.index')
            ->with('status', __('No background changes were made.'));
    }

    public function updateHomepageHero(Request $request): RedirectResponse
    {
        $this->authorize('manageSystemSettings');

        $user = $request->user();
        if ($user === null || ! $user->isSuperAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'hero_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'remove_hero' => ['nullable', 'boolean'],
            'hero_visibility' => [
                'nullable',
                'string',
                Rule::in(array_keys(BrandingImagesService::HOMEPAGE_HERO_VISIBILITY_OPTIONS)),
            ],
        ]);

        try {
            if ($request->boolean('remove_hero')) {
                $this->branding->resetHomepageHero($user);

                return redirect()
                    ->route('admin.settings.index')
                    ->with('status', __('Homepage hero image reset to the default.'));
            }

            if ($request->hasFile('hero_image')) {
                $this->branding->storeHomepageHero($request->file('hero_image'), $user);
            }

            if (! empty($validated['hero_visibility'])) {
                $this->branding->setHomepageHeroVisibility($validated['hero_visibility'], $user);
            }
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.settings.index')
                ->withErrors(['hero_image' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.settings.index')
            ->with('status', __('Homepage hero settings saved.'));
    }
}
