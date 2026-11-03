# Module cleanup recommendations

Local implementation applied the kept/merged core and retired deferred product entry points after owner authorization. Legacy service/template/table files and original data remain retained. “Retire” does not mean physical deletion; production usage and any later file/data removal still require review. Original task IDs refer to `archive/TASKS_ORIGINAL.md`; current results are in TASKS.md/ACCEPTANCE.md.

| Existing/proposed area | Decision | Reuse and change |
|---|---|---|
| M00 foundation, M16 security | Keep and simplify | Keep Laravel, session login, role middleware, policies and layouts; three roles, explicit query scoping, targeted audit records. No permission package or branch-role hierarchy. |
| Accountant role/dashboard | Retired from active demo/product | Admin owns finance; active enum, seeders, helpers, policies, routes and navigation support three roles. The original unsupported account remains unchanged in the preserved legacy DB, never promoted. |
| M02 students, M07 teacher profiles | Keep; merge under People navigation | Reuse `User` and existing student/teacher screens. No separate employee or HR subsystem. |
| M04 courses/batches | Keep | Reuse models, CRUD and teacher FK; repair missing templates/actions as needed. One teacher per batch; no pivot table yet. |
| M03 admissions/enrollment | Keep and consolidate | Route student enrollment and enrollment controller writes through `EnrollmentService`; align actions and add transaction/capacity protection. |
| M05 fees/installments | Keep and consolidate | Reuse enrollment agreement and schedule concepts, views and useful calculations. One authoritative financial write path; add payments/allocations rather than rewrite all CRUD. |
| M06 sessions/attendance | Keep and merge duplicate paths | Keep `class_sessions`, `attendances`, their uniqueness constraint and roster UI. `AttendanceService` owns marking; session controller delegates instead of duplicating it. |
| Hikvision/fingerprint, auto-absence | Defer; retire active routes/schedule after approval | Keep legacy service/columns untouched initially. Unlink automatic calls and scheduler before later dependency-reviewed cleanup. Manual marking must work without devices. |
| M01, M12, M13 dashboards/portals | Keep narrow role views | Reuse existing web controllers/templates. Consolidate overlapping `DashboardController` calculations only when needed; no parallel API dashboard. |
| M15 reports | Merge into core modules | Keep reports landing page and fee/attendance filters. Repair schema references and missing views for four core reports only; enforce Admin/global versus Teacher/assigned scope. |
| M07 payroll, teacher leaves, dual earnings | Defer | Preserve `TeacherEarning`, `BatchTeacherEarning`, `TeacherPayment`, `TeacherLeave`, salary service and tables; no calculation or payment UI in baseline. Later select one payout model and reconcile history before migration. |
| M08 expenses | Defer | No dedicated module found. Do not create expense tables or income-versus-expense claims. |
| M09 exams/results, M10 certificates/letters | Defer | Preserve existing models/migrations; placeholder PDF helpers are not a delivery commitment. No new controllers/templates now. |
| M11 CRM, M14 tasks/notices | Defer | No dedicated implementation found; do not build speculative scaffolding. |
| M18 Zoom | Replace integration requirement with manual links | Reuse batch/session meeting fields. No credentials, OAuth, background reconciliation or meeting provisioning. |
| M19 WhatsApp, fee reminders/notifications | Defer | Preserve notification classes and reminder command. Do not schedule provider sends or add integrations. |
| M17 Excel import | Defer | Preserve existing data; backup/reconciliation is core, general import tooling is not. Reassess only if actual source files are supplied. |
| M20 UX, M21 deployment | Keep essentials | Existing responsive Bootstrap UI, useful validation, accessible forms, backup/restore and staging checks; no redesign or fixed hosting migration. |
| Dormant API/Sanctum examples | Retire as baseline contract | Do not enable API routing or install Sanctum merely to satisfy stale examples. Preserve files until a separate dependency check authorizes cleanup. |
| Branch model/scoping | Defer pending explicit decision | No current schema exists. Baseline Admin is organization-wide; labels can be added later if needed. If branch isolation is mandatory, revise baseline before implementation. |

## Later cleanup sequence

1. Obtain baseline approval, production usage inventory and backup evidence.
2. Replace or safely disable entry points: routes, links, scheduled commands and any automatic callers. Search references across code, views, tests and docs.
3. Prove retained workflows work without the retired paths and that history remains readable.
4. Submit a separate cleanup diff listing files and data implications. Physical table/column deletion requires explicit approval and restore evidence; it is not part of this roadmap.

No framework upgrade, SPA rewrite, repository layer, microservice split, new queue system or blanket dependency removal is proposed.
