<?php

declare(strict_types=1);

namespace AStarLab\Core\Heuristic;

final class ChebyshevHeuristic implements HeuristicInterface
{
    public function estimate(int $x, int $y, int $goalX, int $goalY): int
    {
        return max(abs($goalX - $x), abs($goalY - $y));
    }
}
