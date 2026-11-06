<?php

declare(strict_types=1);

namespace AStarLab\Core\Benchmark;

use AStarLab\Core\Grid\Grid;
use AStarLab\Core\Heuristic\ChebyshevHeuristic;
use AStarLab\Core\Heuristic\HeuristicInterface;
use AStarLab\Core\Heuristic\OctileHeuristic;
use AStarLab\Core\Search\AStar;
use AStarLab\Core\Search\DiagonalCostScheme;

final class GroundTruth
{
    public function optimalCost(Grid $grid, DiagonalCostScheme $scheme): ?float
    {
        $heuristic = $scheme === DiagonalCostScheme::Geometric
            ? new OctileHeuristic()
            : new ChebyshevHeuristic();
        $result = (new AStar())->search($grid, $heuristic, $scheme);

        return $result->cost;
    }

    public function dijkstraCost(Grid $grid, DiagonalCostScheme $scheme): ?float
    {
        $zero = new class implements HeuristicInterface {
            public function estimate(int $x, int $y, int $goalX, int $goalY): float
            {
                return 0.0;
            }
        };
        $result = (new AStar())->search($grid, $zero, $scheme);

        return $result->cost;
    }
}
