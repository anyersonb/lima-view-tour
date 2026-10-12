<?php

/**
 * Admin panel access rules that are not covered by roles.
 *
 * maintenance_emails: the only accounts that can open "Mantenimiento" (cache,
 * optimize, run migrations...). Comma-separated in MAINTENANCE_ADMIN_EMAILS,
 * case-insensitive.
 *
 * maintenance_user_ids: optional, stronger anchor (profile e-mail is not editable
 * but IDs never change). Comma-separated in MAINTENANCE_ADMIN_IDS.
 *
 * Production has no SSH and the .env is edited by hand, so an empty or missing
 * variable must NOT lock everyone out: it falls back to the default below.
 *
 * TODO(confirm with Anyerson): the default is the e-mail assumed for his admin
 * login. If he signs in with another address, set MAINTENANCE_ADMIN_EMAILS in
 * the production .env BEFORE deploying, or he will see a 403 on that page.
 */
$maintenanceDefault = ['ablanco@webtilia.com'];

$maintenanceEmails = array_values(array_unique(array_filter(array_map(
    static fn (string $email): string => mb_strtolower(trim($email)),
    explode(',', (string) env('MAINTENANCE_ADMIN_EMAILS', ''))
))));

$maintenanceIds = array_values(array_unique(array_filter(array_map(
    static fn (string $id): string => trim($id),
    explode(',', (string) env('MAINTENANCE_ADMIN_IDS', ''))
), static fn (string $id): bool => ctype_digit($id))));

return [
    'maintenance_emails' => $maintenanceEmails !== [] ? $maintenanceEmails : $maintenanceDefault,
    // When not empty, these user IDs (env MAINTENANCE_ADMIN_IDS, comma-separated)
    // decide access to Maintenance instead of the e-mail list.
    'maintenance_user_ids' => $maintenanceIds,
];
