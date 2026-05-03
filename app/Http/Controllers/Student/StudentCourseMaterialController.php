<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CourseMaterial;
use App\Services\PracticeModuleSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentCourseMaterialController extends Controller
{
    public function index(PracticeModuleSettings $practice): View|RedirectResponse
    {
        $practice->assertStudentPracticeOrAbort();

        $user = auth()->user();

        $materials = CourseMaterial::query()
            ->visibleToStudent($user)
            ->with(['course:id,code,title'])
            ->orderByDesc('created_at')
            ->get();

        return view('student.practice.materials', [
            'materials' => $materials,
        ]);
    }

    public function download(PracticeModuleSettings $practice, CourseMaterial $material): StreamedResponse|RedirectResponse
    {
        $practice->assertStudentPracticeOrAbort();

        $user = auth()->user();

        $row = CourseMaterial::query()
            ->visibleToStudent($user)
            ->whereKey($material->id)
            ->first();
        abort_if($row === null, 404);

        return Storage::disk('local')->download($row->file_path, basename($row->file_path));
    }
}
