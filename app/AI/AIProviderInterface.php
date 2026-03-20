<?php

declare(strict_types=1);

namespace App\AI;

interface AIProviderInterface
{
    public function respond(string $prompt, array $context = []): string;
}