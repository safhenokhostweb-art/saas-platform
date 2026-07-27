# Security Rules

- Treat font, audio, SVG, logo, prompt, and AI output as hostile input.
- Never expose Master source, editable schema, AI metadata, internal paths, credentials, tenant data, or unrelated assets.
- Sanitize and bound uploads; isolate future rendering/conversion with network disabled.
- Master Assets are immutable; plans reference stable IDs only.
- No automatic approval/publication and no provider authority.
- Record safe checksums/correlation metadata, not raw private content or hidden reasoning.
- Enforce future tenant scope, license, rate, cost, retention, and audit controls server-side.
