<?php

namespace App\Services;

/**
 * Picks candidate topic lines from plain outline text (PDF/TXT/DOCX extraction) without calling an LLM.
 *
 * @return list<string>
 */
final class OutlineTopicSuggester
{
    /**
     * @return list<string>
     */
    public function suggestFromPlainText(string $text, int $max = 25): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $text = mb_substr($text, 0, 120_000);
        $lines = preg_split('/\R+/u', $text) ?: [];
        $candidates = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            $line = preg_replace('/^[\s\-*•\t]+/u', '', $line) ?? $line;
            $line = preg_replace('/^\d+[\.\)]\s*/u', '', $line) ?? $line;
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $len = mb_strlen($line);
            if ($len < 6 || $len > 200) {
                continue;
            }

            if (preg_match('#^https?://#i', $line)) {
                continue;
            }

            if (preg_match('/^page\s+\d+/iu', $line)) {
                continue;
            }

            $candidates[] = $line;
        }

        $candidates = array_values(array_unique($candidates));

        return array_slice($candidates, 0, max(1, min(50, $max)));
    }
}
