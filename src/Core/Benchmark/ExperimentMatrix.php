<?php

declare(strict_types=1);

namespace AStarLab\Core\Benchmark;

use AStarLab\Core\Search\DiagonalCostScheme;

final class ExperimentMatrix
{
    /** @return list<array{id: string, size: int, density: float, heuristic: string, scheme: DiagonalCostScheme}> */
    public function configurations(): array
    {
        $configurations = [];
        $scenario = 1;
        foreach ([DiagonalCostScheme::Geometric, DiagonalCostScheme::Uniform] as $scheme) {
            foreach ([20, 50, 100] as $size) {
                foreach ([0.10, 0.30, 0.50] as $density) {
                    foreach (['octile', 'chebyshev'] as $heuristic) {
                        $configurations[] = [
                            'id' => sprintf('S%02d', $scenario++),
                            'size' => $size,
                            'density' => $density,
                            'heuristic' => $heuristic,
                            'scheme' => $scheme,
                        ];
                    }
                }
            }
        }

        return $configurations;
    }
}
