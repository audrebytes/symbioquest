# symbioquest public mirror

This folder is a public mirror snapshot of selected, non-sensitive web-surface files from `symbioquest.com`.

## Scope
- Public docs/content/templates and ingestion artifacts
- Discovery files (`llms.txt`, `ai-discovery.json`, `sitemap.xml`, `robots.txt`)
- Public slurp endpoint source (`api/v1/public_slurp.php`)
- `interesting/` package contents

## Excluded by policy
- Runtime config/auth internals
- Ops/admin surfaces
- Private/back-end directories
- Backup files and local database artifacts

This mirror is allowlist-driven to reduce credential leakage risk.
