<?php
namespace App\Services\ThemeBuilder\Extension;

use App\Models\Extension;
use App\Models\ExtensionRelease;
use App\Models\Theme;
use App\Services\ThemeBuilder\BlockRegistry;
use App\Services\ThemeBuilder\Dependency\ExtensionElementOwnershipResolver;
use App\Services\ThemeBuilder\Dependency\ThemeExtensionBlockTypeExtractor;
use SaaSPlatform\ExtensionSdk\ExtensionControlDefinition;
use SaaSPlatform\ExtensionSdk\ExtensionElementDefinition;
use SaaSPlatform\ExtensionSdk\ExtensionManifest;
use SaaSPlatform\ExtensionSdk\ExtensionProductIdentity;
use Throwable;

/**
 * Phase 3G-B-6 — the ONE read-model adapter that turns a Theme's already
 * -selected commercial Extension releases (Theme.settings.extension_selections,
 * written by 3G-B-6's Canvas save path) into palette entries the EXISTING
 * SaaS Theme Canvas already knows how to render: the exact
 * `App\Services\ThemeBuilder\BlockRegistry::all()` shape (name/category/icon/
 * is_container/schema/defaultProps), keyed by the element's real
 * `ext:{vendor}:{block}` identity.
 *
 * Deliberately NOT wired through BlockRegistry::registerHostType(): that
 * registration is process-wide/static and shared with customer-site's own
 * runtime consumption of the same canonical package — two different Themes
 * can legitimately select two different releases of the same product (or
 * different products entirely), so palette composition must stay a per
 * -request, per-Theme array merge, never global mutable registry state.
 *
 * Never executes third-party/customer provider code: only ever reads
 * Extension/ExtensionRelease rows and parses their already-validated
 * ExtensionManifest — pure data in, array out.
 */
final class ExtensionCanvasPaletteAdapter
{
    /** Every BlockRegistry category an Extension element may declare and still render under its native palette section. */
    private const KNOWN_CATEGORIES = [
        BlockRegistry::CATEGORY_LAYOUT,
        BlockRegistry::CATEGORY_CONTENT,
        BlockRegistry::CATEGORY_DYNAMIC,
        BlockRegistry::CATEGORY_ECOMMERCE,
        BlockRegistry::CATEGORY_MARKETING,
        BlockRegistry::CATEGORY_UTILITY,
        BlockRegistry::CATEGORY_MODULE,
    ];

    /** Fallback bucket for a manifest that declares no recognized canonical category — never dropped, never hidden. */
    public const FALLBACK_CATEGORY = 'extension';

    /**
     * @return array<string, array{name:string,category:string,icon:string,is_container:bool,schema:array,defaultProps:array}>
     *   Keyed by `ext:{vendor}:{block}`. Only elements belonging to a product
     *   whose currently-recorded Theme selection resolves to a real,
     *   parseable ExtensionRelease are included — an unresolvable historical
     *   selection contributes zero NEW palette entries (existing persisted
     *   content is unaffected; see resolveSelections()' errors for that case).
     */
    public static function paletteFor(Theme $theme): array
    {
        $resolved = self::resolveSelections($theme->settings['extension_selections'] ?? []);

        $palette = [];
        foreach ($resolved['manifests'] as $manifest) {
            foreach ($manifest->elements as $type => $element) {
                $palette[$type] = self::elementToDefinition($element);
            }
        }

        return $palette;
    }

    /**
     * Phase 3G-B-6 closure (§5-§6) — the subset of paletteFor() that is safe
     * to OFFER for NEW authoring: an `ext:*` type whose selected release
     * declares it AND whose canonical, platform-wide ownership (the ONE
     * authority, ExtensionElementOwnershipResolver — never re-derived here)
     * resolves to that exact same, real (FK-verified) product identity.
     *
     * Deliberately NOT used for rendering support (BlockRenderer container
     * detection, live preview): a type that legacy/direct-DB data has since
     * made UNRESOLVED/AMBIGUOUS/wrong-owner must still render correctly if
     * it was already persisted historically — this method only narrows what
     * a Canvas author may newly pick from the palette, it never hides
     * already-authored content from its own renderer. See paletteFor()'s
     * unfiltered use in ThemeCanvasController::extContainerTypesFor().
     */
    public static function authorablePaletteFor(Theme $theme): array
    {
        $resolved = self::resolveSelections($theme->settings['extension_selections'] ?? []);
        $valid = self::ownershipValidTypes($resolved['manifests']);

        return array_intersect_key(self::paletteFor($theme), $valid);
    }

