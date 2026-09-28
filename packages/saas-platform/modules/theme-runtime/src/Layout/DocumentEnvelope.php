<?php

namespace SaaSPlatform\ThemeRuntime\Layout;

/**
 * Phase 2A — the canonical versioned document envelope: the outer shape a
 * page, header, footer, or future template part takes when it is carried
 * as a canonical layout document.
 *
 * The envelope is pure JSON (persisted through existing JSON seams —
 * Theme.pages / Theme.settings / ThemeTemplate.blocks — never new DB
 * columns) and is the boundary object between storage, the editor, and the
 * renderer. It carries:
 *   - schema version (layout_version — gates parsing, fail-closed),
 *   - zone/document type (page | header | footer | template_part),
 *   - direction (first-class data: rtl|ltr, fail-closed default),
 *   - locale when available (fa|ar|en|…),
 *   - the node tree itself (page node from LayoutContract::fromLegacyBlocks),
 *   - responsive contract metadata (breakpoint names, not per-widget values).
 *
 * Envelope keys a renderer/editor does not know are preserved verbatim on
 * a round trip (same lossless discipline as the node adapter).
 */
final class DocumentEnvelope
{
    public const SCHEMA_VERSION = 1;

    /** Document (zone) types the envelope can represent today. */
    public const TYPES = ['page', 'header', 'footer', 'template_part'];

    public const DEFAULT_TYPE = 'page';

    /**
     * Build an envelope around a page node (LayoutContract::fromLegacyBlocks
     * output or any valid node tree root). Unknown $overrides values fail
     * closed: an unsupported direction or type falls back to the canonical
     * default rather than being stored verbatim.
     *
     * @param array<string,mixed> $overrides direction|locale|type|title|extra keys
     */
    public static function make(array $pageNode, array $overrides = []): array
    {
        $direction = $overrides['direction'] ?? null;
        if (!is_string($direction) || !LayoutContract::isSupportedDirection($direction)) {
            $direction = LayoutContract::DEFAULT_DIRECTION;
        }

        $type = $overrides['type'] ?? null;
        if (!is_string($type) || !in_array($type, self::TYPES, true)) {
            $type = self::DEFAULT_TYPE;
        }

        $envelope = [
            'layout_version' => self::SCHEMA_VERSION,
            'type' => $type,
            'direction' => $direction,
            'locale' => $overrides['locale'] ?? ($direction === 'ltr' ? 'en' : 'fa'),
        ];

        // Optional human metadata (never authoritative).
        if (array_key_exists('title', $overrides)) {
            $envelope['title'] = $overrides['title'];
        }

        // Responsive contract metadata: which breakpoints this document
        // understands. Per-widget overrides live on the nodes themselves.
        $envelope['responsive'] = [
            'breakpoints' => LayoutContract::BREAKPOINTS,
            'max_widths' => LayoutContract::BREAKPOINT_MAX_WIDTH_BY_NAME,
        ];

        $envelope['page'] = $pageNode;

        // Preserve any other override keys verbatim (forward compatibility).
        foreach ($overrides as $key => $value) {
            if (!array_key_exists($key, $envelope)) {
                $envelope[$key] = $value;
            }
        }

        return $envelope;
    }

    /**
     * True when $document is structurally a valid envelope (fields present,
     * enums valid, version supported). Does NOT validate the inner node
     * tree — use DocumentValidator for the full fail-closed pass.
     */
    public static function isValid(mixed $document): bool
    {
        if (!is_array($document)) {
            return false;
        }

        $version = $document['layout_version'] ?? null;
        if ($version !== self::SCHEMA_VERSION) {
            return false;
        }

        $type = $document['type'] ?? self::DEFAULT_TYPE;
        if (!in_array($type, self::TYPES, true)) {
            return false;
        }

        $direction = $document['direction'] ?? null;
        if (!is_string($direction) || !LayoutContract::isSupportedDirection($direction)) {
            return false;
        }

        if (!array_key_exists('page', $document) || !is_array($document['page'])) {
            return false;
        }

        return true;
    }

    /**
     * Wrap a legacy block list in an envelope (the boundary conversion a
     * host performs when handing saved content to the canonical pipeline).
     */
    public static function fromLegacyBlocks(array $blocks, array $overrides = []): array
    {
        return self::make(LayoutContract::fromLegacyBlocks($blocks), $overrides);
    }

    /**
     * Unwrap an envelope back to the legacy block list (the boundary
     * conversion for persistence/public rendering).
     */
    public static function toLegacyBlocks(array $document): array
    {
        $page = $document['page'] ?? [];

        return LayoutContract::toLegacyBlocks($page);
    }

    /** Resolve the effective direction of an envelope (or any partial doc). */
    public static function direction(array $document): string
    {
        $direction = $document['direction'] ?? null;
        if (is_string($direction) && LayoutContract::isSupportedDirection($direction)) {
            return $direction;
        }

        $locale = $document['locale'] ?? null;
        if (is_string($locale) && $locale !== '') {
            return LayoutContract::directionForLocale($locale);
        }

        return LayoutContract::DEFAULT_DIRECTION;
    }
}
