<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class QuestionPolicy
{
    public function view(User $user, Question $question): bool
    {
        $question->loadMissing('quiz');

        return Gate::forUser($user)->allows('view', $question->quiz);
    }

    public function update(User $user, Question $question): bool
    {
        $question->loadMissing('quiz');

        return Gate::forUser($user)->allows('update', $question->quiz);
    }

    public function delete(User $user, Question $question): bool
    {
        $question->loadMissing('quiz');

        return Gate::forUser($user)->allows('delete', $question->quiz);
    }
}