    /**
     * @param array<string, ExtensionManifest> $manifests real (FK-verified) product identity => its resolved manifest
     * @return array<string, true> only the element types whose sole canonical owner
     *   (ExtensionElementOwnershipResolver) matches the product identity that
     *   actually supplied them here — excludes unresolved/ambiguous/wrong-owner.
     */
    private static function ownershipValidTypes(array $manifests): array
    {
        $allTypes = [];
        foreach ($manifests as $manifest) {
            foreach (array_keys($manifest->elements) as $type) {
                $allTypes[$type] = true;
            }
        }

        if ($allTypes === []) {
            return [];
        }

        $ownership = (new ExtensionElementOwnershipResolver())->resolve(array_keys($allTypes));

        $valid = [];
        foreach ($manifests as $productIdentity => $manifest) {
            foreach (array_keys($manifest->elements) as $type) {
                $result = $ownership[$type] ?? null;
                if ($result !== null && $result->isResolved() && $result->productIdentity === $productIdentity) {
                    $valid[$type] = true;
                }
            }
        }

        return $valid;
    }

    /**
     * @param array<string, array{version?: string}|string> $selections product identity => version (or `['version' => ...]`)
     * @return array{
     *   manifests: array<string, ExtensionManifest>,
     *   errors: array<string, string>,
     * } manifests/errors both keyed by product identity
     */
    public static function resolveSelections(array $selections): array
    {
        $manifests = [];
        $errors = [];

        foreach ($selections as $productIdentity => $value) {
            $version = is_array($value) ? ($value['version'] ?? null) : $value;
            if (!is_string($version) || $version === '') {
                $errors[$productIdentity] = 'no version recorded for this selection.';
                continue;
            }

            if (!ExtensionProductIdentity::isValidIdentifier($productIdentity)) {
                $errors[$productIdentity] = 'not a valid "{vendor}/{package}" product identity.';
                continue;
            }

            $identity = ExtensionProductIdentity::fromString($productIdentity);
            $extension = Extension::where('vendor_slug', $identity->vendorSlug())
                ->where('package_slug', $identity->packageSlug())
                ->first();

            if ($extension === null) {
                $errors[$productIdentity] = "no Extension product \"{$productIdentity}\" exists.";
                continue;
            }

            /** @var ExtensionRelease|null $release */
            $release = $extension->releases()->where('version', $version)->first();
            if ($release === null) {
                $errors[$productIdentity] = "Extension \"{$productIdentity}\" has no release version \"{$version}\".";
                continue;
            }

            try {
                $manifests[$productIdentity] = ExtensionManifest::fromArray($release->manifest);
            } catch (Throwable) {
                $errors[$productIdentity] = "the manifest for \"{$productIdentity}\" release \"{$version}\" is not a valid ExtensionManifest.";
            }
        }

        return ['manifests' => $manifests, 'errors' => $errors];
    }

    /**
     * Extension products with at least one currently-selectable (published)
     * release — the NEW-selection policy (3G-B-6 §6): a superseded/
     * deprecated release is never offered for a brand-new selection, even
     * though it remains fully resolvable for content authored against it
     * historically (resolveSelections() above never filters on status, by
     * design — matching ExtensionVersionSelectionResolver's own policy).
     *
     * @return array<int, array{identifier:string, name:string, releases: array<int, array{version:string, sdk_contract_version:int}>}>
     */
    public static function selectableProducts(): array
    {
        $products = [];

        Extension::with(['releases' => function ($query) {
            $query->where('status', ExtensionRelease::STATUS_PUBLISHED)->orderBy('version');
        }])->get()->each(function (Extension $extension) use (&$products) {
            if ($extension->releases->isEmpty()) {
                return;
            }

            $products[] = [
                'identifier' => $extension->identifier(),
                'name' => $extension->name,
                'releases' => $extension->releases->map(fn (ExtensionRelease $r) => [
                    'version' => $r->version,
                    'sdk_contract_version' => $r->sdk_contract_version,
                ])->values()->all(),
            ];
        });

        return $products;
    }

