<?php

/**
 * Admin panel access rules that are not covered by roles.
 *
 * maintenance_emails: the only accounts that can open "Mantenimiento" (cache,
 * optimize, run migrations...). Comma-separated in MAINTENANCE_ADMIN_EMAILS,
 * case-insensitive.
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

return [
    'maintenance_emails' => $maintenanceEmails !== [] ? $maintenanceEmails : $maintenanceDefault,
];
