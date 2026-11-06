<?php

declare(strict_types=1);

namespace AStarLab\Tests\Unit;

use AStarLab\Core\Grid\Grid;
use AStarLab\Core\Grid\GridGenerator;
use PHPUnit\Framework\TestCase;

final class GridTest extends TestCase
{
    public function testIndexAndCoordinatesAreBijections(): void
    {
        $grid = new Grid(7, 5);
        for ($y = 0; $y < $grid->height(); $y++) {
            for ($x = 0; $x < $grid->width(); $x++) {
                self::assertSame([$x, $y], $grid->coordinates($grid->index($x, $y)));
            }
        }
    }

    public function testDiagonalMovementCannotCutCornersOrLeaveGrid(): void
    {
        $grid = new Grid(3, 3);
        self::assertTrue($grid->canStep(0, 4));
        self::assertTrue($grid->canStep(0, 3));
        self::assertFalse($grid->canStep(0, -1));
        $grid->setBlocked(1, 0);
        self::assertFalse($grid->canStep(0, 4));
        $grid->setBlocked(1, 0, false);
        $grid->setBlocked(0, 1);
        self::assertFalse($grid->canStep(0, 4));
    }

    public function testGeneratorUsesExactDeterministicObstacleCountAndOpenEndpoints(): void
    {
        $generator = new GridGenerator();
        $first = $generator->generate(20, 20, 0.30, 1234);
        $second = $generator->generate(20, 20, 0.30, 1234);

        self::assertSame(120, $first->blockedCount());
        self::assertSame($first->occupancy(), $second->occupancy());
        self::assertFalse($first->isBlockedIndex(0));
        self::assertFalse($first->isBlockedIndex(399));
    }

    public function testRequestedBenchmarkDensitiesProduceExactObstacleCounts(): void
    {
        $generator = new GridGenerator();
        foreach ([20, 50, 100] as $size) {
            foreach ([0.10, 0.30, 0.50] as $density) {
                $grid = $generator->generate($size, $size, $density, 98);
                self::assertSame((int) floor($density * $size * $size), $grid->blockedCount());
            }
        }
    }

    public function testRepairPreservesObstacleCountAndCreatesPath(): void
    {
        $generator = new GridGenerator();
        $grid = $generator->generate(20, 20, 0.30, 50);
        $generator->repair($grid, 0.30);

        self::assertSame(120, $grid->blockedCount());
        self::assertTrue($grid->canStep(18, 19));
        self::assertTrue($grid->canStep(19, 39));
    }
}
