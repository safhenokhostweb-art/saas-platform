<?php

namespace App\Support\DataPurge;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * One table, or one slice of a table, that a TablePurger removes. Every row
 * a target touches goes into the snapshot first, so TablePurger::restore()
 * can put it back exactly.
 *
 * Keep this file identical in saas-platform and customer-site.
 */
final class PurgeTarget
{
    private function __construct(
        public readonly string $table,
        private readonly ?Closure $scope,
        public readonly ?string $nullColumn,
        public readonly bool $resetIds,
    ) {
    }

    /**
     * Delete every row. $resetIds restarts the auto-increment afterwards;
     * pass false when the ids are used outside the table (file paths, other
     * applications), so a new row can never inherit an old row's identity.
     */
    public static function table(string $table, bool $resetIds = true): self
    {
        return new self($table, null, null, $resetIds);
    }

    /** Delete only the rows $scope selects. Ids are never reset. */
    public static function rows(string $table, Closure $scope): self
    {
        return new self($table, $scope, null, false);
    }

    /** Set $column to null wherever it is set; the rows themselves stay. */
    public static function detach(string $table, string $column): self
    {
        return new self($table, fn (Builder $query) => $query->whereNotNull($column), $column, false);
    }

    public function name(): string
    {
        return $this->nullColumn === null ? $this->table : "{$this->table}.{$this->nullColumn}";
    }

    public function isWholeTable(): bool
    {
        return $this->scope === null;
    }

    public function query(): Builder
    {
        $query = DB::table($this->table);
        if ($this->scope !== null) {
            ($this->scope)($query);
        }

        return $query;
    }
}
