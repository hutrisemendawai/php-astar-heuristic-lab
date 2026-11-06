<?php

declare(strict_types=1);

namespace AStarLab\Core\Support;

use SplPriorityQueue;

final class MinPriorityQueue extends SplPriorityQueue
{
    public function compare(mixed $priority1, mixed $priority2): int
    {
        if (!is_array($priority1) || !is_array($priority2)) {
            return $priority2 <=> $priority1;
        }

        $length = min(count($priority1), count($priority2));
        for ($index = 0; $index < $length; $index++) {
            $comparison = $priority2[$index] <=> $priority1[$index];
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return count($priority2) <=> count($priority1);
    }
}
