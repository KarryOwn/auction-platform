<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ApiException extends HttpException
{
    public function __construct(
        int $statusCode,
        string $message,
        protected array $details = [],
    ) {
        parent::__construct($statusCode, $message);
    }

    public function details(): array
    {
        return $this->details;
    }

    public function render(Request $request): JsonResponse
    {
        $payload = [
            'error' => $this->getMessage(),
            'code' => $this->getStatusCode(),
        ];

        if ($this->details !== []) {
            $payload['details'] = $this->details;
        }

        return response()->json($payload, $this->getStatusCode());
    }

    public static function forbidden(string $message, array $details = []): self
    {
        return new self(403, $message, $details);
    }
}
