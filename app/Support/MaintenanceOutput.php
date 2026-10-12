<?php

namespace App\Support;

/**
 * Turns the raw output of an artisan command (or the message of an exception)
 * into something safe to show in a panel notification.
 *
 * Failed migrations / DB calls dump the connection user, host, database and
 * sometimes the full SQL. That is operational detail the person pressing the
 * button must not see; the complete text goes to Log::error only.
 */
final class MaintenanceOutput
{
    public const MAX_LENGTH = 160;

    /** First meaningful line, credentials/DSN masked, cut to MAX_LENGTH. */
    public static function summary(?string $raw): string
    {
        $line = '';
        foreach (preg_split('/\R/u', (string) $raw) ?: [] as $candidate) {
            if (trim($candidate) !== '') {
                $line = trim($candidate);
                break;
            }
        }

        if ($line === '') {
            return '';
        }

        $line = self::mask($line);

        if (mb_strlen($line) > self::MAX_LENGTH) {
            $line = rtrim(mb_substr($line, 0, self::MAX_LENGTH - 1)).'…';
        }

        return $line;
    }

    public static function mask(string $text): string
    {
        $rules = [
            // scheme://user:pass@host:port/db  (DSN / URL with credentials)
            '~([a-z][a-z0-9+.\-]*://)[^\s/@]*@[^\s/]*~i' => '$1***',
            // 'user'@'host' (MySQL "Access denied for user")
            "~'[^']*'@'[^']*'~" => "'***'@'***'",
            // plain user@host
            '~[^\s@\'"]+@[^\s@\'"]+~' => '***@***',
            // (Connection: mysql, SQL: ...) from QueryException
            '~\(Connection:[^)]*\)?~i' => '(connection details omitted)',
            // key=value / key: value with sensitive meaning
            '~\b(password|passwd|pwd|pass|secret|token|api[_-]?key|key|dsn|host|hostname|dbname|database|db|user|username|port)\b(\s*[=:]\s*)[^\s;,)]+~i' => '$1$2***',
            // Unknown database 'x', table 'x'
            "~\b(database|schema|table)\s+'[^']*'~i" => "$1 '***'",
            // IPv4
            '~\b\d{1,3}(?:\.\d{1,3}){3}\b~' => '***',
            // "using password: YES" leftovers
            '~using password:\s*\w+~i' => 'using password: ***',
        ];

        foreach ($rules as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        return $text;
    }
}
