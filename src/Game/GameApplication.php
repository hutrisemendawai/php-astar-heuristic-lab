<?php

declare(strict_types=1);

namespace AStarLab\Game;

use AStarLab\Core\Grid\Grid;
use AStarLab\Core\Grid\GridGenerator;
use AStarLab\Core\Grid\SolvabilityChecker;
use AStarLab\Core\Heuristic\ChebyshevHeuristic;
use AStarLab\Core\Heuristic\OctileHeuristic;
use AStarLab\Core\Search\AStar;
use AStarLab\Core\Search\DiagonalCostScheme;
use AStarLab\Core\Search\SearchResult;
use RuntimeException;
use VISU\Graphics\GLState;
use VISU\OS\Window;
use VISU\Runtime\GameLoop;
use VISU\Runtime\GameLoopDelegate;

final class GameApplication implements GameLoopDelegate
{
    private Grid $grid;

    private int $size = 20;

    private float $density = 0.10;

    private int $seed = 1000;

    private string $heuristicName = 'octile';

    private DiagonalCostScheme $scheme = DiagonalCostScheme::Geometric;

    private ?SearchResult $result = null;

    private ?SearchResult $comparisonResult = null;

    private ?float $optimalCost = null;

    private ?float $comparisonOptimalCost = null;

    private bool $searchPending = false;

    private bool $regeneratePending = true;

    private bool $showSearchSets = true;

    private bool $sideBySide = false;

    private ?int $npcIndex = null;

    private ?Window $window = null;

    private bool $jitEnabled;

    private readonly GridRenderer $renderer;

    private readonly HudOverlay $hud;

    private readonly NpcEntity $npc;

    public function __construct()
    {
        $this->grid = new Grid($this->size, $this->size);
        $status = function_exists('opcache_get_status') ? opcache_get_status(false) : false;
        $this->jitEnabled = is_array($status) && (bool) ($status['jit']['enabled'] ?? false);
        $this->renderer = new GridRenderer();
        $this->hud = new HudOverlay();
        $this->npc = new NpcEntity();
    }

    public function run(): void
    {
        if (!extension_loaded('glfw') || !function_exists('glfwInit')) {
            throw new RuntimeException('The php-glfw extension is required to run the game.');
        }
        if (!glfwInit()) {
            throw new RuntimeException('Unable to initialize GLFW.');
        }

        try {
            $this->window = new Window('A* Heuristic Lab', 900, 900);
            $this->window()->initailize(new GLState());
            $this->window()->setEventHandler(new InputController($this));
            $this->window()->setSwapInterval(1);
            (new GameLoop($this, 60.0))->start();
        } finally {
            $this->window = null;
            glfwTerminate();
        }
    }

    public function update(): void
    {
        $this->window()->pollEvents();
        if ($this->regeneratePending) {
            $this->generateSolvableGrid();
            $this->regeneratePending = false;
        }
        if ($this->searchPending) {
            $this->runSearch();
            $this->searchPending = false;
        }
        if ($this->result !== null) {
            $this->npcIndex = $this->npc->advance($this->result->path);
        }

        $this->hud->updateTitle(
            $this->window(),
            ucfirst($this->heuristicName),
            ucfirst($this->scheme->value),
            $this->size,
            $this->density,
            $this->seed,
            $this->result,
            $this->optimalCost,
            $this->jitEnabled,
        );
    }

    public function draw(): void
    {
        $width = $this->window()->getFramebufferWidth();
        $height = $this->window()->getFramebufferHeight();
        glViewport(0, 0, $width, $height);
        glClearColor(0.08, 0.08, 0.1, 1.0);
        glClear(GL_COLOR_BUFFER_BIT);

        if ($this->sideBySide) {
            $halfWidth = intdiv($width, 2);
            $this->renderer->draw(
                $this->grid,
                $width,
                $height,
                $this->result?->path ?? [],
                $this->showSearchSets ? ($this->result?->openIndices ?? []) : [],
                $this->showSearchSets ? ($this->result?->closedIndices ?? []) : [],
                0,
                $halfWidth,
                $this->npcIndex,
            );
            $this->renderer->draw(
                $this->grid,
                $width,
                $height,
                $this->comparisonResult?->path ?? [],
                $this->showSearchSets ? ($this->comparisonResult?->openIndices ?? []) : [],
                $this->showSearchSets ? ($this->comparisonResult?->closedIndices ?? []) : [],
                $halfWidth,
                $width - $halfWidth,
            );
        } else {
            $this->renderer->draw(
                $this->grid,
                $width,
                $height,
                $this->result?->path ?? [],
                $this->showSearchSets ? ($this->result?->openIndices ?? []) : [],
                $this->showSearchSets ? ($this->result?->closedIndices ?? []) : [],
                0,
                $width,
                $this->npcIndex,
            );
        }
    }

