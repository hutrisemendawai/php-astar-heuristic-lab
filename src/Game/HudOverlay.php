<?php

declare(strict_types=1);

namespace AStarLab\Game;

use AStarLab\Core\Search\SearchResult;
use VISU\OS\Window;

final class HudOverlay
{
    public function updateTitle(
        Window $window,
        string $heuristic,
        string $scheme,
        int $size,
        float $density,
        int $seed,
        ?SearchResult $result,
        ?float $optimalCost,
        bool $jitEnabled,
    ): void {
        $gap = $result?->cost !== null && $optimalCost !== null && $optimalCost > 0.0
            ? ($result->cost - $optimalCost) / $optimalCost * 100
            : null;
        $metrics = $result === null
            ? 'Search: Space'
            : sprintf(
                'T %.1fµs | N %d | M %d | C %s | C* %s | gap %s%%',
                $result->timeMicros,
                $result->expandedNodes,
                $result->peakOpenListSize,
                $result->cost === null ? '-' : number_format($result->cost, 3),
                $optimalCost === null ? '-' : number_format($optimalCost, 3),
                $gap === null ? '-' : number_format($gap, 3),
            );
        $window->setTitle(sprintf(
            'A* Lab | %s %s | %dx%d %.0f%% seed %d | JIT %s | %s',
            $heuristic,
            $scheme,
            $size,
            $size,
            $density * 100,
            $seed,
            $jitEnabled ? 'on' : 'off',
            $metrics,
        ));
    }
}
