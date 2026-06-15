<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Config;
use CityBus\Core\Database;
use CityBus\Core\Logger;
use CityBus\Core\Setting;
use CityBus\Models\AuditLog;

/**
 * Sauvegardes de la base MySQL.
 *
 * Stratégie :
 *   1) Tente d'abord mysqldump si disponible (rapide, fidèle, gère blobs).
 *   2) Sinon fallback PHP : SHOW CREATE TABLE + INSERT par batch.
 *
 * Les fichiers sont écrits dans storage/backups/ avec une convention de nom
 * citybus_YYYY-MM-DD_HHMMSS.sql et compressés en .gz si l'extension zlib est
 * disponible.
 */
final class BackupService
{
    private string $backupDir;

    public function __construct(?string $dir = null)
    {
        $this->backupDir = $dir ?: BASE_PATH . '/storage/backups';
        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0755, true);
        }
    }

    /**
     * Lance une sauvegarde et retourne le chemin du fichier créé.
     */
    public function run(?int $userId = null): string
    {
        $stamp    = date('Y-m-d_His');
        $filename = "citybus_{$stamp}.sql";
        $path     = $this->backupDir . '/' . $filename;

        $ok = $this->tryMysqldump($path);
        if (!$ok) {
            $this->phpDump($path);
        }

        // Compression gzip si dispo
        if (function_exists('gzopen')) {
            $gzPath = $path . '.gz';
            $in  = fopen($path, 'rb');
            $out = gzopen($gzPath, 'wb9');
            if ($in && $out) {
                while (!feof($in)) {
                    gzwrite($out, (string)fread($in, 1 << 20));
                }
                fclose($in);
                gzclose($out);
                @unlink($path);
                $path     = $gzPath;
                $filename = basename($gzPath);
            }
        }

        $size = (int)@filesize($path);
        AuditLog::record('backup.run', 'backup', null, [
            'file' => $filename,
            'size' => $size,
            'by'   => $userId,
        ]);

        $this->purgeOld();

        return $path;
    }

    /** Liste des sauvegardes existantes (les plus récentes d'abord). */
    public function list(): array
    {
        $files = glob($this->backupDir . '/citybus_*.sql*') ?: [];
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        return array_map(fn($f) => [
            'name'  => basename($f),
            'path'  => $f,
            'size'  => (int)filesize($f),
            'mtime' => (int)filemtime($f),
        ], $files);
    }

    public function delete(string $name): bool
    {
        // Validation stricte du nom — pas de path traversal
        if (!preg_match('/^citybus_\d{4}-\d{2}-\d{2}_\d{6}\.sql(\.gz)?$/', $name)) {
            return false;
        }
        $path = $this->backupDir . '/' . $name;
        if (!is_file($path)) return false;
        $ok = @unlink($path);
        if ($ok) {
            AuditLog::record('backup.delete', 'backup', null, ['file' => $name]);
        }
        return $ok;
    }

    public function path(string $name): ?string
    {
        if (!preg_match('/^citybus_\d{4}-\d{2}-\d{2}_\d{6}\.sql(\.gz)?$/', $name)) {
            return null;
        }
        $path = $this->backupDir . '/' . $name;
        return is_file($path) ? $path : null;
    }

    private function purgeOld(): void
    {
        $days = max(1, Setting::getInt('backup.retention_days', 30));
        $threshold = time() - $days * 86400;
        foreach ($this->list() as $f) {
            if ($f['mtime'] < $threshold) {
                @unlink($f['path']);
            }
        }
    }

    private function tryMysqldump(string $outPath): bool
    {
        $bin = $this->detectMysqldump();
        if (!$bin) return false;

        $host = Config::get('database.host', '127.0.0.1');
        $port = (int)Config::get('database.port', 3306);
        $name = Config::get('database.database', '');
        $user = Config::get('database.username', '');
        $pass = Config::get('database.password', '');

        // On évite de mettre le mot de passe sur la ligne de commande (visible via ps).
        // Utilisation d'une variable d'environnement MYSQL_PWD.
        $cmd = sprintf(
            '"%s" --host=%s --port=%d --user=%s --single-transaction --quick --routines --triggers --default-character-set=utf8mb4 %s 2>&1',
            $bin,
            escapeshellarg($host),
            $port,
            escapeshellarg($user),
            escapeshellarg($name)
        );

        $env  = ['MYSQL_PWD' => $pass];
        $desc = [1 => ['file', $outPath, 'w'], 2 => ['pipe', 'w']];
        $proc = @proc_open($cmd, $desc, $pipes, null, $env);
        if (!is_resource($proc)) return false;

        $err = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        if ($code !== 0) {
            Logger::warning('mysqldump failed', ['code' => $code, 'stderr' => $err]);
            @unlink($outPath);
            return false;
        }
        return is_file($outPath) && filesize($outPath) > 0;
    }

    private function detectMysqldump(): ?string
    {
        // Candidats classiques (XAMPP Windows + Linux)
        $candidates = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/opt/homebrew/bin/mysqldump',
        ];
        foreach ($candidates as $c) {
            if (is_file($c) && is_executable($c)) return $c;
        }
        // PATH
        $cmd = stripos(PHP_OS, 'WIN') === 0 ? 'where mysqldump' : 'which mysqldump';
        $out = trim((string)@shell_exec($cmd));
        if ($out !== '') {
            $first = strtok($out, "\n");
            if ($first && is_file($first)) return $first;
        }
        return null;
    }

    /** Fallback dump pur PHP (lent, suffisant pour des bases moyennes). */
    private function phpDump(string $outPath): void
    {
        $pdo = Database::connection();
        $f   = fopen($outPath, 'w');
        if (!$f) throw new \RuntimeException("Impossible d'écrire la sauvegarde");

        fwrite($f, "-- CityBus PHP dump\n-- Date: " . date('c') . "\n\n");
        fwrite($f, "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");

        $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(\PDO::FETCH_ASSOC);
            fwrite($f, "DROP TABLE IF EXISTS `$table`;\n");
            fwrite($f, ($create['Create Table'] ?? '') . ";\n\n");

            // Données par paquets de 500 lignes
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $batch = [];
            $cols  = null;
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                if ($cols === null) {
                    $cols = array_map(fn($c) => "`$c`", array_keys($row));
                }
                $vals = array_map(function ($v) use ($pdo) {
                    if ($v === null) return 'NULL';
                    if (is_int($v) || is_float($v)) return (string)$v;
                    return $pdo->quote((string)$v);
                }, array_values($row));
                $batch[] = '(' . implode(',', $vals) . ')';
                if (count($batch) >= 500) {
                    fwrite($f, "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES\n"
                        . implode(",\n", $batch) . ";\n\n");
                    $batch = [];
                }
            }
            if ($batch) {
                fwrite($f, "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES\n"
                    . implode(",\n", $batch) . ";\n\n");
            }
        }

        fwrite($f, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($f);
    }
}
