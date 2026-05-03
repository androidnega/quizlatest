<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

class CourseMaterialTextExtractor
{
    private const int MAX_CHARS = 120_000;

    public function extractToRelativePath(string $diskRelativeOriginalPath, string $extension): string
    {
        $contents = Storage::disk('local')->get($diskRelativeOriginalPath);
        if ($contents === null || $contents === '') {
            throw new \RuntimeException(__('Could not read uploaded file.'));
        }

        $text = match (strtolower($extension)) {
            'txt' => $this->fromPlainText($contents),
            'pdf' => $this->fromPdf($contents),
            'docx' => $this->fromDocx($contents),
            default => throw new \InvalidArgumentException(__('Unsupported file type.')),
        };

        $text = $this->normalize($text);
        if ($text === '') {
            throw new \RuntimeException(__('No text could be extracted from this file.'));
        }

        if (mb_strlen($text) > self::MAX_CHARS) {
            $text = mb_substr($text, 0, self::MAX_CHARS);
        }

        $sidecar = preg_replace('/\.[^.]+$/', '', $diskRelativeOriginalPath).'.extracted.txt';
        Storage::disk('local')->put($sidecar, $text);

        return $sidecar;
    }

    private function fromPlainText(string $raw): string
    {
        return $raw;
    }

    private function fromPdf(string $binary): string
    {
        $parser = new PdfParser;
        $pdf = $parser->parseContent($binary);

        return $pdf->getText();
    }

    private function fromDocx(string $binary): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        if ($tmp === false) {
            throw new \RuntimeException(__('Temporary file error.'));
        }
        file_put_contents($tmp, $binary);
        $zip = new ZipArchive;
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);
            throw new \RuntimeException(__('Could not read DOCX archive.'));
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tmp);

        if ($xml === false) {
            throw new \RuntimeException(__('DOCX document.xml missing.'));
        }

        $xml = preg_replace('/(<w:p[^>]*>)/', "\n$1", $xml);
        $text = strip_tags(str_replace(['</w:p>', '</w:tab>'], ["\n", "\t"], $xml));

        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function normalize(string $text): string
    {
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