    public function render(float $deltaTime): void
    {
        $this->draw();
        $this->window()->swapBuffers();
    }

    public function shouldStop(): bool
    {
        return $this->window()->shouldClose();
    }

    public function handleKey(int $key): void
    {
        match ($key) {
            GLFW_KEY_H => $this->heuristicName = $this->heuristicName === 'octile' ? 'chebyshev' : 'octile',
            GLFW_KEY_D => $this->scheme = $this->scheme === DiagonalCostScheme::Geometric
                ? DiagonalCostScheme::Uniform
                : DiagonalCostScheme::Geometric,
            GLFW_KEY_G => $this->size = match ($this->size) {
                20 => 50,
                50 => 100,
                default => 20,
            },
            GLFW_KEY_O => $this->density = match ($this->density) {
                0.10 => 0.30,
                0.30 => 0.50,
                default => 0.10,
            },
            GLFW_KEY_LEFT => $this->seed--,
            GLFW_KEY_RIGHT => $this->seed++,
            GLFW_KEY_R => $this->regeneratePending = true,
            GLFW_KEY_SPACE => $this->searchPending = true,
            GLFW_KEY_V => $this->showSearchSets = !$this->showSearchSets,
            GLFW_KEY_S => $this->sideBySide = !$this->sideBySide,
            default => null,
        };
        if (in_array($key, [GLFW_KEY_H, GLFW_KEY_D, GLFW_KEY_G, GLFW_KEY_O, GLFW_KEY_LEFT, GLFW_KEY_RIGHT], true)) {
            $this->regeneratePending = true;
        }
        if ($key === GLFW_KEY_S) {
            $this->searchPending = true;
        }
    }

    public function toggleAtCursor(Window $window): void
    {
        $x = 0.0;
        $y = 0.0;
        glfwGetCursorPos($window->getGLFWHandle(), $x, $y);
        $gridX = min($this->size - 1, (int) floor($x / $window->getWidth() * $this->size));
        $gridY = min($this->size - 1, (int) floor($y / $window->getHeight() * $this->size));
        $index = $this->grid->index($gridX, $gridY);
        if ($index !== 0 && $index !== $this->grid->size() - 1) {
            $this->grid->setBlocked($gridX, $gridY, !$this->grid->isBlocked($gridX, $gridY));
            $this->result = null;
            $this->comparisonResult = null;
        }
    }

    private function generateSolvableGrid(): void
    {
        $generator = new GridGenerator();
        $checker = new SolvabilityChecker();
        do {
            $this->grid = $generator->generate($this->size, $this->size, $this->density, $this->seed++);
        } while (!$checker->isSolvable($this->grid));
        $this->seed--;
        $this->result = null;
        $this->comparisonResult = null;
        $this->optimalCost = null;
        $this->comparisonOptimalCost = null;
        $this->npc->reset();
        $this->npcIndex = null;
    }

    private function runSearch(): void
    {
        $octile = new OctileHeuristic();
        $chebyshev = new ChebyshevHeuristic();
        $heuristic = $this->heuristicName === 'octile' ? $octile : $chebyshev;
        $otherHeuristic = $this->heuristicName === 'octile' ? $chebyshev : $octile;
        $algorithm = new AStar();
        $this->result = $algorithm->search($this->grid, $heuristic, $this->scheme, collectVisualization: true);
        $truthHeuristic = $this->scheme === DiagonalCostScheme::Geometric ? $octile : $chebyshev;
        $this->optimalCost = (new AStar())->search($this->grid, $truthHeuristic, $this->scheme)->cost;
        $this->comparisonResult = null;
        $this->comparisonOptimalCost = null;
        if ($this->sideBySide) {
            $this->comparisonResult = $algorithm->search($this->grid, $otherHeuristic, $this->scheme, collectVisualization: true);
            $this->comparisonOptimalCost = $this->optimalCost;
        }
        $this->npc->reset();
        $this->npcIndex = $this->result->path[0] ?? null;
    }

    private function window(): Window
    {
        return $this->window ?? throw new RuntimeException('The VISU window is not initialized.');
    }
}
