<?php

declare(strict_types=1);

namespace AStarLab\Core\Grid;

use InvalidArgumentException;
use OutOfBoundsException;

final class Grid
{
    /** @var list<bool> */
    private array $blocked;

    public function __construct(
        private readonly int $width,
        private readonly int $height,
    ) {
        if ($width < 2 || $height < 2) {
            throw new InvalidArgumentException('Grid dimensions must both be at least 2.');
        }

        $this->blocked = array_fill(0, $width * $height, false);
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function size(): int
    {
        return $this->width * $this->height;
    }

    public function index(int $x, int $y): int
    {
        if ($x < 0 || $x >= $this->width || $y < 0 || $y >= $this->height) {
            throw new OutOfBoundsException('Grid coordinates are outside the grid.');
        }

        return $y * $this->width + $x;
    }

    /** @return array{int, int} */
    public function coordinates(int $index): array
    {
        if ($index < 0 || $index >= $this->size()) {
            throw new OutOfBoundsException('Grid index is outside the grid.');
        }

        return [$index % $this->width, intdiv($index, $this->width)];
    }

    public function isBlocked(int $x, int $y): bool
    {
        return $this->blocked[$this->index($x, $y)];
    }

    public function isBlockedIndex(int $index): bool
    {
        if ($index < 0 || $index >= $this->size()) {
            throw new OutOfBoundsException('Grid index is outside the grid.');
        }

        return $this->blocked[$index];
    }

    public function setBlocked(int $x, int $y, bool $blocked = true): void
    {
        $index = $this->index($x, $y);
        if (($index === 0 || $index === $this->size() - 1) && $blocked) {
            throw new InvalidArgumentException('Start and goal cells must remain open.');
        }

        $this->blocked[$index] = $blocked;
    }

    public function canStep(int $from, int $to): bool
    {
        if (
            $from < 0 || $from >= $this->size()
            || $to < 0 || $to >= $this->size()
            || $this->blocked[$from]
            || $this->blocked[$to]
        ) {
            return false;
        }

        $fromX = $from % $this->width;
        $fromY = intdiv($from, $this->width);
        $toX = $to % $this->width;
        $toY = intdiv($to, $this->width);
        $dx = $toX - $fromX;
        $dy = $toY - $fromY;

        if (($dx === 0 && $dy === 0) || abs($dx) > 1 || abs($dy) > 1) {
            return false;
        }

        if ($dx !== 0 && $dy !== 0) {
            return !$this->blocked[$fromY * $this->width + $toX]
                && !$this->blocked[$toY * $this->width + $fromX];
        }

        return true;
    }

    /** @return list<bool> */
    public function occupancy(): array
    {
        return $this->blocked;
    }

    public function blockedCount(): int
    {
        return count(array_filter($this->blocked));
    }
}
