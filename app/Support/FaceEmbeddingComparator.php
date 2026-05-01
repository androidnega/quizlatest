<?php

namespace App\Support;

class FaceEmbeddingComparator
{
    public static function similarityPercent(array $template, array $probe): float
    {
        $length = min(count($template), count($probe));
        if ($length === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $length; $i++) {
            $a = (float) $template[$i];
            $b = (float) $probe[$i];
            $dot += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        $cosine = $dot / (sqrt($normA) * sqrt($normB));
        $cosine = max(-1.0, min(1.0, $cosine));

        return round((($cosine + 1) / 2) * 100, 2);
    }
}
