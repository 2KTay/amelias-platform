<?php

declare(strict_types=1);

namespace Amelias\Http;

/**
 * HTTP response helpers + server-side view rendering.
 *
 * Views are plain PHP templates under templates/. render() extracts data into
 * the template scope and wraps it in an optional layout. No template engine,
 * the conventions audit forbids inline styles, not PHP partials.
 */
final class Response
{
    private string $templatesPath;

    public function __construct(?string $templatesPath = null)
    {
        $this->templatesPath = $templatesPath ?? (ROOT_PATH . '/templates');
    }

    public function html(string $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        echo $body;
    }

    public function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public function redirect(string $to, int $status = 302): void
    {
        http_response_code($status);
        header('Location: ' . $to);
    }

    /**
     * Render a template (e.g. "public/home") optionally wrapped in a layout
     * (e.g. "layouts/public"). Data keys become local variables in the view.
     *
     * @param array<string,mixed> $data
     */
    public function view(string $template, array $data = [], ?string $layout = null, int $status = 200): void
    {
        $content = $this->capture($template, $data);
        if ($layout !== null) {
            $content = $this->capture($layout, $data + ['content' => $content]);
        }
        $this->html($content, $status);
    }

    /** @param array<string,mixed> $data */
    public function capture(string $template, array $data = []): string
    {
        $file = $this->templatesPath . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Template not found: {$template}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
