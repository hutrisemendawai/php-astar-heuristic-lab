<?php

declare(strict_types=1);

namespace AStarLab\Game;

final class NpcEntity
{
    private int $pathPosition = 0;

    /** @param list<int> $path */
    public function advance(array $path): ?int
    {
        if ($path === []) {
            return null;
        }

        if ($this->pathPosition < count($path) - 1) {
            $this->pathPosition++;
        }

        return $path[$this->pathPosition];
    }

    public function reset(): void
    {
        $this->pathPosition = 0;
    }
}
