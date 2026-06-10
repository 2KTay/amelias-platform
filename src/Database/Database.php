<?php

declare(strict_types=1);

namespace Amelias\Database;

use PDO;
use PDOStatement;

/**
 * PDO singleton, the spine of every data operation.
 *
 * Guarantees, set once here so no caller can get them wrong:
 *   - Exception error mode (failures throw, never silently return false).
 *   - Real prepared statements (EMULATE_PREPARES = false), no SQL injection.
 *   - utf8mb4 connection.
 *   - Connection time_zone = '+00:00' so every timestamp is stored/compared in
 *     UTC; display conversion to America/Phoenix happens in fmt_date().
 *
 * All SQL MUST use bound parameters. String interpolation into SQL is banned
 * (the conventions audit enforces this).
 */
final class Database
{
    private static ?PDO $pdo = null;

    private function __construct()
    {
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect(config('db'));
        }
        return self::$pdo;
    }

    /** @param array<string,mixed> $db */
    public static function connect(array $db): PDO
    {
        $driver = $db['driver'] ?? 'mysql';

        if ($driver === 'sqlite') {
            // Test/CI fallback so the suite runs without a MySQL server.
            $pdo = new PDO('sqlite:' . ($db['path'] ?? ':memory:'));
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA foreign_keys = ON');
            return $pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db['host'] ?? '127.0.0.1',
            (int) ($db['port'] ?? 3306),
            $db['name'] ?? '',
            $db['charset'] ?? 'utf8mb4',
        );

        $pdo = new PDO($dsn, $db['user'] ?? '', $db['pass'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);

        // Store and compare everything in UTC, regardless of server tz.
        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->exec("SET NAMES " . ($db['charset'] ?? 'utf8mb4') . " COLLATE utf8mb4_unicode_ci");

        return $pdo;
    }

    /** Override the connection (used by tests to inject a sqlite PDO). */
    public static function set(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }

    /**
     * Run a prepared statement and return it.
     *
     * @param array<string,mixed>|list<mixed> $params
     */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** @return array<string,mixed>|null */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string,mixed>> */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function fetchValue(string $sql, array $params = []): mixed
    {
        return self::run($sql, $params)->fetchColumn();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    /** Affected-row count, used by the atomic-claim concurrency guard. */
    public static function affected(string $sql, array $params = []): int
    {
        return self::run($sql, $params)->rowCount();
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
