<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class GuestLayout extends Component
{
    public function __construct(
        public ?string $heading = null,
        public ?string $description = null,
        public ?string $pageTitle = null,
        public ?string $eyebrow = null,
        public string $contentMax = 'max-w-md',
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.guest');
    }
}
