<?php

declare(strict_types=1);

namespace AStarLab\Core\Heuristic;

interface HeuristicInterface
{
    public function estimate(int $x, int $y, int $goalX, int $goalY): int|float;
}
