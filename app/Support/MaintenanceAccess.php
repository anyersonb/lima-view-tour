<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may open the "Mantenimiento" page (cache, migrations...). The list lives
 * in config/admin.php (env MAINTENANCE_ADMIN_EMAILS, case-insensitive).
 */
final class MaintenanceAccess
{
    public static function allows(?Authenticatable $user): bool
    {
        $email = $user?->email ?? null;

        if (! is_string($email) || trim($email) === '') {
            return false;
        }

        $allowed = array_map(
            static fn ($e): string => mb_strtolower(trim((string) $e)),
            (array) config('admin.maintenance_emails', [])
        );

        return in_array(mb_strtolower(trim($email)), $allowed, true);
    }
}
