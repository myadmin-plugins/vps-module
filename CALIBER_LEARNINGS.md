# Caliber Learnings

Accumulated patterns and anti-patterns from development sessions.
Auto-managed by [caliber](https://github.com/caliber-ai-org/ai-setup) — do not edit manually.

- **[gotcha:project]** This package's working directory during bash operations is `/home/sites/mystage/vendor/detain/myadmin-vps-module`, NOT the parent MyAdmin app at `/home/sites/mystage/`. Relative paths like `docs/superpowers/plans/` or `include/` will not resolve. Always use absolute paths (e.g., `/home/sites/mystage/docs/`, `/home/sites/mystage/include/`) when referencing parent-app files from bash commands or file reads.
