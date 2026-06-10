<?php

declare(strict_types=1);

namespace Amelias\Controllers\Admin;

use Amelias\Controllers\Controller;
use Amelias\Database\Database;
use Amelias\Services\Auth;
use Amelias\Support\Session;
use Amelias\Http\Request;

/**
 * Staff login (screen 18b), separate from the customer auth screen.
 */
final class AuthController extends Controller
{
    public function login(Request $request): void
    {
        if (Session::user() !== null) {
            $this->redirect(url('/admin'));
            return;
        }

        $error = null;
        if ($request->isPost()) {
            $email = trim((string) $request->input('email'));
            $password = (string) $request->input('password');
            $ip = $request->ip();

            if (!csrf_verify((string) $request->input('_csrf'))) {
                $error = 'Your session expired. Please try again.';
            } elseif (Auth::isLockedOut($ip, $email)) {
                $error = 'Too many attempts. Please wait 15 minutes and try again.';
            } else {
                $user = Auth::attemptStaff($email, $password, $ip);
                if ($user !== null) {
                    Session::loginUser($user);
                    Session::guardFingerprint($ip, $request->userAgent());
                    Database::run('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?', [$user['id']]);
                    $this->redirect(url('/admin'));
                    return;
                }
                $error = 'Incorrect email or password.';
            }
        }

        $this->response->view('admin/login', [
            'title'    => 'Staff Sign In',
            'error'    => $error,
            'isDemo'   => Session::isDemoHost(),
        ], 'layouts/auth');
    }

    public function logout(Request $request): void
    {
        Session::logout();
        $this->redirect(url('/admin/login'));
    }
}
