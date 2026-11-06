<?php

declare(strict_types=1);

namespace AStarLab\Game;

use VISU\OS\Window;
use VISU\OS\WindowEventHandlerInterface;

final class InputController implements WindowEventHandlerInterface
{
    public function __construct(private readonly GameApplication $application)
    {
    }

    public function handleWindowKey(Window $window, int $key, int $scancode, int $action, int $mods): void
    {
        if ($action === GLFW_PRESS) {
            $this->application->handleKey($key);
        }
    }

    public function handleWindowChar(Window $window, int $char): void
    {
    }

    public function handleWindowCharMods(Window $window, int $char, int $mods): void
    {
    }

    public function handleWindowMouseButton(Window $window, int $button, int $action, int $mods): void
    {
        if ($action === GLFW_PRESS && ($button === GLFW_MOUSE_BUTTON_1 || $button === GLFW_MOUSE_BUTTON_2)) {
            $this->application->toggleAtCursor($window);
        }
    }

    public function handleWindowCursorPos(Window $window, float $xpos, float $ypos): void
    {
    }

    public function handleWindowCursorEnter(Window $window, int $entered): void
    {
    }

    public function handleWindowScroll(Window $window, float $xoffset, float $yoffset): void
    {
    }

    public function handleWindowDrop(Window $window, array $paths): void
    {
    }

    public function handleWindowWillPollEvents(Window $window): void
    {
    }

    public function handleWindowDidPollEvents(Window $window): void
    {
    }
}
