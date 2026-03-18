<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

class PermissionGuard
{
    /**
     * Garante que a sessão atual possui um dos papéis informados.
     */
    public static function requireRole(array $roles = ['admin']): void
    {
        $role = $_SESSION['role'] ?? 'guest';

        if (!in_array($role, $roles, true)) {
            throw new RuntimeException('Acesso negado. Permissão insuficiente.');
        }
    }

    /**
     * Define a role padrão caso não exista.
     */
    public static function defaultRole(string $role = 'guest'): void
    {
        if (!isset($_SESSION['role'])) {
            $_SESSION['role'] = $role;
        }
    }
}
