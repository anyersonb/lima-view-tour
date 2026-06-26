<?php
/**
 * Dumps the `tours` and `bookings` tables from the local Laravel DB into a
 * portable SQL file suitable for import into the prod database. Avoids
 * needing mysqldump on Windows.
 *
 * Usage: php database/scripts/dump_migration_tables.php > path/to/dump.sql
 */
declare(strict_types=1);

$pdo = new PDO('mysql:host=127.0.0.1;dbname=lima_tours;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "SET NAMES utf8mb4;\n";
echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

// Wipe child first, then parent. DELETE (not TRUNCATE) so phpMyAdmin respects
// the FK_CHECKS toggle even when it runs each statement as its own transaction.
echo "DELETE FROM `bookings`;\n";
echo "DELETE FROM `tours`;\n\n";

// Insert order: parent (tours) before child (bookings).
$tables = ['tours', 'bookings'];

foreach ($tables as $tbl) {
    echo "-- ─────── {$tbl} ───────\n";

    $rows = $pdo->query("SELECT * FROM `{$tbl}`")->fetchAll();
    if (! $rows) continue;

    $cols = array_keys($rows[0]);
    $colList = '`' . implode('`, `', $cols) . '`';

    $chunkSize = 50;
    foreach (array_chunk($rows, $chunkSize) as $chunk) {
        echo "INSERT INTO `{$tbl}` ({$colList}) VALUES\n";
        $values = [];
        foreach ($chunk as $row) {
            $cells = array_map(function ($v) use ($pdo) {
                if ($v === null) return 'NULL';
                if (is_int($v) || is_float($v)) return (string) $v;
                return $pdo->quote((string) $v);
            }, array_values($row));
            $values[] = '(' . implode(', ', $cells) . ')';
        }
        echo implode(",\n", $values) . ";\n\n";
    }
}

echo "SET FOREIGN_KEY_CHECKS=1;\n";
