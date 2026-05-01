<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Level;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class LevelController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Level::class);

        return view('coordinator.levels.index', [
            'levels' => Level::query()
                ->where('university_id', $this->universityId())
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function toggleStatus(Level $level): RedirectResponse
    {
        $this->authorize('update', $level);

        $level->update(['is_active' => ! $level->is_active]);

        return redirect()->route('coordinator.levels.index')->with('status', 'Level status updated.');
    }

    private function universityId(): int
    {
        return (int) auth()->user()->university_id;
    }
}
