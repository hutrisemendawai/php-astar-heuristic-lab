# A* Heuristic Lab

This repository separates a headless PHP core from a VISU/GLFW demo. It compares Octile and Chebyshev heuristics with 8-direction A* on square grids, using geometric (`D₂ = √2`) and uniform (`D₂ = 1`) diagonal costs. The benchmark uses paired maps and deterministic seeds; it does not contain fabricated results.

## Requirements and installation

- PHP 8.2 or newer (the CI matrix uses PHP 8.3 and 8.4)
- Composer 2
- For the game only: php-glfw and the VISU prerequisites described by [phpgl/visu](https://github.com/phpgl/visu)

Install the PHP dependencies:

```sh
composer install
```

VISU 1.0 exposes a fixed-step `GameLoop`/`GameLoopDelegate` (rather than an `Application` class). The game adapter uses that interface; it does not run in headless CI.

## Game

On a machine with a display and php-glfw enabled:

```sh
php bin/game
```

Controls: **H** heuristic, **D** diagonal cost, **G** grid size, **O** obstacle density, **←/→** seed, **R** regenerate, **Space** search, **V** show/hide open and closed cells, **S** side-by-side comparison. Click a cell to toggle an obstacle (start and goal are protected). The window title shows the active settings and search metrics.

Pathfinding runs in the fixed-timestep `update()` callback; `draw()` contains only OpenGL work. Benchmark calls do not use the game layer.

## Benchmark

```sh
php -c php.ini.benchmark bin/benchmark
```

Defaults are `--n-seed=30 --warmup=20 --iterations=50 --base-seed=1000 --out=results/`. Smaller runs are useful for validation:

```sh
php -c php.ini.benchmark bin/benchmark \
  --map-strategy=repair --n-seed=2 --warmup=1 --iterations=3 --out=/tmp/astar-smoke
```

Optional switches are `--map-strategy=pre-verified|repair`, `--engine=xoshiro|mt19937`, `--priority=negate|min-class`, and `--tie-break=none|insertionOrder`; `--no-jit` requests a baseline run. The default map strategy advances the seed until each size/density group contains the requested number of BFS-solvable maps. At 50% obstacles, no-corner-cutting can make those seeds extremely rare; use `repair` for a bounded smoke run. Repair deterministically opens a top-row/right-column corridor and relocates its obstacles, preserving the exact obstacle count. All heuristics and cost schemes use the same maps within a group.

The full factorial matrix contains 36 scenarios (S01–S18 geometric; S19–S36 uniform). It counterbalances Octile/Chebyshev order across paired maps, performs warm-up before measurement, and reports the median timing per map. Output files are:

- `raw.csv`: one row per map and scenario
- `summary_geometric.csv` and `summary_uniform.csv`: paper-table summaries
- `metadata.json`: runtime/JIT/OS/CPU/package/commit and run details
- `seeds_used.json`: accepted seeds and rejected-seed counts per size/density

`results/` is ignored except for `.gitkeep`; choose an external output folder for local smoke runs.

## Metrics and reproducibility

- `T_exec`: elapsed search time in microseconds, measured with `hrtime(true)` from search-state initialization through path reconstruction. It contains no rendering, logging, or file I/O.
- `N_exp`: number of unique nodes actually expanded.
- `M_peak`: maximum open-list size, including lazy-deletion entries.
- `C_path`: returned path cost; `C*` is the admissible-heuristic A* ground truth.
- `costGapPct`: `(C_path - C*) / C* × 100`.
- `ΔN_exp%`: `(N_exp,Chebyshev - N_exp,Octile) / N_exp,Chebyshev × 100`.

The default minimum heap negates priorities; `--priority=min-class` uses the `MinPriorityQueue` comparator. Both modes use a deterministic insertion-order tie-break by default. Chebyshev remains an integer-valued estimate; Octile uses a precomputed `√2 - 1` constant. The generic `HeuristicInterface` keeps the algorithms swappable without a dependency on graphics code.

The benchmark checks the expected optimality and the Uniform/Octile `√2` cost bound. `GroundTruth` also provides Dijkstra for independent tests. Results are reproducible for a fixed PHP/RNG implementation, seed, and options; execution time can still vary with JIT state, CPU microarchitecture, operating system scheduling, and thermal conditions. A missing JIT produces a prominent warning and is recorded in metadata. JIT guard failures and runtime/compiler differences may change timing even when paths and counts are identical.

## Quality checks

```sh
composer test
composer stan
composer cs
```

See [`docs/METHODOLOGY.md`](docs/METHODOLOGY.md) for the mapping from implementation to the paper's sections.
