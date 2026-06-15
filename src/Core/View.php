<?php

declare(strict_types=1);

namespace CityBus\Core;

/**
 * Moteur de templates PHP simple : layout + sections + escape.
 */
final class View
{
    private array $sections = [];
    private array $stack    = [];
    private ?string $layout = null;
    private string $viewsPath;

    public function __construct(?string $viewsPath = null)
    {
        $this->viewsPath = $viewsPath ?? BASE_PATH . '/views';
    }

    public function render(string $view, array $data = []): string
    {
        $content = $this->renderView($view, $data);
        if ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null;
            // Si la vue a défini une section 'content', on la privilégie ;
            // sinon on utilise l'output brut hors-sections.
            $main = $this->sections['content'] ?? $content;
            $data['__content'] = $main;
            $this->sections['content'] = $main;
            return $this->renderView($layout, $data);
        }
        return $content;
    }

    private function renderView(string $view, array $__data): string
    {
        $file = $this->viewsPath . '/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Vue introuvable : $view ($file)");
        }
        extract($__data, EXTR_SKIP);
        $view = $this; // accessible dans le template via $view
        ob_start();
        include $file;
        return ob_get_clean() ?: '';
    }

    public function extends(string $layout): void
    {
        $this->layout = $layout;
    }

    public function start(string $section): void
    {
        $this->stack[] = $section;
        ob_start();
    }

    public function end(): void
    {
        $section = array_pop($this->stack);
        $this->sections[$section] = ob_get_clean();
    }

    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function include(string $view, array $data = []): void
    {
        echo $this->renderView($view, $data);
    }

    public function partial(string $view, array $data = []): string
    {
        return $this->renderView($view, $data);
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
