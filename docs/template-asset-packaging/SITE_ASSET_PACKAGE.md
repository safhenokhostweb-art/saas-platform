# Site Asset Package

The future resolver produces a private, immutable runtime snapshot for one site:

```text
site-assets/{site-id}/{package-id}/v3/
├── manifest.json
├── integrity.json
├── theme.css
├── motion/
├── icons/
├── objects/
└── private/
```

`manifest.json` contains opaque package/site scope, template ID/version/checksum, package version, resolved published asset IDs/versions/checksums, runtime-safe paths, rendering mode, compatibility profile, license snapshot, lineage IDs, and activation timestamp. It excludes Master sources, editable schemas, AI metadata, prompts, provider payloads, raw logos, private approval evidence, and other sites' identifiers.

## Export/import contract

Export includes the canonical manifest, dependency lock, compatibility declaration, integrity/checksum list, approved compiled artifacts, and safe theme tokens. Import validates schema version, canonical paths, checksums/signature, site audience, ownership, license, compatibility, required dependencies, file MIME/size/safety, and package monotonic version before staging.

Import never activates directly. The future platform stages, validates, atomically changes an activation pointer, audits the decision, and retains the previous package for rollback. This phase defines no deployment implementation.

