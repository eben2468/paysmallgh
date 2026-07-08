<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    private string $viewsPath;

    public function __construct()
    {
        $this->viewsPath = dirname(__DIR__) . '/Views';
    }

    /**
     * Render a view inside the main layout.
     * $data is extracted into the view's scope.
     */
    public function render(string $view, array $data = [], string $layout = 'layouts/main'): string
    {
        $content = $this->partial($view, $data);
        $data['content'] = $content;
        return $this->partial($layout, $data);
    }

    /** Render a view file with no layout. */
    public function partial(string $view, array $data = []): string
    {
        $file = $this->viewsPath . '/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$view}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
