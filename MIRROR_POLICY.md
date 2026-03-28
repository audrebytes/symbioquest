# Public Mirror Policy (symbioquest)

This repo includes a **public mirror** of selected files from `symbioquest.com` under:

- `public-mirror/symbioquest.com/public_html/`

The mirror is **allowlist-driven** to reduce credential and internal-surface leakage.

---

## 1) What the mirror is for

- Public transparency of selected web surface
- Machine-facing ingestion/discovery artifacts
- Reproducible snapshot of published public content

It is **not** a full deploy repo and must not include runtime internals.

---

## 2) Allowed content (high level)

- Public pages and templates:
  - `index.php`, `about/`, `contact/`, `docs/`, `commons/`
- Public ingestion/discovery files:
  - `api/v1/public_slurp.php`
  - `ai-discovery.json`, `llms.txt`, `robots.txt`, `sitemap.xml`
- Public corpus lane:
  - `interesting/` (including package manifests, indexes, and published assets)

---

## 3) Forbidden content (hard deny)

Never include these in the mirror:

- Runtime secrets/config/auth internals:
  - `config.php`, `auth.php`, `api/v1/auth.php`, `api/auth.php`
- Ops/admin/internal surfaces:
  - `ops/`, `backend/`, `private/`
- Local artifacts/backups/datastores:
  - `*.bak_*`, `*.db`, `*.sqlite`, `.env*`
- Any hardcoded key/token/credential material

---

## 4) Update workflow

1. Build/update mirror from allowlist only
2. Stage only mirror paths explicitly
3. Run mandatory secret-pattern scan before commit
4. Commit with clear scope
5. Run final diff scan before push

Reference artifacts generated during initial rollout:

- `E:/HG-host/reports/github_public_mirror_preflight_20260328.md`
- `E:/HG-host/reports/github_public_mirror_allowlist_manifest_20260328.txt`

---

## 5) Security scan patterns (minimum)

Use these checks before commit/push:

```bash
git diff --staged | grep -E "sk-let|at-let|sk-[a-zA-Z0-9]{20}|Bearer [a-zA-Z]|password\s*=|secret\s*=|api_key\s*=|private_key|token\s*="

git diff origin/main..HEAD | grep -E "sk-let|at-let|sk-[a-zA-Z0-9]{20}|Bearer [a-zA-Z]|password\s*=|secret\s*=|api_key\s*=|private_key|token\s*="
```

Expected false-positive example in docs:

- `Authorization: Bearer your_api_key_here`

Anything else suspicious: stop and review.
