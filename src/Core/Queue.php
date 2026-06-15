<?php

declare(strict_types=1);

namespace CityBus\Core;

/**
 * Queue async backed by `jobs` table.
 * Worker : `php bin/queue-worker.php` (boucle infinie).
 */
final class Queue
{
    public static function dispatch(string $jobClass, array $payload = [], string $queue = 'default', int $delaySeconds = 0): int
    {
        if (!class_exists($jobClass)) {
            throw new \InvalidArgumentException("Job class introuvable: $jobClass");
        }
        return Database::insert('jobs', [
            'queue'        => $queue,
            'job_class'    => $jobClass,
            'payload'      => json_encode($payload),
            'available_at' => date('Y-m-d H:i:s', time() + $delaySeconds),
        ]);
    }

    /** Récupère un job pending et le marque processing (lock optimiste). */
    public static function reserve(string $queue = 'default'): ?array
    {
        return Database::transaction(function() use ($queue) {
            $job = Database::selectOne(
                "SELECT * FROM jobs
                 WHERE queue = ? AND status = 'pending' AND available_at <= NOW()
                 ORDER BY id ASC LIMIT 1 FOR UPDATE SKIP LOCKED",
                [$queue]
            );
            if (!$job) return null;
            Database::update('jobs', [
                'status'     => 'processing',
                'attempts'   => (int)$job['attempts'] + 1,
                'started_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$job['id']]);
            return $job;
        });
    }

    public static function done(int $jobId): void
    {
        Database::update('jobs', [
            'status' => 'done',
            'finished_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$jobId]);
    }

    public static function fail(int $jobId, string $error, bool $retry = true): void
    {
        $job = Database::selectOne("SELECT * FROM jobs WHERE id = ?", [$jobId]);
        if (!$job) return;
        $attempts = (int)$job['attempts'];
        $max = (int)$job['max_attempts'];
        if ($retry && $attempts < $max) {
            $delay = min(3600, 30 * (2 ** $attempts)); // backoff exponentiel
            Database::update('jobs', [
                'status'        => 'pending',
                'available_at'  => date('Y-m-d H:i:s', time() + $delay),
                'error'         => $error,
            ], 'id = ?', [$jobId]);
        } else {
            Database::insert('jobs_failed', [
                'job_class' => $job['job_class'],
                'payload'   => $job['payload'],
                'error'     => $error,
            ]);
            Database::update('jobs', [
                'status'      => 'failed',
                'error'       => $error,
                'finished_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$jobId]);
        }
    }

    public static function process(array $job): void
    {
        $class = $job['job_class'];
        $payload = json_decode($job['payload'], true) ?: [];
        try {
            if (!class_exists($class)) throw new \RuntimeException("Job class missing: $class");
            $instance = new $class();
            if (!method_exists($instance, 'handle')) throw new \RuntimeException("$class::handle missing");
            $instance->handle($payload);
            self::done((int)$job['id']);
        } catch (\Throwable $e) {
            self::fail((int)$job['id'], $e->getMessage());
        }
    }
}
