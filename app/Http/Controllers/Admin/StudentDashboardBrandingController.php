<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StudentDashboardBrandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class StudentDashboardBrandingController extends Controller
{
    public function __construct(
        private readonly StudentDashboardBrandingService $branding,
    ) {}

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('manageSystemSettings');

        $user = $request->user();
        if ($user === null || ! $user->isSuperAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'banner_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'remove_banner' => ['nullable', 'boolean'],
        ]);

        try {
            if ($request->boolean('remove_banner')) {
                $this->branding->resetCustomBanner($user);

                return redirect()
                    ->route('admin.settings.index')
                    ->with('status', __('Student dashboard banner reset to the default image.'));
            }

            if ($request->hasFile('banner_image')) {
                $this->branding->storeCustomBanner($request->file('banner_image'), $user);

                return redirect()
                    ->route('admin.settings.index')
                    ->with('status', __('Student dashboard banner updated.'));
            }
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.settings.index')
                ->withErrors(['banner_image' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.settings.index')
            ->with('status', __('No banner changes were made.'));
    }
}
