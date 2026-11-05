# LGL institute management

Laravel 12 / PHP 8.2+ institute management using Eloquent, Blade, Tailwind 4 with Bootstrap compatibility, and MySQL-compatible storage. Exactly three roles: Admin, Teacher and Student. Ten functional groups are implemented locally; production deployment is owner-managed.

## Local use

The owner confirmed on 5 November 2026 that the currently configured local `lgl_system` is the working database. Use additive migrations; do not reset it or overwrite preserved databases/backups to resume work.

```powershell
npm ci
npm run build
php artisan serve --host=127.0.0.1 --port=8791
php artisan test
```

Use an existing preview at http://127.0.0.1:8791 if already running. Local demo accounts only:

- Admin: `admin@admin.com` / `password`.
- Teachers: `teacher@lgl.com`, `teacher1@lgl.com` / `password`.
- Students: `student1@lgl.com` through `student20@lgl.com` / `password`.

These match the current seeder and checked local demo accounts. Editing the seeder alone does not update existing accounts.

The three role portals and login use a German flag theme: black navigation, red primary actions and gold accents on white surfaces. The dashboard and login load compiled Tailwind/Vite assets. Node 22 is used locally; build assets before serving or packaging a release. Bootstrap remains for existing forms, tables and navigation behavior. Tailwind utilities use the `tw:` prefix without its reset to avoid conflicts.

Demo seeders refuse production. Never use these accounts for production provisioning.

## Documentation

- [Project requirements, architecture and operations](docs/PROJECT.md)
- [Task status, verification and recovery evidence](docs/TASKS.md)
- [Shared agent rules and collaboration workflow](AGENTS.md)
- [Claude Code entry point](CLAUDE.md)

These five Markdown files are the current documentation. Deferred modules remain outside the active product. Historical API examples were removed because API routing is unregistered and Sanctum is not installed.

The login page fills the desktop viewport edge to edge and displays the institute logo uploaded in Settings, with an LGL fallback before upload. Its default branding is Land of German Language; Settings can override the institute name.

Admin can sell a multi-level package (2–4 consecutive levels, e.g. A1 + A2, A1 to B1, A1 to B2) from Enrollments > Multi-level package. Admin enters the agreed price per sale (package or student-specific discount), chooses full payment now or monthly installments per level, and the system reserves every seat and splits the price exactly across level ledgers. Normal module-wise admission remains available. Per-student teachers are paid monthly per batch (rate × students that month) from Teacher Payroll > Generate month.

Exam-based institute certificates are the next requested extension. Design is recorded under E01 in docs/TASKS.md; passing-rule clarification is pending and the legacy exam/certificate modules remain inactive.

Admin batch save now automatically creates regular dated classes from weekly days/times through the batch end date, starting today or the batch start date. Set an end date when supplying a weekly schedule. Teachers see assigned batch dates/timings and today’s direct attendance links; use Extra / makeup class only for additional classes. Existing dated classes and attendance are preserved. For an older batch, Admin can edit and save to fill missing future dates.

Class Sessions now separates teacher confirmation from student attendance. Open a class to record Class taken or Class missed with a reason, then use Schedule linked makeup on an original missed class. This week’s teaching shows missed classes as makeup needed, pending or covered; coverage requires the makeup’s explicit Class taken confirmation. The additive teaching-record migration was applied to the owner-authorized local `lgl_system` on 5 November 2026.

Teacher Dashboard class names and dates/times open class details; assigned weekly timing opens the batch-filtered dated class list. For online teaching, Admin can enter one recurring meeting link on the batch, which regular class generation copies into new sessions. Separate meeting URLs require individual session links; meeting creation is manual. Changing a batch link does not overwrite links already stored on existing sessions.
