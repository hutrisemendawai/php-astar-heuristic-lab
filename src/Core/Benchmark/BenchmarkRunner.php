<?php

declare(strict_types=1);

namespace AStarLab\Core\Benchmark;

use AStarLab\Core\Grid\Grid;
use AStarLab\Core\Grid\GridGenerator;
use AStarLab\Core\Grid\SolvabilityChecker;
use AStarLab\Core\Heuristic\ChebyshevHeuristic;
use AStarLab\Core\Heuristic\OctileHeuristic;
use AStarLab\Core\Search\AStar;
use AStarLab\Core\Search\DiagonalCostScheme;
use RuntimeException;

final class BenchmarkRunner
{
    /**
     * @return array{
     *     raw: list<array<string, string|int|float|null>>,
     *     geometric: list<array<string, string|int|float|null>>,
     *     uniform: list<array<string, string|int|float|null>>,
     *     seeds: array<string, array{accepted: list<int>, rejected: int}>,
     *     statistics: array<string, array<string, float>>
     * }
     */
    public function run(
        int $seedCount,
        int $warmup,
        int $iterations,
        int $baseSeed,
        string $mapStrategy = 'pre-verified',
        string $engine = 'xoshiro',
        bool $negatedPriorities = true,
        string $tieBreak = 'insertionOrder',
    ): array {
        if ($seedCount < 1 || $warmup < 0 || $iterations < 1) {
            throw new RuntimeException('Seed count and iterations must be positive; warm-up cannot be negative.');
        }
        if (!in_array($mapStrategy, ['pre-verified', 'repair'], true)) {
            throw new RuntimeException('Map strategy must be pre-verified or repair.');
        }

        $generator = new GridGenerator();
        $checker = new SolvabilityChecker();
        $groundTruth = new GroundTruth();
        $matrix = new ExperimentMatrix();
        $search = new AStar($negatedPriorities, $tieBreak);
        $raw = [];
        $seeds = [];
        $statistics = [];
        $summaries = [];
        $heuristics = [
            'octile' => new OctileHeuristic(),
            'chebyshev' => new ChebyshevHeuristic(),
        ];

        foreach ([20, 50, 100] as $size) {
            foreach ([0.10, 0.30, 0.50] as $density) {
                $key = sprintf('%d@%d%%', $size, (int) round($density * 100));
                $maps = [];
                $acceptedSeeds = [];
                $rejectedSeeds = 0;
                $candidateSeed = $baseSeed;

                while (count($maps) < $seedCount) {
                    $grid = $generator->generate($size, $size, $density, $candidateSeed, $engine);
                    if ($mapStrategy === 'repair') {
                        $generator->repair($grid, $density);
                    }

                    if ($checker->isSolvable($grid)) {
                        $maps[] = ['seed' => $candidateSeed, 'grid' => $grid];
                        $acceptedSeeds[] = $candidateSeed;
                    } else {
                        $rejectedSeeds++;
                    }
                    $candidateSeed++;
                }

                $seeds[$key] = ['accepted' => $acceptedSeeds, 'rejected' => $rejectedSeeds];
                $scenarioConfigurations = array_values(array_filter(
                    $matrix->configurations(),
                    static fn (array $configuration): bool => $configuration['size'] === $size
                        && $configuration['density'] === $density,
                ));

                foreach ($maps as $mapIndex => $map) {
                    /** @var Grid $grid */
                    $grid = $map['grid'];
                    $costs = [];
                    foreach ([DiagonalCostScheme::Geometric, DiagonalCostScheme::Uniform] as $scheme) {
                        $optimal = $groundTruth->optimalCost($grid, $scheme);
                        if ($optimal === null) {
                            throw new RuntimeException('BFS and A* disagree about map solvability.');
                        }
                        $costs[$scheme->value] = $optimal;
                    }

                    $order = ($mapIndex % 2 === 0) ? ['octile', 'chebyshev'] : ['chebyshev', 'octile'];
                    foreach ([DiagonalCostScheme::Geometric, DiagonalCostScheme::Uniform] as $scheme) {
                        foreach ($order as $heuristicName) {
                            $configuration = $this->configuration(
                                $scenarioConfigurations,
                                $scheme,
                                $heuristicName,
                            );

                            for ($iteration = 0; $iteration < $warmup; $iteration++) {
                                $search->search($grid, $heuristics[$heuristicName], $scheme);
                            }

                            $times = [];
                            $expansions = [];
                            $peaks = [];
                            $pathCosts = [];
                            for ($iteration = 0; $iteration < $iterations; $iteration++) {
                                $result = $search->search($grid, $heuristics[$heuristicName], $scheme);
                                if (!$result->found || $result->cost === null) {
                                    throw new RuntimeException('A* failed on a map verified solvable by BFS.');
                                }
                                $times[] = $result->timeMicros;
                                $expansions[] = $result->expandedNodes;
                                $peaks[] = $result->peakOpenListSize;
                                $pathCosts[] = $result->cost;
                            }

                            $timeStats = Stats::summarize($times);
                            $gap = ($pathCosts[0] - $costs[$scheme->value]) / $costs[$scheme->value] * 100;
                            $this->assertCostBound($scheme, $heuristicName, $pathCosts[0], $costs[$scheme->value]);
                            $measurement = [
                                'scenario_id' => $configuration['id'],
                                'size' => $size,
                                'density' => $density,
                                'heuristic' => $heuristicName,
                                'scheme' => $scheme->value,
                                'seed' => $map['seed'],
                                'T_exec_median_us' => $timeStats['median'],
                                'N_exp' => Stats::median($expansions),
                                'M_peak' => Stats::median($peaks),
                                'C_path' => Stats::median($pathCosts),
                                'C_star' => $costs[$scheme->value],
                                'costGapPct' => $gap,
                            ];
                            $raw[] = $measurement;
                            $groupKey = $configuration['id'];
                            $summaries[$groupKey]['configuration'] = $configuration;
                            foreach ($measurement as $field => $value) {
                                if (in_array($field, ['T_exec_median_us', 'N_exp', 'M_peak', 'C_path', 'costGapPct'], true)) {
                                    $summaries[$groupKey]['values'][$field][] = (float) $value;
                                }
                            }

                            $statistics[$groupKey][] = $timeStats['median'];
                        }
                    }
                    gc_collect_cycles();
                }
            }
        }

        $scenarioSummaries = [];
        foreach ($summaries as $id => $summary) {
            $configuration = $summary['configuration'];
            $values = $summary['values'];
            $timeStats = Stats::summarize($statistics[$id]);
            $scenarioSummaries[$id] = [
                'id' => $id,
                'size' => $configuration['size'],
                'density' => $configuration['density'],
                'heuristic' => ucfirst($configuration['heuristic']),
                'scheme' => $configuration['scheme']->value,
                'T_exec_median_us' => $timeStats['mean'],
                'N_exp' => Stats::summarize($values['N_exp'])['mean'],
                'N_exp_max' => Stats::summarize($values['N_exp'])['max'],
                'M_peak' => Stats::summarize($values['M_peak'])['mean'],
                'M_peak_max' => Stats::summarize($values['M_peak'])['max'],
                'C_path' => Stats::summarize($values['C_path'])['mean'],
                'costGapPct' => Stats::summarize($values['costGapPct'])['mean'],
                'costGapPct_max' => Stats::summarize($values['costGapPct'])['max'],
            ];
            $statistics[$id] = $timeStats;
        }

        foreach ([DiagonalCostScheme::Geometric, DiagonalCostScheme::Uniform] as $scheme) {
            foreach ([20, 50, 100] as $size) {
                foreach ([10, 30, 50] as $densityPercent) {
                    $scenario = $this->findScenario($scenarioSummaries, $scheme, $size, $densityPercent, 'octile');
                    $chebyshev = $this->findScenario($scenarioSummaries, $scheme, $size, $densityPercent, 'chebyshev');
                    $denominator = $chebyshev['N_exp'];
                    $delta = $denominator === 0.0 ? 0.0 : ($denominator - $scenario['N_exp']) / $denominator * 100;
                    $scenarioSummaries[$scenario['id']]['deltaNExpPct'] = $delta;
                    $scenarioSummaries[$chebyshev['id']]['deltaNExpPct'] = $delta;
                    if ($scheme === DiagonalCostScheme::Geometric && $scenario['N_exp'] > $chebyshev['N_exp']) {
                        throw new RuntimeException('Expected mean N_exp(Chebyshev) >= N_exp(Octile) for Geometric cost.');
                    }
                }
            }
        }

        $geometric = [];
        $uniform = [];
        foreach ($scenarioSummaries as $summary) {
            if ($summary['scheme'] === DiagonalCostScheme::Geometric->value) {
                $geometric[] = $summary;
            } else {
                $uniform[] = $summary;
            }
        }

        return [
            'raw' => $raw,
            'geometric' => $geometric,
            'uniform' => $uniform,
            'seeds' => $seeds,
            'statistics' => $statistics,
        ];
    }

