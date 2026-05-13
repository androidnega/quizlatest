<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AboutController extends Controller
{
    public function __invoke(): View
    {
        /** @var array<int, array{name: string, field: string, avatar: string}> $team */
        $team = config('about.team', []);

        return view('marketing.about', [
            'teamMembers' => is_array($team) ? $team : [],
        ]);
    }
}
