<?php

declare(strict_types=1);

namespace AStarLab\Core\Search;

use AStarLab\Core\Grid\Grid;
use AStarLab\Core\Heuristic\HeuristicInterface;
use AStarLab\Core\Support\MinPriorityQueue;
use SplPriorityQueue;
use InvalidArgumentException;

final class AStar
{
    private const EPSILON = 1.0e-9;

    private const DIRECTION_X = [-1, 0, 1, -1, 1, -1, 0, 1];

    private const DIRECTION_Y = [-1, -1, -1, 0, 0, 1, 1, 1];

    public function __construct(
        private readonly bool $negatedPriorities = true,
        private readonly string $tieBreak = 'insertionOrder',
    ) {
        if (!in_array($tieBreak, ['none', 'insertionOrder'], true)) {
            throw new InvalidArgumentException('Tie break must be none or insertionOrder.');
        }
    }

    public function search(
        Grid $grid,
        HeuristicInterface $heuristic,
        DiagonalCostScheme $scheme,
        int $start = 0,
        ?int $goal = null,
        bool $collectVisualization = false,
    ): SearchResult {
        $goal ??= $grid->size() - 1;
        if ($start < 0 || $start >= $grid->size() || $goal < 0 || $goal >= $grid->size()) {
            throw new InvalidArgumentException('Start and goal must be valid grid indices.');
        }

        $width = $grid->width();
        $height = $grid->height();
        $blocked = $grid->occupancy();
        $startX = $start % $width;
        $startY = intdiv($start, $width);
        $goalX = $goal % $width;
        $goalY = intdiv($goal, $width);
        $startedAt = hrtime(true);
        $gScore = array_fill(0, $grid->size(), INF);
        $cameFrom = array_fill(0, $grid->size(), -1);
        $closedSet = array_fill(0, $grid->size(), false);
        $gScore[$start] = 0.0;
        $open = $this->negatedPriorities ? new SplPriorityQueue() : new MinPriorityQueue();
        $open->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        $sequence = 0;
        $pushCount = 0;
        $peakOpenListSize = 0;
        $expandedNodes = 0;
        $closedIndices = [];

        if ($blocked[$start] || $blocked[$goal]) {
            $elapsed = (hrtime(true) - $startedAt) / 1000;

            return new SearchResult(false, [], null, 0, 0, 0, $elapsed);
        }

        $open->insert(
            ['index' => $start, 'g' => 0.0],
            $this->priority((float) $heuristic->estimate($startX, $startY, $goalX, $goalY), $sequence),
        );
        $sequence++;
        $pushCount++;
        $peakOpenListSize = count($open);

        while (!$open->isEmpty()) {
            /** @var array{data: array{index: int, g: float}, priority: mixed} $entry */
            $entry = $open->extract();
            $current = $entry['data']['index'];
            $currentG = $entry['data']['g'];
            if ($closedSet[$current] || $currentG > $gScore[$current] + self::EPSILON) {
                continue;
            }

            $closedSet[$current] = true;
            $expandedNodes++;
            if ($collectVisualization) {
                $closedIndices[] = $current;
            }
            if ($current === $goal) {
                $path = $this->reconstructPath($cameFrom, $goal);
                $openIndices = $collectVisualization ? $this->openIndices($open, $gScore, $closedSet) : [];
                $elapsed = (hrtime(true) - $startedAt) / 1000;

                return new SearchResult(
                    true,
                    $path,
                    $gScore[$goal],
                    $expandedNodes,
                    $peakOpenListSize,
                    $pushCount,
                    $elapsed,
                    $closedIndices,
                    $openIndices,
                );
            }

            $x = $current % $width;
            $y = intdiv($current, $width);
            for ($direction = 0; $direction < 8; $direction++) {
                $dx = self::DIRECTION_X[$direction];
                $dy = self::DIRECTION_Y[$direction];
                $nextX = $x + $dx;
                $nextY = $y + $dy;
                if ($nextX < 0 || $nextX >= $width || $nextY < 0 || $nextY >= $height) {
                    continue;
                }

                $next = $nextY * $width + $nextX;
                if ($closedSet[$next] || $blocked[$next]) {
                    continue;
                }
                if ($dx !== 0 && $dy !== 0 && ($blocked[$y * $width + $nextX] || $blocked[$nextY * $width + $x])) {
                    continue;
                }

                $stepCost = $dx !== 0 && $dy !== 0 ? $scheme->diagonalCost() : 1.0;
                $tentativeG = $gScore[$current] + $stepCost;
                if ($tentativeG + self::EPSILON >= $gScore[$next]) {
                    continue;
                }

                $cameFrom[$next] = $current;
                $gScore[$next] = $tentativeG;
                $estimate = $heuristic->estimate($nextX, $nextY, $goalX, $goalY);
                $this->insert($open, $next, $tentativeG, $tentativeG + $estimate, $sequence);
                $pushCount++;
                $peakOpenListSize = max($peakOpenListSize, count($open));
            }
        }

        $openIndices = $collectVisualization ? $this->openIndices($open, $gScore, $closedSet) : [];
        $elapsed = (hrtime(true) - $startedAt) / 1000;

        return new SearchResult(false, [], null, $expandedNodes, $peakOpenListSize, $pushCount, $elapsed, $closedIndices, $openIndices);
    }

    private function insert(
        SplPriorityQueue $open,
        int $index,
        float $gScore,
        float $fScore,
        int &$sequence,
    ): void {
        $data = ['index' => $index, 'g' => $gScore];
        $priority = $this->priority($fScore, $sequence);
        $open->insert($data, $priority);
        $sequence++;
    }

    private function priority(float $fScore, int $sequence): float|array
    {
        if ($this->negatedPriorities) {
            return $this->tieBreak === 'insertionOrder'
                ? [-$fScore, -$sequence]
                : -$fScore;
        }

        return $this->tieBreak === 'insertionOrder'
            ? [$fScore, $sequence]
            : [$fScore];
    }

    /** @param list<int> $cameFrom
     *  @return list<int>
     */
    private function reconstructPath(array $cameFrom, int $goal): array
    {
        $path = [];
        for ($current = $goal; $current !== -1; $current = $cameFrom[$current]) {
            $path[] = $current;
        }

        return array_reverse($path);
    }

    /** @return list<int> */
    private function openIndices(SplPriorityQueue $open, array $gScore, array $closedSet): array
    {
        $nodes = [];
        $snapshot = clone $open;
        while (!$snapshot->isEmpty()) {
            /** @var array{data: array{index: int, g: float}, priority: mixed} $entry */
            $entry = $snapshot->extract();
            $index = $entry['data']['index'];
            if (!$closedSet[$index] && $entry['data']['g'] <= $gScore[$index] + self::EPSILON) {
                $nodes[$index] = $index;
            }
        }

        return array_values($nodes);
    }
}
