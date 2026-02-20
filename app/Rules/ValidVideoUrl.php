<?php

namespace App\Rules;

use App\Services\VideoEmbedService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidVideoUrl implements ValidationRule
{
    public function __construct(private readonly VideoEmbedService $videoEmbedService) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || $this->videoEmbedService->parse($value) === null) {
            $fail('Please provide a valid YouTube or Vimeo URL');
        }
    }
}
