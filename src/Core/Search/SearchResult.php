<?php

declare(strict_types=1);

namespace AStarLab\Core\Search;

final readonly class SearchResult
{
    /** @param list<int> $path
     *  @param list<int> $closedIndices
     *  @param list<int> $openIndices
     */
    public function __construct(
        public bool $found,
        public array $path,
        public ?float $cost,
        public int $expandedNodes,
        public int $peakOpenListSize,
        public int $pushCount,
        public float $timeMicros,
        public array $closedIndices = [],
        public array $openIndices = [],
    ) {
    }
}
