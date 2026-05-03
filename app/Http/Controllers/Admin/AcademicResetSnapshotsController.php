<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicResetSnapshot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AcademicResetSnapshotsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $snapshots = AcademicResetSnapshot::query()
            ->with(['department', 'initiator'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.academic-reset-snapshots.index', [
            'snapshots' => $snapshots,
        ]);
    }
}