    /**
     * Canvas save-boundary gate (3G-B-6 §12): every `ext:*` type found
     * anywhere in the submitted block tree must belong to a product+release
     * that resolves cleanly from $selections. Fails closed as ONE combined
     * outcome — either the whole save is rejected with an actionable
     * diagnostic, or it is accepted; never a partial acceptance.
     *
     * Deliberately does not consult ExtensionElementOwnershipResolver here:
     * that class answers a platform-wide catalog question ("who legitimately
     * owns this type across every Extension ever released") used at
     * publish/dependency-derivation time. This save-time gate asks a
     * narrower, Theme-local question — "is this type actually present in
     * the release THIS Theme says it selected" — which is all Canvas
     * authoring is responsible for asserting (§25: Canvas only authors
     * truthful Theme state; ownership/eligibility stay backend-service
     * concerns).
     *
     * Phase 3G-B-6A (§11-§17) additionally gates NEW `ext:*` INSTANCE usage
     * — behind canonical ownership (ExtensionElementOwnershipResolver): the
     * selected release containing the type is necessary but no longer
     * sufficient — the type's sole canonical owner must also be the exact
     * real product that selection points to.
     *
     * Deliberately INSTANCE-, not TYPE-, scoped (3G-B-6's original closure
     * exempted an entire type once any instance of it existed historically —
     * a real gap: it let one historical ambiguous block silently authorize
     * unlimited NEW instances of the same type). A submitted block instance
     * is exempt from the ownership re-check only if its exact `(id, type)`
     * pair already existed anywhere in $previousInstances (this Theme's
     * FULL prior snapshot — header/footer/every page — before this save);
     * `id` is the existing stable authoring identity the Canvas client
     * already assigns and re-generates on clone (see
     * ThemeExtensionBlockTypeExtractor::extractInstancesFromSnapshot()'s own
     * doc comment) — no second identifier is invented here. A block moved
     * between valid locations keeps its id and stays exempt; a genuinely
     * new or duplicated instance does not. A type's ownership status is
     * only ever evaluated once per save even if used by several NEW
     * instances.
     *
     * @param array $blocks the single page's submitted block tree being saved
     * @param array<string, array{version?: string}|string> $selections the FULL, final extension_selections map this save will persist
     * @param array<int, array{id: ?string, type: string}> $previousInstances every `ext:*` occurrence anywhere in this Theme's PRIOR persisted snapshot, before this save
     * @return array{ok: bool, error: ?string}
     */
    public static function validateBlocksAgainstSelections(array $blocks, array $selections, array $previousInstances = []): array
    {
        $resolved = self::resolveSelections($selections);

        if ($resolved['errors'] !== []) {
            $parts = [];
            foreach ($resolved['errors'] as $product => $reason) {
                $parts[] = "{$product}: {$reason}";
            }

            return ['ok' => false, 'error' => 'انتخاب نسخه افزونه نامعتبر است — ' . implode(' | ', $parts)];
        }

        $available = [];
        foreach ($resolved['manifests'] as $productIdentity => $manifest) {
            foreach (array_keys($manifest->elements) as $type) {
                $available[$type] = $productIdentity;
            }
        }

        $extractor = new ThemeExtensionBlockTypeExtractor();
        $submittedInstances = $extractor->extractInstancesFromSnapshot([], [], ['_page' => ['blocks' => $blocks]]);

        $used = [];
        foreach ($submittedInstances as $instance) {
            $used[$instance['type']] = true;
        }
        $used = array_keys($used);

        $missing = array_values(array_filter($used, static fn (string $t) => !isset($available[$t])));

        if ($missing !== []) {
            return [
                'ok' => false,
                'error' => 'این نوع بلوک‌ها در نسخه افزونه انتخاب‌شده فعلی موجود نیستند: ' . implode(', ', $missing),
            ];
        }

        $previousKeys = [];
        foreach ($previousInstances as $instance) {
            if ($instance['id'] !== null) {
                $previousKeys[$instance['id'] . '::' . $instance['type']] = true;
            }
        }

        $newTypes = [];
        foreach ($submittedInstances as $instance) {
            $key = $instance['id'] !== null ? $instance['id'] . '::' . $instance['type'] : null;
            if ($key === null || !isset($previousKeys[$key])) {
                $newTypes[$instance['type']] = true;
            }
        }
        $newTypes = array_values(array_intersect(array_keys($newTypes), array_keys($available)));

        if ($newTypes !== []) {
            $ownership = (new ExtensionElementOwnershipResolver())->resolve($newTypes);

            $wrongOwnership = [];
            foreach ($newTypes as $type) {
                $result = $ownership[$type];
                if (!$result->isResolved() || $result->productIdentity !== $available[$type]) {
                    $wrongOwnership[] = $type;
                }
            }

            if ($wrongOwnership !== []) {
                return [
                    'ok' => false,
                    'error' => 'مالکیت این نوع بلوک(های) جدید افزونه تایید نمی‌شود: ' . implode(', ', $wrongOwnership),
                ];
            }
        }

        return ['ok' => true, 'error' => null];
    }

