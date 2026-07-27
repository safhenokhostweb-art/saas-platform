<?php

namespace SaaSPlatform\CoreUtils\Support;

/**
 * Phase 17-F — Part 2: the official field contract for a module's
 * module.json. Formalizes the shape that forms/tickets/education already
 * follow in practice; does not invent a new structure.
 *
 * Deliberately additive: every field classified as "required" here is
 * already present on all real, distributable module.json files audited
 * during Phase 17-F (forms, tickets, education). motion-graphics is not a
 * distributable module (declares "distributable": false, has no `modules`
 * table row, and can never reach a ModuleRelease) and is exempt from this
 * schema by construction — it never flows through
 * ModuleReleaseProducerAdapter or ModulePackageBuilderService.
 *
 * `lifecycle` in the Phase 17-F request is a conceptual grouping, not a
 * literal module.json wrapper key: `install` already exists as a top-level
 * key on every real module.json (with `command`/`publishes`), so `upgrade`
 * and `uninstall` are added as siblings at the same top level rather than
 * nested under a new `lifecycle` object — introducing that wrapper would
 * require editing every existing module.json's structure, which is exactly
 * the kind of unnecessary redesign this phase rules out.
 *
 * This class only validates the manifest's own shape (pure array in,
 * array out — no filesystem, no ZIP, no Eloquent). Cross-checking a
 * manifest against a specific module_releases row is
 * App\Services\Modules\ModulePackageValidator's job (it needs the DB).
 */
class ModuleManifestSchema
{
    /**
     * Fields whose absence makes the manifest unusable — every real
     * module.json audited in Phase 17-F already has all of these.
     *
     * @var string[]
     */
    public const REQUIRED_FIELDS = [
        'name',
        'module_slug',
        'version',
        'type',
        'namespace',
        'provider',
    ];

    /**
     * Present on every real module.json today, but their absence doesn't
     * make the package undeployable — checked and reported as warnings,
     * never as hard failures. `display_name` is deliberately NOT in either
     * list: modules.title (DB, admin-editable) already owns the
     * customer/admin-facing display name; module.json's `name` is a
     * build-time label only. Duplicating it into module.json would create
     * a second source of truth for something the DB already owns.
     *
     * @var string[]
     */
    public const RECOMMENDED_FIELDS = [
        'compatibility',
        'update_type',
        'dependencies',
    ];

    /**
     * @param  array<string, mixed>  $manifest  decoded module.json content
     * @return array{valid: bool, errors: string[], warnings: string[]}
     */
    public function validate(array $manifest): array
    {
        $errors = [];
        $warnings = [];

        foreach (self::REQUIRED_FIELDS as $field) {
            if (!array_key_exists($field, $manifest) || $manifest[$field] === null || $manifest[$field] === '') {
                $errors[] = "module.json is missing required field: {$field}";
            }
        }

        foreach (self::RECOMMENDED_FIELDS as $field) {
            if (!array_key_exists($field, $manifest) || $manifest[$field] === null) {
                $warnings[] = "module.json is missing recommended field: {$field}";
            }
        }

        if (isset($manifest['module_slug']) && !preg_match('/^[a-z0-9][a-z0-9\-]*$/', (string) $manifest['module_slug'])) {
            $errors[] = "module.json module_slug must be lowercase alphanumeric with hyphens only, got: {$manifest['module_slug']}";
        }

        if (isset($manifest['version']) && !$this->isValidSemver((string) $manifest['version'])) {
            $errors[] = "module.json version is not valid semver: {$manifest['version']}";
        }

        if (isset($manifest['dependencies']) && !$this->isValidDependenciesShape($manifest['dependencies'])) {
            $warnings[] = 'module.json dependencies must be shaped as {"packages": [...], "modules": [...]}';
        }

        if (isset($manifest['uninstall']) && !$this->isValidUninstallShape($manifest['uninstall'])) {
            $warnings[] = 'module.json uninstall must be shaped as {"type": "soft"|"hard", "cleanup": [...]}';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function isValidSemver(string $version): bool
    {
        return (bool) preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.\-]+)?(\+[0-9A-Za-z.\-]+)?$/', $version);
    }

    private function isValidDependenciesShape(mixed $dependencies): bool
    {
        if (!is_array($dependencies)) {
            return false;
        }
        foreach (['packages', 'modules'] as $key) {
            if (isset($dependencies[$key]) && !is_array($dependencies[$key])) {
                return false;
            }
        }
        return true;
    }

    private function isValidUninstallShape(mixed $uninstall): bool
    {
        if (!is_array($uninstall)) {
            return false;
        }
        if (isset($uninstall['type']) && !in_array($uninstall['type'], ['soft', 'hard'], true)) {
            return false;
        }
        if (isset($uninstall['cleanup']) && !is_array($uninstall['cleanup'])) {
            return false;
        }
        return true;
    }
}
