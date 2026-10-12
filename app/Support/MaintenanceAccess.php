<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may open the "Mantenimiento" page (cache, migrations...).
 *
 * - If config('admin.maintenance_user_ids') is not empty (env
 *   MAINTENANCE_ADMIN_IDS) the user ID decides. IDs cannot be edited from the
 *   panel, so they anchor the access even if an e-mail changes hands.
 * - Otherwise the e-mail list in config('admin.maintenance_emails') applies
 *   (env MAINTENANCE_ADMIN_EMAILS, case-insensitive).
 */
final class MaintenanceAccess
{
    public static function allows(?Authenticatable $user): bool
    {
        if ($user === null) {
            return false;
        }

        $ids = array_map('strval', (array) config('admin.maintenance_user_ids', []));

        if ($ids !== []) {
            $id = $user->getAuthIdentifier();

            return (is_int($id) || (is_string($id) && $id !== '')) && in_array((string) $id, $ids, true);
        }

        $email = $user->email ?? null;

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
