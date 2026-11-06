<?php

declare(strict_types=1);

namespace AStarLab\Tests\Unit;

use AStarLab\Core\Benchmark\GroundTruth;
use AStarLab\Core\Grid\Grid;
use AStarLab\Core\Grid\GridGenerator;
use AStarLab\Core\Grid\SolvabilityChecker;
use AStarLab\Core\Heuristic\ChebyshevHeuristic;
use AStarLab\Core\Heuristic\OctileHeuristic;
use AStarLab\Core\Search\AStar;
use AStarLab\Core\Search\DiagonalCostScheme;
use PHPUnit\Framework\TestCase;

final class SearchTest extends TestCase
{
    public function testHeuristicValuesAndUniformOverestimationRatio(): void
    {
        $octile = new OctileHeuristic();
        $chebyshev = new ChebyshevHeuristic();

        self::assertEqualsWithDelta(140.0071, $octile->estimate(0, 0, 99, 99), 0.0001);
        self::assertSame(99, $chebyshev->estimate(0, 0, 99, 99));
        self::assertEqualsWithDelta(
            sqrt(2.0),
            $octile->estimate(0, 0, 99, 99) / $chebyshev->estimate(0, 0, 99, 99),
            1.0e-9,
        );
    }

    public function testFoundPathIsLegalDeterministicAndHasCorrectCost(): void
    {
        $grid = new Grid(8, 8);
        foreach ([[2, 1], [2, 2], [2, 3], [4, 4], [4, 5]] as [$x, $y]) {
            $grid->setBlocked($x, $y);
        }

        $search = new AStar();
        $first = $search->search($grid, new OctileHeuristic(), DiagonalCostScheme::Geometric);
        $second = $search->search($grid, new OctileHeuristic(), DiagonalCostScheme::Geometric);

        self::assertTrue($first->found);
        self::assertSame($first->path, $second->path);
        self::assertSame($first->cost, $second->cost);
        self::assertSame(0, $first->path[0]);
        self::assertSame(63, $first->path[array_key_last($first->path)]);
        self::assertLessThanOrEqual($grid->size(), $first->expandedNodes);
        self::assertGreaterThanOrEqual(1, $first->peakOpenListSize);
        $visualized = $search->search(
            $grid,
            new OctileHeuristic(),
            DiagonalCostScheme::Geometric,
            collectVisualization: true,
        );
        self::assertCount(count(array_unique($visualized->closedIndices)), $visualized->closedIndices);

        $sum = 0.0;
        for ($position = 1; $position < count($first->path); $position++) {
            $previous = $first->path[$position - 1];
            $current = $first->path[$position];
            self::assertTrue($grid->canStep($previous, $current));
            $diagonal = ($previous % 8) !== ($current % 8)
                && intdiv($previous, 8) !== intdiv($current, 8);
            $sum += $diagonal ? DiagonalCostScheme::Geometric->diagonalCost() : 1.0;
        }
        self::assertEqualsWithDelta($sum, $first->cost, 1.0e-9);
    }

    public function testBothHeapModesAndIndependentBfsAgree(): void
    {
        $grid = (new GridGenerator())->generate(12, 12, 0.15, 44);
        $solvable = (new SolvabilityChecker())->isSolvable($grid);

        foreach ([true, false] as $negatedPriorities) {
            $result = (new AStar($negatedPriorities))->search(
                $grid,
                new ChebyshevHeuristic(),
                DiagonalCostScheme::Geometric,
            );
            self::assertSame($solvable, $result->found);
        }
    }

    public function testBfsAndAStarRejectAPathBlockedByCornerCutting(): void
    {
        $grid = new Grid(3, 3);
        $grid->setBlocked(1, 0);
        $grid->setBlocked(0, 1);

        self::assertFalse((new SolvabilityChecker())->isSolvable($grid));
        self::assertFalse((new AStar())->search(
            $grid,
            new ChebyshevHeuristic(),
            DiagonalCostScheme::Uniform,
        )->found);
    }

    public function testGroundTruthAStarAndDijkstraAgree(): void
    {
        $grid = (new GridGenerator())->generate(15, 15, 0.10, 7);
        $groundTruth = new GroundTruth();
        foreach ([DiagonalCostScheme::Geometric, DiagonalCostScheme::Uniform] as $scheme) {
            $dijkstraCost = $groundTruth->dijkstraCost($grid, $scheme);
            $optimalCost = $groundTruth->optimalCost($grid, $scheme);
            self::assertNotNull($dijkstraCost);
            self::assertNotNull($optimalCost);
            self::assertEqualsWithDelta($dijkstraCost, $optimalCost, 1.0e-9);
        }
    }

    public function testTheoreticalCostBoundsHoldOnSampleMaps(): void
    {
        $generator = new GridGenerator();
        $search = new AStar();
        $groundTruth = new GroundTruth();
        $checker = new SolvabilityChecker();
        $testedMaps = 0;
        for ($seed = 1; $seed <= 20; $seed++) {
            $grid = $generator->generate(12, 12, 0.15, $seed);
            if (!$checker->isSolvable($grid)) {
                continue;
            }
            $testedMaps++;

            $geometricStar = $groundTruth->optimalCost($grid, DiagonalCostScheme::Geometric);
            self::assertNotNull($geometricStar);
            foreach ([new OctileHeuristic(), new ChebyshevHeuristic()] as $heuristic) {
                self::assertEqualsWithDelta(
                    $geometricStar,
                    $search->search($grid, $heuristic, DiagonalCostScheme::Geometric)->cost,
                    1.0e-9,
                );
            }

            $uniformStar = $groundTruth->optimalCost($grid, DiagonalCostScheme::Uniform);
            self::assertNotNull($uniformStar);
            $uniformChebyshev = $search->search($grid, new ChebyshevHeuristic(), DiagonalCostScheme::Uniform)->cost;
            $uniformOctile = $search->search($grid, new OctileHeuristic(), DiagonalCostScheme::Uniform)->cost;
            self::assertEqualsWithDelta($uniformStar, $uniformChebyshev, 1.0e-9);
            self::assertGreaterThanOrEqual($uniformStar - 1.0e-9, $uniformOctile);
            self::assertLessThanOrEqual(sqrt(2.0) * $uniformStar + 1.0e-9, $uniformOctile);
        }
        self::assertGreaterThan(0, $testedMaps, 'The property test must exercise at least one solvable map.');
    }
}