    /** @param list<array{id: string, size: int, density: float, heuristic: string, scheme: DiagonalCostScheme}> $configurations
     *  @return array{id: string, size: int, density: float, heuristic: string, scheme: DiagonalCostScheme}
     */
    private function configuration(array $configurations, DiagonalCostScheme $scheme, string $heuristic): array
    {
        foreach ($configurations as $configuration) {
            if ($configuration['scheme'] === $scheme && $configuration['heuristic'] === $heuristic) {
                return $configuration;
            }
        }

        throw new RuntimeException('Experiment matrix is missing a requested scenario.');
    }

    private function assertCostBound(
        DiagonalCostScheme $scheme,
        string $heuristic,
        float $pathCost,
        float $optimalCost,
    ): void {
        $gap = $pathCost - $optimalCost;
        if ($scheme === DiagonalCostScheme::Geometric && abs($gap) > 1.0e-9) {
            throw new RuntimeException('Geometric cost must be optimal for both heuristics.');
        }
        if ($scheme === DiagonalCostScheme::Uniform && $heuristic === 'chebyshev' && abs($gap) > 1.0e-9) {
            throw new RuntimeException('Chebyshev must be optimal for Uniform cost.');
        }
        if ($scheme === DiagonalCostScheme::Uniform && $heuristic === 'octile') {
            if ($gap < -1.0e-9 || $pathCost > sqrt(2.0) * $optimalCost + 1.0e-9) {
                throw new RuntimeException('Uniform/Octile cost exceeded its theoretical suboptimality bound.');
            }
        }
    }

    /** @param array<string, array<string, string|int|float>> $summaries
     *  @return array<string, string|int|float>
     */
    private function findScenario(
        array $summaries,
        DiagonalCostScheme $scheme,
        int $size,
        int $densityPercent,
        string $heuristic,
    ): array {
        foreach ($summaries as $summary) {
            if (
                $summary['scheme'] === $scheme->value
                && $summary['size'] === $size
                && (int) round((float) $summary['density'] * 100) === $densityPercent
                && strtolower((string) $summary['heuristic']) === $heuristic
            ) {
                return $summary;
            }
        }

        throw new RuntimeException('Experiment summary is missing a scenario.');
    }
}
