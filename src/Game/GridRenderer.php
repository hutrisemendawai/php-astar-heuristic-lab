<?php

declare(strict_types=1);

namespace AStarLab\Game;

use AStarLab\Core\Grid\Grid;

final class GridRenderer
{
    /**
     * @param list<int> $path
     * @param list<int> $openIndices
     * @param list<int> $closedIndices
     */
    public function draw(
        Grid $grid,
        int $windowWidth,
        int $windowHeight,
        array $path,
        array $openIndices,
        array $closedIndices,
        int $offsetX = 0,
        ?int $viewportWidth = null,
        ?int $npcIndex = null,
    ): void {
        $viewportWidth ??= $windowWidth;
        $cellSize = max(1, min(intdiv($viewportWidth, $grid->width()), intdiv($windowHeight, $grid->height())));
        $originX = $offsetX + intdiv($viewportWidth - $cellSize * $grid->width(), 2);
        $originY = intdiv($windowHeight - $cellSize * $grid->height(), 2);
        $pathSet = array_fill_keys($path, true);
        $openSet = array_fill_keys($openIndices, true);
        $closedSet = array_fill_keys($closedIndices, true);

        glEnable(GL_SCISSOR_TEST);
        for ($index = 0; $index < $grid->size(); $index++) {
            $x = $index % $grid->width();
            $y = intdiv($index, $grid->width());
            glScissor(
                $originX + $x * $cellSize + 1,
                $windowHeight - ($originY + ($y + 1) * $cellSize) + 1,
                max(1, $cellSize - 2),
                max(1, $cellSize - 2),
            );
            if ($index === $npcIndex) {
                glClearColor(1.0, 0.9, 0.1, 1.0);
            } elseif ($index === 0) {
                glClearColor(0.1, 0.8, 0.2, 1.0);
            } elseif ($index === $grid->size() - 1) {
                glClearColor(0.9, 0.1, 0.1, 1.0);
            } elseif ($grid->isBlockedIndex($index)) {
                glClearColor(0.12, 0.12, 0.15, 1.0);
            } elseif (isset($pathSet[$index])) {
                glClearColor(0.1, 0.65, 0.95, 1.0);
            } elseif (isset($openSet[$index])) {
                glClearColor(0.3, 0.75, 0.35, 1.0);
            } elseif (isset($closedSet[$index])) {
                glClearColor(0.8, 0.45, 0.15, 1.0);
            } else {
                glClearColor(0.88, 0.9, 0.93, 1.0);
            }
            glClear(GL_COLOR_BUFFER_BIT);
        }
        glDisable(GL_SCISSOR_TEST);
    }
}
