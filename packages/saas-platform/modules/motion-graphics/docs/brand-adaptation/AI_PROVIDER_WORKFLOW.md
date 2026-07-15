# AI Provider Workflow

Status: **Architecture only.**

## Provider-neutral boundary

No provider SDK or model-specific response shape belongs in the domain workflow. A future adapter must normalize every provider to the same request/proposal contract.

## Admin workflow

1. Platform Admin selects one customer/site.
2. Platform Admin selects approved global assets across icons, objects, illustrations, and motion.
3. Platform Admin uploads an authorized customer logo.
4. The platform sanitizes, validates, scans, fingerprints, and stores the logo privately.
5. The palette analyzer prepares candidate brand tokens.
6. Platform Admin reviews or adjusts the palette proposal.
7. The platform creates a bounded provider request containing only required assets, editable schemas, normalized metadata, and the sanitized logo representation.
8. The provider returns a structured proposal, never a published artifact.
9. The platform validates structure, schema, accessibility, security, rendering, size, and theme tokens.
10. Valid proposals become customer-specific draft versions.
11. Platform Admin compares and approves or rejects each draft.
12. Approved drafts are compiled, optimized, packaged, and published through the authorized workflow.

## Minimum request envelope

```json
{
  "request_id": "opaque-id",
  "customer_scope": "private opaque reference",
  "source_assets": [
    {
      "source_asset_id": "id",
      "source_asset_version": "version",
      "asset_class": "icon|object|illustration|motion",
      "sanitized_source": {},
      "editable_schema": {}
    }
  ],
  "sanitized_logo": {},
  "approved_palette": {
    "brand_primary": "",
    "brand_secondary": "",
    "brand_accent": "",
    "brand_background": "",
    "brand_border": ""
  },
  "constraints": {
    "preserve_structure": true,
    "preserve_accessibility": true,
    "external_dependencies": false
  }
}
```

Use opaque customer references when the provider does not require business identity.

## Response contract

The provider returns per-asset proposed source, a normalized change list, confidence/warnings, and no publication decision. Extra properties are rejected or ignored according to a strict schema.

## Security and privacy

- validate MIME type, file signature, dimensions, decompression limits, and SVG safety;
- strip metadata and active content;
- keep original logos and previews private;
- use least-data provider requests;
- configure provider retention and training opt-out where available;
- never include credentials, internal paths, tenant secrets, or unrelated assets;
- verify response size and parse safely;
- log identifiers and checksums instead of sensitive payloads; and
- apply timeouts, retries, rate limits, and circuit breaking in a future adapter.

## Approval boundary

AI may analyze, propose, and regenerate. It cannot approve, publish, select a customer, widen editable fields, or change ownership.

