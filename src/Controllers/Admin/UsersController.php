<?php

declare(strict_types=1);

namespace Amelias\Controllers\Admin;

use Amelias\Database\Database;
use Amelias\Http\Request;

/**
 * Users & roles (screen 27, 6.5), Owner ONLY.
 *
 * Staff list, role assignment (owner/manager/staff), invite (creates a user
 * with must_reset_password=1), deactivate/reactivate, and a recent audit_log
 * view. Every change writes audit_log. An owner cannot demote or deactivate
 * themselves, and the last active owner cannot be removed.
 */
final class UsersController extends AdminController
{
    private const ROLES = ['owner', 'manager', 'staff'];

    public function index(Request $request): void
    {
        $user = $this->requireRole('owner');
        $notice = null;
        $error  = null;

        if ($request->isPost()) {
            if (!csrf_verify((string) $request->input('_csrf'))) {
                $this->redirect(url('/admin/users'));
                return;
            }
            $action = (string) $request->input('action');
            try {
                match ($action) {
                    'invite'     => $notice = $this->invite($request, (int) $user['id']),
                    'role'       => $notice = $this->setRole((int) $request->input('user_id'), (string) $request->input('role'), (int) $user['id']),
                    'deactivate' => $notice = $this->setActive((int) $request->input('user_id'), false, (int) $user['id']),
                    'reactivate' => $notice = $this->setActive((int) $request->input('user_id'), true, (int) $user['id']),
                    default      => null,
                };
            } catch (\RuntimeException $e) {
                $error = $e->getMessage();
            }
            // PRG unless we have an inline error to show.
            if ($error === null) {
                $_SESSION['_notice'] = $notice;
                $this->redirect(url('/admin/users'));
                return;
            }
        }

        $notice ??= $_SESSION['_notice'] ?? null;
        unset($_SESSION['_notice']);

        $this->viewAdmin('admin/users', [
            'title'   => 'Staff & Access',
            'styles'  => ['pages/admin-depth.css'],
            'currentUserId' => (int) $user['id'],
            'users'   => $this->users(),
            'audit'   => $this->recentAudit(),
            'roles'   => self::ROLES,
            'notice'  => $notice,
            'error'   => $error,
        ]);
    }

    /** @return list<array<string,mixed>> */
    private function users(): array
    {
        return Database::fetchAll(
            'SELECT id, email, name, role, is_active, must_reset_password, last_login_at
               FROM users ORDER BY (role = \'owner\') DESC, role ASC, name ASC'
        );
    }

    /** @return list<array<string,mixed>> */
    private function recentAudit(): array
    {
        return Database::fetchAll(
            'SELECT a.created_at, a.action, a.entity_type, a.entity_id, u.name AS actor_name
               FROM audit_log a
               LEFT JOIN users u ON u.id = a.actor_id AND a.actor_type = \'user\'
              ORDER BY a.id DESC LIMIT 25'
        );
    }

    private function invite(Request $request, int $actorId): string
    {
        $email = strtolower(trim((string) $request->input('email')));
        $name  = trim((string) $request->input('name'));
        $role  = (string) $request->input('role');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('A valid email is required.');
        }
        if (!in_array($role, self::ROLES, true)) {
            $role = 'staff';
        }
        $exists = Database::fetchValue('SELECT 1 FROM users WHERE email = ?', [$email]);
        if ($exists) {
            throw new \RuntimeException('A user with that email already exists.');
        }
        // Unguessable placeholder hash; the invitee must reset before first use.
        $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
        $id = Database::insert(
            'INSERT INTO users (email, name, password_hash, role, is_active, must_reset_password)
             VALUES (?, ?, ?, ?, 1, 1)',
            [$email, $name !== '' ? $name : $email, $hash, $role]
        );
        $this->audit($actorId, 'user.invite', $id, ['email' => $email, 'role' => $role]);
        return 'Invited ' . $email . ' (' . $role . '). They must set a password before signing in.';
    }

    private function setRole(int $userId, string $role, int $actorId): string
    {
        if (!in_array($role, self::ROLES, true)) {
            throw new \RuntimeException('Invalid role.');
        }
        $target = Database::fetch('SELECT id, role FROM users WHERE id = ?', [$userId]);
        if ($target === null) {
            throw new \RuntimeException('User not found.');
        }
        if ($userId === $actorId && $role !== 'owner') {
            throw new \RuntimeException('You cannot remove your own owner access.');
        }
        if ($target['role'] === 'owner' && $role !== 'owner' && $this->activeOwnerCount() <= 1) {
            throw new \RuntimeException('At least one active owner is required.');
        }
        Database::run('UPDATE users SET role = ? WHERE id = ?', [$role, $userId]);
        $this->audit($actorId, 'user.role', $userId, ['role' => $role]);
        return 'Role updated.';
    }

    private function setActive(int $userId, bool $active, int $actorId): string
    {
        $target = Database::fetch('SELECT id, role, is_active FROM users WHERE id = ?', [$userId]);
        if ($target === null) {
            throw new \RuntimeException('User not found.');
        }
        if (!$active) {
            if ($userId === $actorId) {
                throw new \RuntimeException('You cannot deactivate your own account.');
            }
            if ($target['role'] === 'owner' && $this->activeOwnerCount() <= 1) {
                throw new \RuntimeException('At least one active owner is required.');
            }
        }
        Database::run('UPDATE users SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $userId]);
        $this->audit($actorId, $active ? 'user.reactivate' : 'user.deactivate', $userId, ['is_active' => $active]);
        return $active ? 'User reactivated.' : 'User deactivated.';
    }

    private function activeOwnerCount(): int
    {
        return (int) Database::fetchValue("SELECT COUNT(*) FROM users WHERE role = 'owner' AND is_active = 1");
    }

    private function audit(int $actorId, string $action, int $entityId, array $after): void
    {
        Database::run(
            'INSERT INTO audit_log (actor_type, actor_id, action, entity_type, entity_id, after_json)
             VALUES (?, ?, ?, ?, ?, ?)',
            ['user', $actorId, $action, 'user', (string) $entityId, json_encode($after, JSON_THROW_ON_ERROR)]
        );
    }
}