    /**
     * Phase 3G-B-6A (§2, §7) — ext:* type => its manifest-declared, already
     * -validated `{css: string[], js: string[]}` asset references (see
     * ExtensionElementDefinition's own doc comment for the exact reference
     * contract). Reads ONLY the currently-selected release manifests — never
     * customer/provider code, never a second asset authority. Deliberately
     * unfiltered by ownership (same rationale as paletteFor(): this feeds
     * RENDERING, which must keep working for already-persisted historical
     * content even if legacy/direct-DB data has since made ownership
     * ambiguous — see authorablePaletteFor()'s doc comment).
     *
     * @return array<string, array{css: string[], js: string[]}>
     */
    public static function elementAssetsFor(Theme $theme): array
    {
        $resolved = self::resolveSelections($theme->settings['extension_selections'] ?? []);

        $assets = [];
        foreach ($resolved['manifests'] as $manifest) {
            foreach ($manifest->elements as $type => $element) {
                $assets[$type] = $element->assets;
            }
        }

        return $assets;
    }

    /**
     * Phase 3G-B-6A (§7) — the declarative asset EMISSION seam: for the
     * ext:* types actually present in $blocks, the deduplicated, type
     * -ordered (deterministic — extractFromSnapshot's own lexical type sort)
     * union of their manifest-declared assets. An Extension type declared in
     * a selected release but never used in $blocks contributes nothing; the
     * same href/src required by two different used elements (or two
     * instances of the same element) is emitted once; two different
     * Extensions' assets never mix because each type's assets come only
     * from its own element definition. Never throws on an unresolved type —
     * save-boundary validation is what gates authoring; rendering stays
     * defensive.
     *
     * @return array{css: string[], js: string[]}
     */
    public static function assetsUsedIn(array $blocks, Theme $theme): array
    {
        $elementAssets = self::elementAssetsFor($theme);
        $used = (new ThemeExtensionBlockTypeExtractor())->extractFromSnapshot([], [], ['_page' => ['blocks' => $blocks]]);

        $css = [];
        $js = [];
        foreach ($used as $type) {
            $typeAssets = $elementAssets[$type] ?? null;
            if ($typeAssets === null) {
                continue;
            }

            foreach ($typeAssets['css'] as $href) {
                if (!in_array($href, $css, true)) {
                    $css[] = $href;
                }
            }
            foreach ($typeAssets['js'] as $src) {
                if (!in_array($src, $js, true)) {
                    $js[] = $src;
                }
            }
        }

        return ['css' => $css, 'js' => $js];
    }

    private static function elementToDefinition(ExtensionElementDefinition $element): array
    {
        $category = in_array($element->category, self::KNOWN_CATEGORIES, true)
            ? $element->category
            : self::FALLBACK_CATEGORY;

        $schema = [];
        foreach ($element->controls as $key => $control) {
            $schema[$key] = self::controlToSchemaField($control);
        }

        return [
            'name' => $element->label,
            'category' => $category,
            'icon' => $element->icon,
            'is_container' => $element->isContainer,
            'schema' => $schema,
            'defaultProps' => $element->defaults(),
        ];
    }

    /**
     * Maps the SDK's 10 declarative control types onto the exact vocabulary
     * the EXISTING Canvas property panel already renders generically
     * (canvas.blade.php's `schemaDef.type` loop) — no new control widgets,
     * no per-element branching. `spacing`/`responsive` pass through
     * unmapped: the canonical Canvas property panel does not render a
     * generic editor for those two schema types today for canonical blocks
     * either (e.g. `section.padding`), so Extension controls of that family
     * get the identical, pre-existing baseline treatment — never worse,
     * never a bespoke fix invented only for Extensions.
     */
    private static function controlToSchemaField(ExtensionControlDefinition $control): array
    {
        $type = match ($control->controlType) {
            ExtensionControlDefinition::TEXT => 'string',
            ExtensionControlDefinition::TEXTAREA => 'textarea',
            ExtensionControlDefinition::NUMBER => 'number',
            ExtensionControlDefinition::TOGGLE => 'boolean',
            ExtensionControlDefinition::SELECT => 'select',
            ExtensionControlDefinition::COLOR => 'color',
            ExtensionControlDefinition::MEDIA => 'image',
            ExtensionControlDefinition::URL => 'string',
            default => $control->controlType, // spacing / responsive — passthrough, see docblock above
        };

        $field = [
            'type' => $type,
            'default' => $control->default,
        ];

        if ($control->label !== null) {
            $field['label'] = $control->label;
        }

        if ($control->options !== null) {
            $field['options'] = $control->options;
        }

        return $field;
    }
}
