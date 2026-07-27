<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\ModuleRelease;
use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Support\ModuleManifestSchema;
use SaaSPlatform\CoreUtils\Support\ModuleVersionRegistry;

/**
 * Phase 17-H — Part 3/6: Module Release Publishing Contract.
 *
 * Marks an existing module_releases row as published — the only state
 * ModuleDeploymentRequestService/ModuleUpdateEligibilityService/
 * DeploymentResolver will ever select for install/upgrade/display (see
 * ModuleRelease::latestPublishedForModule(), Part 7). Never deploys
 * anything itself — it only flips the gate that lets some future deploy
 * request select this release.
 *
 * Publishing contract checked before the flip (Part 3):
 *  - a module_releases row for {slug}/{version} exists at all
 *  - module.json (current, on disk) passes ModuleManifestSchema — covers
 *    manifest validity, compatibility declared, dependencies well-formed
 *  - the release has a package (package_sha256 set) — implies it already
 *    passed ModulePackageValidator at build time (Phase 17-F), since a
 *    release can never get package_sha256 any other way
 *  - the release isn't already deprecated (an operator decision this
 *    command must not silently reverse)
 */
class ModulePublishCommand extends Command
{
    protected $signature = 'module:publish {slug} {version}';

    protected $description = 'Publish a module release, making it eligible for customer install/upgrade';

    public function handle(ModuleManifestSchema $schema, ModuleVersionRegistry $registry): int
    {
        $slug = $this->argument('slug');
        $version = $this->argument('version');

        $module = Module::where('slug', $slug)->first();
        if (!$module) {
            $this->error("Module [{$slug}] not found in the modules table.");
            return self::FAILURE;
        }

        $release = ModuleRelease::where('module_id', $module->id)->where('version', $version)->first();
        if (!$release) {
            $this->error("No module_releases row for {$slug} v{$version}. Run `module:release {$slug} {$version}` first.");
            return self::FAILURE;
        }

        if ($release->status === ModuleRelease::STATUS_DEPRECATED) {
            $this->error("Release {$slug} v{$version} is deprecated — publishing a deprecated release is not allowed. Create a new release instead.");
            return self::FAILURE;
        }

        $errors = [];

        // Manifest valid / dependencies well-formed
        $registry->flush();
        $manifestData = $registry->get($slug);
        if (!$manifestData) {
            $errors[] = 'module.json could not be read from packages/saas-platform/modules/' . $slug . '.';
        } else {
            $schemaResult = $schema->validate($manifestData);
            array_push($errors, ...$schemaResult['errors']);

            // Part 3: compatibility is only a WARNING in the general
            // ModuleManifestSchema (build-time lint) — publishing to
            // Marketplace is a stricter, later gate, so it's a hard
            // requirement here specifically.
            if (empty($manifestData['compatibility'])) {
                $errors[] = 'module.json has no compatibility block declared — required before a release may be published.';
            }
        }

        // Package + SHA256 exist — implies it already passed
        // ModulePackageValidator when built (Phase 17-F).
        if (empty($release->package_sha256) || empty($release->package_path)) {
            $errors[] = 'No package artifact registered for this release. Run `module:package-build ' . $slug . ' ' . $version . '` (or `module:release` again) first.';
        }

        if (!empty($errors)) {
            $this->error('FAIL: Release is not ready to publish.');
            foreach ($errors as $error) {
                $this->line("  - {$error}");
            }
            return self::FAILURE;
        }

        $release->update(['status' => ModuleRelease::STATUS_PUBLISHED]);

        $this->info("SUCCESS: {$slug} v{$version} is now published and eligible for customer install/upgrade.");
        return self::SUCCESS;
    }
}
