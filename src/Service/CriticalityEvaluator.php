<?php

declare(strict_types=1);

namespace App\Service;

final class CriticalityEvaluator
{
    public function isCritical(
        string $type,
        float|int $value
    ): bool {
        return match ($type) {
            'temperature' => $value > 39,
            'heart_rate' => $value > 120,
            'oxygen_saturation' => $value < 90,
            default => false,
        };
    }
}