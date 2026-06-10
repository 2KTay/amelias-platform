<?php

declare(strict_types=1);

namespace Amelias\Controllers;

use Amelias\Database\Database;
use Amelias\Services\Auth;
use Amelias\Support\Session;
use Amelias\Http\Request;

/**
 * Customer authentication (screen 18): optional login / create account /
 * continue-as-guest / password reset. Guests never need an account to check out.
 */
final class AuthController extends Controller
{
    public function login(Request $request): void
    {
        if (Session::customer() !== null) {
            $this->redirect(url('/account'));
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
                $error = 'Too many attempts. Please wait a few minutes.';
            } else {
                $customer = Auth::attemptCustomer($email, $password, $ip);
                if ($customer !== null) {
                    Session::loginCustomer($customer);
                    $this->redirect(url('/account'));
                    return;
                }
                $error = 'Incorrect email or password.';
            }
        }
        $this->viewPublic('public/auth', ['title' => 'Sign In', 'styles' => ['pages/auth.css'], 'error' => $error, 'mode' => 'login']);
    }

    public function register(Request $request): void
    {
        $error = null;
        if ($request->isPost()) {
            $email = trim((string) $request->input('email'));
            $password = (string) $request->input('password');
            if (!csrf_verify((string) $request->input('_csrf'))) {
                $error = 'Your session expired. Please try again.';
            } elseif (strlen($password) < 8) {
                $error = 'Please choose a password of at least 8 characters.';
            } elseif (Database::fetch('SELECT id FROM customers WHERE email = ? AND password_hash IS NOT NULL', [$email])) {
                $error = 'An account with that email already exists, try signing in.';
            } else {
                $customer = Auth::findOrCreateCustomer($email, [
                    'first_name' => trim((string) $request->input('first_name')),
                    'last_name'  => trim((string) $request->input('last_name')),
                ]);
                Database::run('UPDATE customers SET password_hash = ?, must_reset_password = 0 WHERE id = ?', [
                    password_hash($password, PASSWORD_DEFAULT), $customer['id'],
                ]);
                $customer['password_hash'] = 'set';
                Session::loginCustomer($customer);
                $this->redirect(url('/account'));
                return;
            }
        }
        $this->viewPublic('public/auth', ['title' => 'Create Account', 'styles' => ['pages/auth.css'], 'error' => $error, 'mode' => 'register']);
    }

    public function reset(Request $request): void
    {
        // Reset email dispatch arrives with the notification service (Task 1.4);
        // this renders the request form and acknowledges without leaking which
        // emails exist (anti-enumeration), rate-limited.
        $sent = false;
        if ($request->isPost() && csrf_verify((string) $request->input('_csrf'))) {
            $email = trim((string) $request->input('email'));
            Auth::rateLimit('pwreset:' . $request->ip(), 5, 900);
            $sent = true;
        }
        $this->viewPublic('public/auth', ['title' => 'Reset Password', 'styles' => ['pages/auth.css'], 'mode' => 'reset', 'sent' => $sent]);
    }

    public function logout(Request $request): void
    {
        Session::logout();
        $this->redirect(url('/'));
    }
}
