<?php

declare(strict_types=1);

namespace Amelias\Controllers\Admin;

use Amelias\Controllers\Controller;
use Amelias\Support\Session;

/**
 * Base for all /admin controllers. Enforces authentication + role gating so a
 * Staff user can never reach owner-only screens (Reports, Users, Settings).
 */
abstract class AdminController extends Controller
{
    /**
     * Require a logged-in staff user with one of $roles. Redirects to the staff
     * login when not authenticated; renders 403 when authenticated but under-privileged.
     * Returns the current user array.
     */
    protected function requireRole(string ...$roles): array
    {
        $user = Session::user();
        if ($user === null) {
            $this->redirect(url('/admin/login'));
            exit;
        }
        if ($roles !== [] && !Session::hasRole(...$roles)) {
            $this->response->view('admin/403', ['title' => 'Not allowed'], 'layouts/admin', 403);
            exit;
        }
        return $user;
    }
}
