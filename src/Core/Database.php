<?php

declare(strict_types=1);

namespace CityBus\Core;

use PDO;
use PDOException;

/**
 * Singleton PDO. Connexion paresseuse (lazy).
 */
final class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $cfg = self::$config;
            $dsn = sprintf(
                '%s:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['connection'] ?? 'mysql',
                $cfg['host'],
                $cfg['port'],
                $cfg['database'],
                $cfg['charset']
            );

            try {
                self::$instance = new PDO(
                    $dsn,
                    $cfg['username'],
                    $cfg['password'],
                    $cfg['options'] ?? []
                );
                self::$instance->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
                self::$instance->exec("SET time_zone = '+01:00'");
            } catch (PDOException $e) {
                throw new \RuntimeException(
                    'Connexion BDD impossible : ' . $e->getMessage(),
                    (int) $e->getCode(),
                    $e
                );
            }
        }
        return self::$instance;
    }

    /** Raccourci : exécute une requête préparée et retourne toutes les lignes. */
    public static function select(string $sql, array $params = []): array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Retourne la 1re ligne ou null. */
    public static function selectOne(string $sql, array $params = []): ?array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Exécute INSERT/UPDATE/DELETE et retourne le nombre de lignes affectées. */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** INSERT et retourne le dernier ID. */
    public static function insert(string $sql, array $params = []): int
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) self::connection()->lastInsertId();
    }

    /** Wrapper transaction. */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
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
