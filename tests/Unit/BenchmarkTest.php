<?php

declare(strict_types=1);

namespace AStarLab\Tests\Unit;

use AStarLab\Core\Benchmark\ExperimentMatrix;
use AStarLab\Core\Benchmark\Stats;
use PHPUnit\Framework\TestCase;

final class BenchmarkTest extends TestCase
{
    public function testExperimentMatrixContainsThirtySixOrderedScenarios(): void
    {
        $configurations = (new ExperimentMatrix())->configurations();

        self::assertCount(36, $configurations);
        self::assertSame('S01', $configurations[0]['id']);
        self::assertSame('S18', $configurations[17]['id']);
        self::assertSame('S19', $configurations[18]['id']);
        self::assertSame('S36', $configurations[35]['id']);
    }

    public function testStatsReturnsExpectedSampleSummary(): void
    {
        $summary = Stats::summarize([1, 2, 3, 4, 5]);

        self::assertSame(3.0, $summary['mean']);
        self::assertSame(3.0, $summary['median']);
        self::assertSame(1.0, $summary['min']);
        self::assertSame(5.0, $summary['max']);
        self::assertSame(5.0, $summary['p95']);
    }
}
