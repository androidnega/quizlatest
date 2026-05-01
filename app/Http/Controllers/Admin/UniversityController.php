<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\University;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UniversityController extends Controller
{
    public function index(): View
    {
        return view('admin.universities.index', [
            'universities' => University::query()->latest()->paginate(10),
        ]);
    }

    public function create(): View
    {
        return view('admin.universities.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:30', 'unique:universities,code'],
            'is_active' => ['nullable', 'boolean'],
            'settings' => ['nullable', 'json'],
        ]);

        University::create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'settings' => isset($validated['settings']) ? json_decode($validated['settings'], true, 512, JSON_THROW_ON_ERROR) : null,
        ]);

        return redirect()->route('admin.universities.index')->with('status', 'University created successfully.');
    }

    public function edit(University $university): View
    {
        return view('admin.universities.edit', [
            'university' => $university,
        ]);
    }

    public function update(Request $request, University $university): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('universities', 'code')->ignore($university->id)],
            'is_active' => ['nullable', 'boolean'],
            'settings' => ['nullable', 'json'],
        ]);

        $university->update([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'settings' => isset($validated['settings']) ? json_decode($validated['settings'], true, 512, JSON_THROW_ON_ERROR) : null,
        ]);

        return redirect()->route('admin.universities.index')->with('status', 'University updated successfully.');
    }
}
