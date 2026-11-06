<?php

declare(strict_types=1);

namespace AStarLab\Core\Search;

enum DiagonalCostScheme: string
{
    case Geometric = 'geometric';
    case Uniform = 'uniform';

    public function diagonalCost(): float
    {
        return $this === self::Geometric ? 1.4142135623730951 : 1.0;
    }
}
