<?php

declare(strict_types=1);

namespace AStarLab\Core\Grid;

final class SolvabilityChecker
{
    private const DIRECTIONS = [
        [-1, -1],
        [0, -1],
        [1, -1],
        [-1, 0],
        [1, 0],
        [-1, 1],
        [0, 1],
        [1, 1],
    ];

    public function isSolvable(Grid $grid): bool
    {
        $goal = $grid->size() - 1;
        $visited = array_fill(0, $grid->size(), false);
        $visited[0] = true;
        $queue = new \SplQueue();
        $queue->enqueue(0);

        while (!$queue->isEmpty()) {
            $current = $queue->dequeue();
            if ($current === $goal) {
                return true;
            }

            $x = $current % $grid->width();
            $y = intdiv($current, $grid->width());
            foreach (self::DIRECTIONS as [$dx, $dy]) {
                $nextX = $x + $dx;
                $nextY = $y + $dy;
                if ($nextX < 0 || $nextX >= $grid->width() || $nextY < 0 || $nextY >= $grid->height()) {
                    continue;
                }

                $next = $nextY * $grid->width() + $nextX;
                if ($visited[$next] || $grid->isBlockedIndex($next)) {
                    continue;
                }

                if ($dx !== 0 && $dy !== 0) {
                    if ($grid->isBlocked($nextX, $y) || $grid->isBlocked($x, $nextY)) {
                        continue;
                    }
                }

                $visited[$next] = true;
                $queue->enqueue($next);
            }
        }

        return false;
    }
}
