<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('type', 32)->default('mcq');
            $table->json('answer_schema')->nullable();
            $table->json('correct_answer_new')->nullable();
        });

        foreach (DB::table('questions')->orderBy('id')->cursor() as $row) {
            $legacyType = (string) ($row->question_type ?? 'mcq');
            $newType = $legacyType === 'short_answer' ? 'fill_blank' : $legacyType;

            if (! in_array($newType, ['mcq', 'true_false', 'fill_blank', 'essay'], true)) {
                $newType = 'mcq';
            }

            $encoded = $this->migrateCorrectAnswer($newType, $row->correct_answer);

            DB::table('questions')->where('id', $row->id)->update([
                'type' => $newType,
                'correct_answer_new' => $encoded,
            ]);
        }

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['question_type', 'correct_answer']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->renameColumn('correct_answer_new', 'correct_answer');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->enum('question_type', ['mcq', 'true_false', 'short_answer', 'essay'])->default('mcq');
            $table->text('correct_answer_legacy')->nullable();
        });

        foreach (DB::table('questions')->orderBy('id')->cursor() as $row) {
            $legacyType = match ($row->type) {
                'fill_blank' => 'short_answer',
                default => $row->type,
            };
            $text = $row->correct_answer;
            if ($text !== null && $text !== '') {
                $decoded = json_decode((string) $text, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $text = is_array($decoded) ? implode("\n", $decoded) : json_encode($decoded);
                }
            }
            DB::table('questions')->where('id', $row->id)->update([
                'question_type' => $legacyType,
                'correct_answer_legacy' => $text,
            ]);
        }

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['type', 'answer_schema', 'correct_answer']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->renameColumn('correct_answer_legacy', 'correct_answer');
        });
    }

    private function migrateCorrectAnswer(string $newType, mixed $rawCorrect): ?string
    {
        if ($newType === 'essay') {
            return null;
        }

        if ($rawCorrect === null || $rawCorrect === '') {
            return null;
        }

        $raw = (string) $rawCorrect;

        if ($newType === 'mcq') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_int($decoded)) {
                    return json_encode([$decoded]);
                }
                if (is_array($decoded)) {
                    $ints = [];
                    foreach ($decoded as $v) {
                        if (is_int($v) || (is_string($v) && ctype_digit($v))) {
                            $ints[] = (int) $v;
                        }
                    }

                    return $ints === [] ? null : json_encode(array_values(array_unique($ints)));
                }
            }
            if (is_numeric(trim($raw))) {
                return json_encode([(int) $raw]);
            }
            $parts = array_map('trim', explode(',', $raw));
            $ints = [];
            foreach ($parts as $p) {
                if ($p !== '' && ctype_digit($p)) {
                    $ints[] = (int) $p;
                }
            }

            return $ints === [] ? null : json_encode(array_values(array_unique($ints)));
        }

        if ($newType === 'true_false') {
            $v = strtolower(trim($raw));

            return json_encode(in_array($v, ['1', 'true', 'yes'], true));
        }

        if ($newType === 'fill_blank') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return json_encode(array_values(array_map(fn ($s) => $this->normalizeBlank((string) $s), $decoded)));
            }
            $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw)));
            if ($lines === []) {
                return json_encode([$this->normalizeBlank($raw)]);
            }

            return json_encode(array_values(array_map(fn ($s) => $this->normalizeBlank($s), $lines)));
        }

        return null;
    }

    private function normalizeBlank(string $s): string
    {
        return preg_replace('/\s+/', ' ', trim($s)) ?? '';
    }
};
