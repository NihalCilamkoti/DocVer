<?php

namespace App\Enums;

enum UserRole: string
{
    case USER = 'user';
    case WORKSPACE_OWNER = 'workspace_owner';
    case WORKSPACE_ADMIN = 'workspace_admin';
    case SUPER_ADMIN = 'super_admin';

    public function label(): string
    {
        return match($this) {
            self::USER => 'User',
            self::WORKSPACE_OWNER => 'Workspace Owner',
            self::WORKSPACE_ADMIN => 'Workspace Administrator',
            self::SUPER_ADMIN => 'Super Administrator',
        };
    }

    public function isSuperAdmin(): bool
    {
        return $this === self::SUPER_ADMIN;
    }

    public function canManageWorkspace(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::WORKSPACE_OWNER, self::WORKSPACE_ADMIN]);
    }
}
