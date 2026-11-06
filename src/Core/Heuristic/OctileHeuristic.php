<?php

declare(strict_types=1);

namespace AStarLab\Core\Heuristic;

final class OctileHeuristic implements HeuristicInterface
{
    private const OCTILE_FACTOR = 0.41421356237309505;

    public function estimate(int $x, int $y, int $goalX, int $goalY): float
    {
        $dx = abs($goalX - $x);
        $dy = abs($goalY - $y);
        $maximum = max($dx, $dy);
        $minimum = min($dx, $dy);

        return $maximum + self::OCTILE_FACTOR * $minimum;
    }
}
