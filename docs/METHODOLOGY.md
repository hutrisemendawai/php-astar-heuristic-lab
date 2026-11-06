# Methodology and implementation mapping

This document records the implementation assumptions for the study. It maps the paper sections III.A–III.F to the repository so benchmark settings can be audited independently of the visualization.

## III.A — Experimental design

`src/Core/Benchmark/ExperimentMatrix.php` enumerates the 2 × 2 × 3 × 3 factorial design and assigns S01–S18 to Geometric followed by S19–S36 to Uniform. `BenchmarkRunner` reuses each accepted map for both heuristics and cost schemes, alternates heuristic order for neighboring maps, applies warm-up before measured repetitions, and reduces each map's repeated timing to a median.

## III.B — Grid and map generation

`src/Core/Grid/Grid.php` uses `idx = y * width + x` and a flat occupancy array. `GridGenerator` blocks exactly `floor(density × width × height)` non-endpoint cells using `Randomizer::pickArrayKeys`. The default runner filters increasing seeds through the independent BFS in `SolvabilityChecker`; the optional repair strategy opens a deterministic top-row/right-column corridor and relocates removed obstacles to unblocked off-corridor cells. A local probe found no accepted 50%-density seed in the first 1,000 Xoshiro seeds for 20×20, 50×50, or 100×100 grids. This density/movement combination may therefore make strict pre-verification impractically slow; the CI smoke uses the documented repair strategy and does not represent the default pre-verified sampling protocol.

## III.C — Movement and cost models

`Grid::canStep()` enforces bounds, occupancy, 8-neighbor adjacency, and no corner-cutting: a diagonal requires both adjacent cardinal cells as well as its destination to be free. `DiagonalCostScheme` defines cardinal cost 1 and diagonal cost √2 or 1. `SolvabilityChecker` repeats the movement constraints in its own BFS rather than calling A*.

## III.D — Heuristics and A*

`OctileHeuristic` and `ChebyshevHeuristic` implement `HeuristicInterface`. Octile uses a precomputed √2 − 1 factor; Chebyshev returns the maximum coordinate difference. `src/Core/Search/AStar.php` uses flat `gScore`, predecessor, and closed arrays; the open list is `SplPriorityQueue` with lazy deletion. The default min-heap negates `f`; `MinPriorityQueue` supplies the alternative reversed comparator. The default `[-f, -sequence]` ordering makes earlier equal-priority insertions deterministic (FIFO). A 1e-9 comparison tolerance avoids floating-point reopen noise.

## III.E — Ground truth and correctness

`GroundTruth::optimalCost()` runs A* with Octile for Geometric and Chebyshev for Uniform; `GroundTruth::dijkstraCost()` uses a zero heuristic. Unit tests compare these answers and validate path legality, movement boundaries, determinism, exact obstacle counts, and the theoretical cost bounds: both Geometric heuristics and Uniform/Chebyshev are optimal; Uniform/Octile is bounded by √2 times optimal.

## III.F — Measurement and reporting

`AStar::search()` starts its timer immediately before allocating search arrays and the open queue; it stops after path reconstruction. It excludes map generation, output, and OpenGL. `SearchResult` reports `T_exec`, `N_exp`, `M_peak`, `pushCount`, and path/cost. `BenchmarkRunner` calculates paired per-map rows and per-scenario time statistics. `CsvExporter` and `bin/benchmark` write the paper tables plus raw measurements, metadata, and the accepted/rejected seed log.

JIT values are read from OPcache at run time. When the CLI JIT is unavailable, the run is explicitly warned about and the status is persisted instead of treating interpreted results as a JIT run. CPU and operating-system metadata are best-effort (`/proc/cpuinfo` with an architecture fallback).

## Game adapter assumption

VISU 1.0 provides `VISU\Runtime\GameLoop` and `GameLoopDelegate`, but no `Application` class. `src/Game/GameApplication.php` therefore implements the versioned delegate API directly. It performs searches and event-driven world changes in fixed-step `update()` and confines drawing to `draw()`/`render()`. The title bar is the lightweight HUD; the game requires php-glfw and a display and is intentionally excluded from CI execution.
