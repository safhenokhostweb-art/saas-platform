<?php

namespace App\Support\DataPurge;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Snapshot-first purge of an ordered list of PurgeTargets.
 *
 * The recovery path comes before the delete (constitution §5): snapshot()
 * writes every affected row to JSON-lines files plus a manifest of row counts
 * and SHA-256 digests, then reads them back. purge() refuses to delete unless
 * that verified snapshot still matches the live row counts, and deletes in one
 * transaction. restore() re-inserts a verified snapshot into empty tables.
 *
 * Targets are listed in delete order: children before parents, so no RESTRICT
 * key ever fires and no CASCADE reaches a row the snapshot does not hold.
 *
 * Keep this file identical in saas-platform and customer-site.
 */
final class TablePurger
{
    public const FORMAT = 'table-purge-snapshot/1';

    private const CHUNK = 500;

    /** @param list<PurgeTarget> $targets */
    public function __construct(
        private readonly string $kind,
        private readonly array $targets,
    ) {
    }

    /** @return array<string, int> rows each target would touch, in delete order */
    public function plan(): array
    {
        $plan = [];
        foreach ($this->targets as $target) {
            $plan[$target->name()] = $target->query()->count();
        }

        return $plan;
    }

    /** Write and read back a snapshot of every row the purge would touch. */
    public function snapshot(string $dir): array
    {
        if (is_dir($dir) && (new \FilesystemIterator($dir))->valid()) {
            throw new RuntimeException("پوشهٔ snapshot خالی نیست: {$dir}");
        }
        File::ensureDirectoryExists($dir, 0700);

        $entries = [];
        foreach ($this->targets as $index => $target) {
            $file = sprintf('%02d-%s.jsonl', $index + 1, $target->name());
            $handle = fopen($dir.DIRECTORY_SEPARATOR.$file, 'xb');
            $rows = 0;

            $query = $target->query();
            if ($target->nullColumn !== null) {
                $query->select(['id', $target->nullColumn]);
            }
            foreach ($query->lazyById(self::CHUNK) as $row) {
                fwrite($handle, json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
                $rows++;
            }
            fclose($handle);

            $entries[] = [
                'name' => $target->name(),
                'table' => $target->table,
                'null_column' => $target->nullColumn,
                'file' => $file,
                'rows' => $rows,
                'sha256' => hash_file('sha256', $dir.DIRECTORY_SEPARATOR.$file),
            ];
        }

        $manifest = [
            'format' => self::FORMAT,
            'kind' => $this->kind,
            'database' => DB::connection()->getDatabaseName(),
            'driver' => DB::connection()->getDriverName(),
            'created_at' => now()->toIso8601String(),
            'entries' => $entries,
        ];
        file_put_contents(
            $dir.DIRECTORY_SEPARATOR.'manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        return $this->verify($dir);
    }

    /**
     * Re-read a snapshot and prove it is whole: same kind, same database, the
     * same targets in the same order, and every file matching its digest and
     * row count. Throws on the first mismatch.
     */
    public function verify(string $dir): array
    {
        $manifestPath = $dir.DIRECTORY_SEPARATOR.'manifest.json';
        if (!is_file($manifestPath)) {
            throw new RuntimeException("manifest.json در {$dir} پیدا نشد.");
        }
        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        if (($manifest['format'] ?? null) !== self::FORMAT || ($manifest['kind'] ?? null) !== $this->kind) {
            throw new RuntimeException('این snapshot متعلق به این فرمان نیست.');
        }
        if (($manifest['database'] ?? null) !== DB::connection()->getDatabaseName()) {
            throw new RuntimeException("این snapshot از پایگاه داده «{$manifest['database']}» است، نه «".DB::connection()->getDatabaseName().'».');
        }

        $expected = array_map(fn (PurgeTarget $t) => $t->name(), $this->targets);
        $actual = array_column($manifest['entries'] ?? [], 'name');
        if ($expected !== $actual) {
            throw new RuntimeException('فهرست جدول‌های snapshot با فهرست فعلی پاک‌سازی یکی نیست.');
        }

        foreach ($manifest['entries'] as $entry) {
            $path = $dir.DIRECTORY_SEPARATOR.basename($entry['file']);
            if (!is_file($path) || hash_file('sha256', $path) !== $entry['sha256']) {
                throw new RuntimeException("فایل {$entry['file']} وجود ندارد یا تغییر کرده است.");
            }
            $rows = 0;
            foreach ($this->readRows($path) as $row) {
                $rows++;
            }
            if ($rows !== $entry['rows']) {
                throw new RuntimeException("{$entry['file']}: {$rows} ردیف خوانده شد، {$entry['rows']} انتظار می‌رفت.");
            }
        }

        return $manifest;
    }

    /**
     * Delete everything the verified snapshot in $dir holds.
     *
     * @return array{removed: array<string, int>, ids_reset: list<string>, ids_reset_skipped: bool}
     */
    public function purge(string $dir): array
    {
        $snapshot = array_column($this->verify($dir)['entries'], 'rows', 'name');

        $removed = DB::transaction(function () use ($snapshot) {
            foreach ($this->targets as $target) {
                $live = $target->query()->lockForUpdate()->count();
                if ($live !== $snapshot[$target->name()]) {
                    throw new RuntimeException(
                        "{$target->name()} پس از snapshot تغییر کرده است ({$snapshot[$target->name()]} → {$live} ردیف). هیچ داده‌ای حذف نشد."
                    );
                }
            }

            $removed = [];
            foreach ($this->targets as $target) {
                $removed[$target->name()] = $target->nullColumn !== null
                    ? $target->query()->update([$target->nullColumn => null])
                    : $target->query()->delete();
            }

            return $removed;
        });

        // ALTER TABLE commits implicitly on MySQL, so it would also commit any
        // transaction wrapped around this call (a test, a caller's unit of
        // work). Reset ids only from the top level.
        $reset = [];
        $skipped = DB::transactionLevel() > 0;
        if (!$skipped) {
            foreach ($this->targets as $target) {
                if ($target->isWholeTable() && $target->nullColumn === null && $target->resetIds && $this->resetAutoIncrement($target->table)) {
                    $reset[] = $target->table;
                }
            }
        }

        return ['removed' => $removed, 'ids_reset' => $reset, 'ids_reset_skipped' => $skipped];
    }

    /**
     * Put a verified snapshot back. Fails closed unless every target is empty
     * now, so a restore never merges into, or overwrites, newer data.
     *
     * @return array<string, int> rows restored per target
     */
    public function restore(string $dir): array
    {
        $manifest = $this->verify($dir);

        foreach ($this->targets as $target) {
            if ($target->nullColumn === null && $target->query()->exists()) {
                throw new RuntimeException("{$target->name()} خالی نیست؛ بازگردانی انجام نشد. ابتدا داده‌های فعلی را پاک کنید.");
            }
        }

        // FK checks off: the snapshot was consistent when taken, and rows go
        // back parents-first, but self-references and cycles (themes ↔
        // theme_releases) cannot be ordered. The flag is set outside the
        // transaction because SQLite ignores the pragma inside one.
        return Schema::withoutForeignKeyConstraints(fn () => DB::transaction(function () use ($dir, $manifest) {
            $restored = [];
            foreach (array_reverse($manifest['entries']) as $entry) {
                $path = $dir.DIRECTORY_SEPARATOR.basename($entry['file']);
                $count = 0;
                $chunk = [];
                foreach ($this->readRows($path) as $row) {
                    $count++;
                    if ($entry['null_column'] !== null) {
                        DB::table($entry['table'])->where('id', $row['id'])->update([$entry['null_column'] => $row[$entry['null_column']]]);
                        continue;
                    }
                    $chunk[] = $row;
                    if (count($chunk) === self::CHUNK) {
                        DB::table($entry['table'])->insert($chunk);
                        $chunk = [];
                    }
                }
                if ($chunk !== []) {
                    DB::table($entry['table'])->insert($chunk);
                }
                $restored[$entry['name']] = $count;
            }

            return $restored;
        }));
    }

    /** @return \Generator<int, array<string, mixed>> */
    private function readRows(string $path): \Generator
    {
        $handle = fopen($path, 'rb');
        try {
            while (($line = fgets($handle)) !== false) {
                if ($line !== "\n" && $line !== '') {
                    yield json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                }
            }
        } finally {
            fclose($handle);
        }
    }

    private function resetAutoIncrement(string $table): bool
    {
        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE '.DB::getQueryGrammar()->wrapTable($table).' AUTO_INCREMENT = 1');

            return true;
        }
        if ($driver === 'sqlite' && Schema::hasTable('sqlite_sequence')) {
            DB::table('sqlite_sequence')->where('name', $table)->delete();

            return true;
        }

        return false;
    }
}
