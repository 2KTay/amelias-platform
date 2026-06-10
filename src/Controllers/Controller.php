<?php

declare(strict_types=1);

namespace Amelias\Controllers;

use Amelias\Http\Response;

/**
 * Base controller. Provides a shared Response and small view helpers so every
 * controller renders through the same layout pipeline.
 */
abstract class Controller
{
    protected Response $response;

    public function __construct()
    {
        $this->response = new Response();
    }

    /** Render a public-facing page wrapped in the public layout. */
    protected function viewPublic(string $template, array $data = [], int $status = 200): void
    {
        $this->response->view($template, $data, 'layouts/public', $status);
    }

    /** Render an admin page wrapped in the admin layout. */
    protected function viewAdmin(string $template, array $data = [], int $status = 200): void
    {
        $this->response->view($template, $data, 'layouts/admin', $status);
    }

    protected function redirect(string $to, int $status = 302): void
    {
        $this->response->redirect($to, $status);
    }

    protected function json(array $data, int $status = 200): void
    {
        $this->response->json($data, $status);
    }
}
