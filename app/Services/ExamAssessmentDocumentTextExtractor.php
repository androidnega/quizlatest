<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Parser;
use ZipArchive;

/**
 * Pulls plain text from lecturer-uploaded outlines (PDF, TXT, DOCX, CSV) for AI question prompts.
 */
final class ExamAssessmentDocumentTextExtractor
{
    public const int MAX_EXTRACTED_CHARS = 140_000;

    public function extractPlainText(UploadedFile $file): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        $raw = match ($extension) {
            'txt' => $this->fromTxt($file),
            'pdf' => $this->fromPdf($file),
            'docx' => $this->fromDocx($file),
            'csv' => $this->fromCsv($file),
            default => throw ValidationException::withMessages([
                'ai_outline_file' => [__('Unsupported file type. Use PDF, TXT, DOCX, or CSV.')],
            ]),
        };

        $normalized = $this->normalizeWhitespace($raw);

        if ($normalized === '') {
            throw ValidationException::withMessages([
                'ai_outline_file' => [__('Could not read any text from that file.')],
            ]);
        }

        return mb_substr($normalized, 0, self::MAX_EXTRACTED_CHARS);
    }

    private function fromTxt(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            throw ValidationException::withMessages([
                'ai_outline_file' => [__('Could not read the text file.')],
            ]);
        }

        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw ValidationException::withMessages([
                'ai_outline_file' => [__('Could not read the text file.')],
            ]);
        }

        return $this->scrubToUtf8($bytes);
    }

    private function fromPdf(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            throw ValidationException::withMessages([
                'ai_outline_file' => [__('Could not read the PDF file.')],
            ]);
        }

        try {
            $parser = new Parser;
            $pdf = $parser->parseFile($path);

            return $this->scrubToUtf8((string) $pdf->getText());
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'ai_outline_file' => [__('Could not read PDF text. Try another export or TXT/DOCX.')],
            ]);
        }
    }

    private function fromDocx(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            throw ValidationException::withMessages([
                'ai_outline_file' => [__('Could not read the DOCX file.')],
            ]);
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages([
                'ai_outline_file' => [__('Invalid or corrupt DOCX file.')],
            ]);
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false || $xml === '') {
            throw ValidationException::withMessages([
                'ai_outline_file' => [__('DOCX has no readable document body.')],
            ]);
        }

        $xml = str_replace(['</w:p>', '</w:tr>'], "\n", $xml);
        $text = strip_tags($xml);

        return $this->scrubToUtf8($text);
    }

    private function fromCsv(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            throw ValidationException::withMessages([
                'ai_outline_file' => [__('Could not read the CSV file.')],
            ]);
        }

        $handle = @fopen($path, 'r');
        if ($handle === false) {
            throw ValidationException::withMessages([
                'ai_outline_file' => [__('Could not read the CSV file.')],
            ]);
        }

        $lines = [];
        try {
            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                foreach ($row as $cell) {
                    $cell = trim((string) $cell);
                    if ($cell !== '') {
                        // Emit each cell on its own line so the topic suggester
                        // can treat them as discrete candidates.
                        $lines[] = $cell;
                    }
                }
            }
        } finally {
            fclose($handle);
        }

        return $this->scrubToUtf8(implode("\n", $lines));
    }

    private function scrubToUtf8(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        if (mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }

        $enc = mb_detect_encoding($raw, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);

        if ($enc === false) {
            return $raw;
        }

        $converted = mb_convert_encoding($raw, 'UTF-8', $enc);

        return is_string($converted) ? $converted : $raw;
    }

    private function normalizeWhitespace(string $s): string
    {
        $s = preg_replace('/\R+/u', "\n", $s) ?? '';
        $s = preg_replace('/[ \t]+/u', ' ', $s) ?? '';
        $s = preg_replace("/\n{3,}/u", "\n\n", $s) ?? '';

        return trim($s);
    }
}
