<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CourseMaterial;
use App\Services\PracticeModuleSettings;
use App\Services\SensitiveStorageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentCourseMaterialController extends Controller
{
    public function index(PracticeModuleSettings $practice): View|RedirectResponse
    {
        $practice->assertStudentCourseMaterialsBrowseOrAbort();

        $user = auth()->user();

        $materials = CourseMaterial::query()
            ->visibleToStudent($user)
            ->with(['course:id,code,title'])
            ->orderByRaw("CASE WHEN material_kind = '".CourseMaterial::KIND_COURSE_OUTLINE."' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->get();

        return view('student.practice.materials', [
            'materials' => $materials,
        ]);
    }

    public function download(
        PracticeModuleSettings $practice,
        SensitiveStorageService $sensitiveStorage,
        CourseMaterial $material,
    ): StreamedResponse|RedirectResponse {
        $practice->assertStudentCourseMaterialsBrowseOrAbort();

        $user = auth()->user();

        $row = CourseMaterial::query()
            ->visibleToStudent($user)
            ->whereKey($material->id)
            ->first();
        abort_if($row === null, 404);

        abort_if($row->file_path === '', 404);
        abort_unless($sensitiveStorage->existsAnywhere($row->file_path), 404);

        return $sensitiveStorage->downloadResponse($row->file_path, basename($row->file_path));
    }
}
