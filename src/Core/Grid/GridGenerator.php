<?php

declare(strict_types=1);

namespace AStarLab\Core\Grid;

use InvalidArgumentException;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

final class GridGenerator
{
    public function generate(int $width, int $height, float $density, int $seed, string $engine = 'xoshiro'): Grid
    {
        if ($density < 0.0 || $density > 1.0) {
            throw new InvalidArgumentException('Density must be between 0 and 1.');
        }

        $grid = new Grid($width, $height);
        $obstacleCount = (int) floor($density * $grid->size());
        $candidateCount = $grid->size() - 2;
        if ($obstacleCount > $candidateCount) {
            throw new InvalidArgumentException('Density leaves too few cells for start and goal.');
        }

        if ($obstacleCount === 0) {
            return $grid;
        }

        $candidates = array_fill_keys(range(1, $grid->size() - 2), true);
        $randomizer = new Randomizer(match ($engine) {
            'xoshiro' => new Xoshiro256StarStar($seed),
            'mt19937' => new \Random\Engine\Mt19937($seed),
            default => throw new InvalidArgumentException('Engine must be xoshiro or mt19937.'),
        });
        foreach ($randomizer->pickArrayKeys($candidates, $obstacleCount) as $index) {
            $grid->setBlocked($index % $width, intdiv($index, $width));
        }

        return $grid;
    }

    public function repair(Grid $grid, float $density): void
    {
        $width = $grid->width();
        $height = $grid->height();
        $path = [];
        for ($x = 0; $x < $width; $x++) {
            $path[] = $grid->index($x, 0);
        }
        for ($y = 1; $y < $height; $y++) {
            $path[] = $grid->index($width - 1, $y);
        }

        $pathSet = array_fill_keys($path, true);
        $obstaclesToMove = [];
        foreach ($path as $index) {
            if ($grid->isBlockedIndex($index)) {
                $obstaclesToMove[] = $index;
                $grid->setBlocked($index % $width, intdiv($index, $width), false);
            }
        }

        $targetCount = (int) floor($density * $grid->size());
        $available = [];
        for ($index = 1; $index < $grid->size() - 1; $index++) {
            if (!isset($pathSet[$index]) && !$grid->isBlockedIndex($index)) {
                $available[] = $index;
            }
        }

        if (count($available) < count($obstaclesToMove) || $grid->blockedCount() + count($obstaclesToMove) !== $targetCount) {
            throw new InvalidArgumentException('The requested density cannot be repaired while preserving obstacle count.');
        }

        $relocated = array_slice($available, 0, count($obstaclesToMove));
        foreach ($relocated as $index) {
            $grid->setBlocked($index % $width, intdiv($index, $width));
        }
    }
}
