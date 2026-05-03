<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Term;
use App\Models\University;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicYearController extends Controller
{
    public function index(): View
    {
        $this->authorize('manageSystemSettings', auth()->user());

        $years = AcademicYear::query()
            ->with(['university:id,name', 'terms'])
            ->orderByDesc('start_date')
            ->paginate(20);

        return view('admin.academic-years.index', ['academicYears' => $years]);
    }

    public function create(): View
    {
        $this->authorize('manageSystemSettings', auth()->user());

        return view('admin.academic-years.create', [
            'universities' => University::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manageSystemSettings', auth()->user());

        $validated = $request->validate([
            'university_id' => ['required', 'integer', 'exists:universities,id'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'string', Rule::in([
                AcademicYear::STATUS_UPCOMING,
                AcademicYear::STATUS_ACTIVE,
                AcademicYear::STATUS_CLOSED,
                AcademicYear::STATUS_ARCHIVED,
            ])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $year = AcademicYear::query()->create([
            'university_id' => (int) $validated['university_id'],
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => $validated['status'],
            'is_active' => false,
        ]);

        $term = Term::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'Full year',
            'start_date' => $year->start_date,
            'end_date' => $year->end_date,
            'status' => Term::STATUS_UPCOMING,
            'is_active' => false,
        ]);

        if ($request->boolean('is_active')) {
            $year->activateExclusive();
            $term->activateExclusive();
        }

        return redirect()->route('admin.academic-years.index')->with('status', __('Academic year saved.'));
    }

    public function edit(AcademicYear $academicYear): View
    {
        $this->authorize('manageSystemSettings', auth()->user());

        return view('admin.academic-years.edit', [
            'academicYear' => $academicYear->load(['terms', 'university']),
            'universities' => University::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $this->authorize('manageSystemSettings', auth()->user());

        $validated = $request->validate([
            'university_id' => ['required', 'integer', 'exists:universities,id'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'string', Rule::in([
                AcademicYear::STATUS_UPCOMING,
                AcademicYear::STATUS_ACTIVE,
                AcademicYear::STATUS_CLOSED,
                AcademicYear::STATUS_ARCHIVED,
            ])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $academicYear->fill([
            'university_id' => (int) $validated['university_id'],
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => $validated['status'],
        ]);

        if ($request->boolean('is_active')) {
            $academicYear->activateExclusive();
        } else {
            $academicYear->is_active = false;
            $academicYear->save();
        }

        return redirect()->route('admin.academic-years.edit', $academicYear)->with('status', __('Academic year updated.'));
    }

    public function storeTerm(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $this->authorize('manageSystemSettings', auth()->user());

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'string', Rule::in([
                Term::STATUS_UPCOMING,
                Term::STATUS_ACTIVE,
                Term::STATUS_CLOSED,
                Term::STATUS_ARCHIVED,
            ])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $term = Term::query()->create([
            'academic_year_id' => $academicYear->id,
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => $validated['status'],
            'is_active' => false,
        ]);

        if ($request->boolean('is_active')) {
            $term->activateExclusive();
        } else {
            $term->save();
        }

        return redirect()->route('admin.academic-years.edit', $academicYear)->with('status', __('Term added.'));
    }

    public function updateTerm(Request $request, AcademicYear $academicYear, Term $term): RedirectResponse
    {
        $this->authorize('manageSystemSettings', auth()->user());
        abort_unless((int) $term->academic_year_id === (int) $academicYear->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'string', Rule::in([
                Term::STATUS_UPCOMING,
                Term::STATUS_ACTIVE,
                Term::STATUS_CLOSED,
                Term::STATUS_ARCHIVED,
            ])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $term->fill([
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => $validated['status'],
        ]);

        if ($request->boolean('is_active')) {
            $term->activateExclusive();
        } else {
            $term->is_active = false;
            $term->save();
        }

        return redirect()->route('admin.academic-years.edit', $academicYear)->with('status', __('Term updated.'));
    }
}
