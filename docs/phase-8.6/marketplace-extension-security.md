# Marketplace Extension Security — Phase 8.6

## Installation Flow

```
RuntimeMarketplacePolicy.installModule(descriptor, modulePath)
  1. MarketplaceGovernanceValidator.validate(descriptor)
     → check required capabilities exist in registry
     → check publisher is trusted
     → check platform version compatibility
  2. ExtensionAuthenticityService.authenticate()
     → hash verification
     → publisher trust check
  3. PluginLifecycleManager.register()
     → dependency scan
     → activate
```

## MarketplaceModuleDescriptor Fields

| Field | Description |
|---|---|
| module_id | Unique module identifier |
| name | Display name |
| version | Semantic version |
| publisher_id | Must be in TrustedPublisherRegistry |
| required_capabilities | Must all exist in ModuleCapabilityRegistry |
| optional_capabilities | Requested but not required |
| min_platform_version | Minimum supported platform version |
| integrity_hash | SHA-256 of all PHP files |

## ExtensionDistributionManifest

Distribution manifest used for download verification. Contains file list, capabilities, license type, and integrity hash. `MarketplaceGovernanceValidator.validateManifest()` checks hash consistency before installation.

## Security Guarantees

- ✅ No module installed without trust verification
- ✅ No module installed without dependency scan
- ✅ No App\\ imports accepted (BLOCKED classification)
- ✅ All installations emit telemetry
- ✅ Publisher must be in trusted registry for TRUSTED level
- ✅ Integrity hash verified against computed SHA-256
