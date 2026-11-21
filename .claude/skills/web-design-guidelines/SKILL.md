---
name: web-design-guidelines
description: Review UI code for Web Interface Guidelines compliance. Use when asked to "review my UI", "check accessibility", "audit design", "review UX", or "check my site against best practices".
metadata:
  author: vercel
  version: "1.0.0"
  source: https://github.com/vercel-labs/agent-skills (skill) + https://github.com/vercel-labs/web-interface-guidelines (rules, MIT)
  argument-hint: <file-or-pattern>
---

# Web Interface Guidelines

Review files for compliance with Web Interface Guidelines.

## How It Works

1. Read the rules in `regras.md` (local copy, next to this file). If you have network access, also fetch the latest
   version from the source URL below and prefer it when it differs.
2. Read the specified files (or prompt user for files/pattern).
3. Check against all rules.
4. Output findings in the terse `file:line` format.

## Guidelines Source

```
https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md
```

## In this project (GDS)

- Public site components: `gds/includes/site_fragmentos.php`, `gds/includes/site_template.php`,
  `gds/assets/css/gds-componentes.css`, `gds/assets/js/gds-site.js`.
- Company panel: `gds/empresa/*.php`, `gds/assets/css/style.css`, `gds/assets/js/gds-painel-editores.js`.
- Panel rule: no setting may require typing a syntax — use the button editors (see LEIA-ME, "Tudo com botões").
- Automated check: `/tmp/gdstest/auditoria_axe.js` style runs (axe-core via Playwright) complement the manual review.
