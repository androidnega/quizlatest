<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Services\CourseMaterialTextExtractor;
use App\Services\ExaminerCourseScopeService;
use App\Services\PracticeModuleSettings;
use App\Services\SensitiveStorageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseMaterialController extends Controller
{
    public function index(
        Request $request,
        PracticeModuleSettings $practice,
        ExaminerCourseScopeService $scope,
        Course $course,
    ): View|RedirectResponse {
        $practice->assertMaterialUploadsOrAbort();
        abort_unless($scope->canManageCourse($request->user(), (int) $course->id), 403);

        $materials = CourseMaterial::query()
            ->where('course_id', $course->id)
            ->with(['uploader:id,name', 'classroom:id,name'])
            ->orderByDesc('created_at')
            ->paginate(20);

        $classes = Classroom::query()
            ->where('university_id', $request->user()->university_id)
            ->whereHas('classCourses', fn ($q) => $q->where('course_id', $course->id))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('examiner.course-materials.index', [
            'course' => $course,
            'materials' => $materials,
            'classes' => $classes,
        ]);
    }

    public function store(
        Request $request,
        PracticeModuleSettings $practice,
        ExaminerCourseScopeService $scope,
        CourseMaterialTextExtractor $extractor,
        Course $course,
    ): RedirectResponse {
        $practice->assertMaterialUploadsOrAbort();
        abort_unless($scope->canManageCourse($request->user(), (int) $course->id), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'file' => ['required', 'file', 'max:12288', 'mimes:pdf,docx,txt'],
        ]);

        $classId = isset($validated['class_id']) ? (int) $validated['class_id'] : null;
        if ($classId !== null) {
            $ok = DB::table('class_course')
                ->where('course_id', $course->id)
                ->where('class_id', $classId)
                ->exists();
            abort_unless($ok, 422, __('Selected class is not linked to this course.'));
        }

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $material = CourseMaterial::query()->create([
            'course_id' => $course->id,
            'class_id' => $classId,
            'uploaded_by' => $request->user()->id,
            'title' => $validated['title'],
            'file_path' => '',
            'file_type' => $ext,
            'extracted_text_path' => null,
            'status' => CourseMaterial::STATUS_PENDING,
            'extraction_error' => null,
        ]);

        $dir = 'course_materials/'.$material->id;
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $storedName = $safeName.'-'.Str::random(8).'.'.$ext;
        $relative = $file->storeAs($dir, $storedName, 'local');

        $material->update(['file_path' => $relative]);

        try {
            $extractedRel = $extractor->extractToRelativePath($relative, $ext);
            $material->update([
                'extracted_text_path' => $extractedRel,
                'status' => CourseMaterial::STATUS_READY,
                'extraction_error' => null,
            ]);
        } catch (\Throwable $e) {
            $material->update([
                'status' => CourseMaterial::STATUS_FAILED,
                'extraction_error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('examiner.courses.materials.index', $course)
                ->withErrors(['file' => __('Extraction failed: :msg', ['msg' => $e->getMessage()])]);
        }

        return redirect()
            ->route('examiner.courses.materials.index', $course)
            ->with('status', __('Material uploaded and processed.'));
    }

    public function download(
        Request $request,
        PracticeModuleSettings $practice,
        ExaminerCourseScopeService $scope,
        SensitiveStorageService $sensitiveStorage,
        Course $course,
        CourseMaterial $material,
    ): StreamedResponse|RedirectResponse {
        $practice->assertMaterialUploadsOrAbort();
        abort_unless((int) $material->course_id === (int) $course->id, 404);
        abort_unless($scope->canManageCourse($request->user(), (int) $course->id), 403);

        $path = $material->file_path;
        abort_if($path === '', 404);
        abort_unless($sensitiveStorage->existsAnywhere($path), 404);

        return $sensitiveStorage->downloadResponse($path, basename($path));
    }

    public function destroy(
        Request $request,
        PracticeModuleSettings $practice,
        ExaminerCourseScopeService $scope,
        SensitiveStorageService $sensitiveStorage,
        Course $course,
        CourseMaterial $material,
    ): RedirectResponse {
        $practice->assertMaterialUploadsOrAbort();
        abort_unless((int) $material->course_id === (int) $course->id, 404);
        abort_unless($scope->canManageCourse($request->user(), (int) $course->id), 403);

        if ($material->file_path !== '') {
            $sensitiveStorage->deleteFromAnywhere($material->file_path);
        }
        if ($material->extracted_text_path !== null && $material->extracted_text_path !== '') {
            $sensitiveStorage->deleteFromAnywhere($material->extracted_text_path);
        }

        $material->delete();

        return redirect()
            ->route('examiner.courses.materials.index', $course)
            ->with('status', __('Material deleted.'));
    }
}
