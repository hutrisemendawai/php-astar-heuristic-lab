<?php

declare(strict_types=1);

namespace AStarLab\Tests\Integration;

use AStarLab\Core\Benchmark\GroundTruth;
use AStarLab\Core\Grid\GridGenerator;
use AStarLab\Core\Grid\SolvabilityChecker;
use AStarLab\Core\Heuristic\ChebyshevHeuristic;
use AStarLab\Core\Heuristic\OctileHeuristic;
use AStarLab\Core\Search\AStar;
use AStarLab\Core\Search\DiagonalCostScheme;
use PHPUnit\Framework\TestCase;

final class HeuristicComparisonTest extends TestCase
{
    public function testPairedSearchesRespectBothCostModels(): void
    {
        $grid = (new GridGenerator())->generate(20, 20, 0.10, 1000);
        self::assertTrue((new SolvabilityChecker())->isSolvable($grid));
        $astar = new AStar();
        $groundTruth = new GroundTruth();
        foreach ([DiagonalCostScheme::Geometric, DiagonalCostScheme::Uniform] as $scheme) {
            $optimalCost = $groundTruth->dijkstraCost($grid, $scheme);
            self::assertNotNull($optimalCost);
            foreach ([new OctileHeuristic(), new ChebyshevHeuristic()] as $heuristic) {
                $result = $astar->search($grid, $heuristic, $scheme);
                self::assertTrue($result->found);
                if ($scheme === DiagonalCostScheme::Geometric || $heuristic instanceof ChebyshevHeuristic) {
                    self::assertEqualsWithDelta($optimalCost, $result->cost, 1.0e-9);
                } else {
                    self::assertGreaterThanOrEqual($optimalCost - 1.0e-9, $result->cost);
                    self::assertLessThanOrEqual(sqrt(2.0) * $optimalCost + 1.0e-9, $result->cost);
                }
            }
        }
    }
}
