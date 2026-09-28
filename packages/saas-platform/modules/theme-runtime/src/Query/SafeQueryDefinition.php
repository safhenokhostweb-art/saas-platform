<?php

namespace SaaSPlatform\ThemeRuntime\Query;

/**
 * Phase 3A — the safe Query Definition contract (foundation only).
 *
 * The reusable, non-SQL authority future query-powered widgets (product
 * grids/carousels, blog grids/sliders, category widgets) will bind to.
 * A query definition is PURE DATA: source + allowlisted filters/sort/
 * paging — no SQL, no model names, no client-chosen columns, no raw
 * where-strings. Only allowlisted fields/operators survive validation;
 * everything else fails closed. Execution is deliberately NOT in this
 * phase: hosts resolve definitions through their own tenant-scoped,
 * allowlisted resolvers (the existing Phase 4B.5/4B.6 seam).
 */
final class SafeQueryDefinition
{
    /** Allowlisted data sources. Each maps to a host resolver; unknown = reject. */
    public const SOURCES = ['product', 'product_category', 'blog_post', 'blog_category', 'menu'];

    /** Allowlisted order-by fields per conceptual group (safe column names only). */
    public const ORDER_FIELDS = [
        '*'            => ['created_at', 'updated_at', 'id', 'title'],
        'product'      => ['price', 'sale_price', 'stock', 'rating'],
        'product_category' => ['title'],
        'blog_post'    => ['published_at', 'title'],
        'blog_category' => ['title'],
        'menu'         => ['order'],
    ];

    /** Allowlisted filter operators (never raw expressions). */
    public const OPERATORS = ['=', '!=', '>', '>=', '<', '<=', 'in', 'not_in', 'like'];

    /** Paging modes. */
    public const PAGINATION_MODES = ['none', 'offset', 'cursor'];

    /**
     * Validate a query definition (array from schema props or a package).
     * Returns the normalized definition; throws on any violation.
     */
    public static function validate(array $query): array
    {
        $source = $query['source'] ?? null;
        if (!is_string($source) || !in_array($source, self::SOURCES, true)) {
            throw new \InvalidArgumentException(
                'Query source must be one of: ' . implode(',', self::SOURCES) . '.'
            );
        }

        // Filters: [{field, operator, value}] with allowlisted fields/operators.
        $filters = [];
        foreach ($query['filters'] ?? [] as $filter) {
            if (!is_array($filter)) {
                throw new \InvalidArgumentException('Query filters must be arrays.');
            }
            $field = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? '=';
            if (!is_string($field) || !self::fieldAllowed($source, $field)) {
                throw new \InvalidArgumentException("Query filter field '{$field}' is not allowlisted for source '{$source}'.");
            }
            if (!is_string($operator) || !in_array($operator, self::OPERATORS, true)) {
                throw new \InvalidArgumentException("Query filter operator '{$operator}' is not allowlisted.");
            }
            if (!is_scalar($filter['value'] ?? null) && !is_array($filter['value'] ?? null)) {
                throw new \InvalidArgumentException('Query filter value must be scalar or a list.');
            }
            $filters[] = ['field' => $field, 'operator' => $operator, 'value' => $filter['value']];
        }

        // Order: {by, direction} — allowlisted field per source.
        $order = null;
        if (isset($query['order'])) {
            if (!is_array($query['order'])) {
                throw new \InvalidArgumentException('Query order must be an object.');
            }
            $by = $query['order']['by'] ?? null;
            $direction = strtolower($query['order']['direction'] ?? 'asc');
            if (!is_string($by) || !self::fieldAllowed($source, $by)) {
                throw new \InvalidArgumentException("Query order field '{$by}' is not allowlisted for source '{$source}'.");
            }
            if (!in_array($direction, ['asc', 'desc'], true)) {
                throw new \InvalidArgumentException('Query order direction must be asc|desc.');
            }
            $order = ['by' => $by, 'direction' => $direction];
        }

        // Paging: bounded, integer-safe.
        $limit = $query['limit'] ?? 12;
        $offset = $query['offset'] ?? 0;
        if (!is_int($limit) || $limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Query limit must be an integer between 1 and 100.');
        }
        if (!is_int($offset) || $offset < 0 || $offset > 10000) {
            throw new \InvalidArgumentException('Query offset must be a non-negative integer ≤ 10000.');
        }
        $pagination = $query['pagination'] ?? 'none';
        if (!in_array($pagination, self::PAGINATION_MODES, true)) {
            throw new \InvalidArgumentException('Query pagination mode is not allowlisted.');
        }

        // include/exclude: lists of safe scalar ids/slugs.
        foreach (['include', 'exclude'] as $listKey) {
            if (isset($query[$listKey])) {
                if (!is_array($query[$listKey])) {
                    throw new \InvalidArgumentException("Query {$listKey} must be a list.");
                }
                foreach ($query[$listKey] as $entry) {
                    if (!is_scalar($entry)) {
                        throw new \InvalidArgumentException("Query {$listKey} entries must be scalar ids/slugs.");
                    }
                }
            }
        }

        return array_merge($query, [
            'source'     => $source,
            'filters'    => $filters,
            'order'      => $order,
            'limit'      => $limit,
            'offset'     => $offset,
            'pagination' => $pagination,
        ]);
    }

    private static function fieldAllowed(string $source, string $field): bool
    {
        return in_array($field, self::ORDER_FIELDS['*'], true)
            || in_array($field, self::ORDER_FIELDS[$source] ?? [], true);
    }
}
