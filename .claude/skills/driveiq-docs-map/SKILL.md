---
name: driveiq-docs-map
description: Map of the docs/ folder for DriveQ with purpose and staleness of each file. Use before reading or citing any doc, to pick the trustworthy source and avoid stale ones.
---

# docs/ map

Trust order: code > `docs/SYSTEM-FLOW.md` > `PROJECT-PLAN.md` section 2 > `ROADMAP.md` > everything else.

**Current:** PROJECT-PLAN.md (section 2 only; header and phase ticks lag), ROADMAP.md (2026-10-20, M11-M15 plan, shipped ledger), DriveQ-Backlog.xlsx (remaining + shipped ledger), DEPLOY.md (says Laravel 11, actually 13), DPDP-Compliance.md, I18N.md, PHASE-1-GEO-RANKING.md, SYSTEM-FLOW.md.

**Partly built specs:** Features/Geo-Aware.md (static India/MH/Pune rules), Features/School-Comparison-Engine.md (built), Features/PRD-Google-Business.md (claim/import only), Realtime-Notification-Message-Broker-Plan.docx (events/channels exist, Reverb off).

**Vision, not status:** requirements/PBAC.md ("5 roles"; real role values are 4), PRD-DSOS.md, Core-Business-ERd.md, ARCHITECTURE.md (table names aspirational), personas and LEARNER-SCENARIOS*.md (June drafts).

**Historical, do not trust for status:** BACKEND-ASSESSMENT.md, PHASE-A-IMPLEMENTATION.md, DriveIQ-Documentation-Implementation-Audit-Report.docx (Aug), DriveIQ-Backlog.xlsx (Aug).

**UI references:** DriveIQ-School-Admin-Dashboard-Design.pptx, DriveIQ-Platform-Admin-Dashboard.html.

Reading binary docs: `unzip -p file.docx word/document.xml` (strip tags), `unzip -p file.xlsx xl/sharedStrings.xml`, `unzip -p file.pptx ppt/slides/slideN.xml`; run Python with `-I`.
