<?php

namespace Tests\Unit;

use App\Services\ExamAssessmentDocumentTextExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExamAssessmentDocumentTextExtractorTest extends TestCase
{
    public function test_extracts_plain_text_from_txt_upload(): void
    {
        $file = UploadedFile::fake()->createWithContent('outline.txt', "Topic A\nTopic B");

        $text = app(ExamAssessmentDocumentTextExtractor::class)->extractPlainText($file);

        $this->assertStringContainsString('Topic A', $text);
        $this->assertStringContainsString('Topic B', $text);
    }

    public function test_rejects_unknown_extension(): void
    {
        $file = UploadedFile::fake()->create('bad.exe', 10);

        $this->expectException(ValidationException::class);

        app(ExamAssessmentDocumentTextExtractor::class)->extractPlainText($file);
    }
}
