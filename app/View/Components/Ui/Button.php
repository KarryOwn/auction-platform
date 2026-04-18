<?php

namespace App\View\Components\Ui;

use Illuminate\View\Component;
use Illuminate\View\View;

class Button extends Component
{
    public function __construct(
        public string $variant = 'primary',
        public string $size = 'md',
        public bool $loading = false,
        public ?string $href = null,
        public string $type = 'button',
        public bool $disabled = false,
        public ?string $icon = null,
    ) {}

    public function render(): View
    {
        return view('components.ui.button');
    }
}
