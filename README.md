# LGL institute management

Laravel 12 / PHP 8.2+ institute management using Eloquent, Blade, Tailwind 4 with Bootstrap compatibility, and MySQL-compatible storage. Exactly three roles: Admin, Teacher and Student. Ten functional groups are implemented locally; production deployment is owner-managed.

## Local use

The configured clean demo is separate from the preserved original `lgl_system` database. Do not reset either database to resume work.

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

The dashboard and login load compiled Tailwind/Vite assets. Node 22 is used locally; build assets before serving or packaging a release. Bootstrap remains for existing forms, tables and navigation behavior. Tailwind utilities use the `tw:` prefix without its reset to avoid conflicts.

Demo seeders refuse production. Never use these accounts for production provisioning.

## Documentation

- [Project requirements, architecture and operations](docs/PROJECT.md)
- [Task status, verification and recovery evidence](docs/TASKS.md)
- [Shared agent rules and collaboration workflow](AGENTS.md)
- [Claude Code entry point](CLAUDE.md)

These five Markdown files are the current documentation. Deferred modules remain outside the active product. Historical API examples were removed because API routing is unregistered and Sanctum is not installed.

The login page fills the desktop viewport edge to edge and displays the institute logo uploaded in Settings, with an LGL fallback before upload. Its default branding is Land of German Language; Settings can override the institute name.

Admin can sell the Rs 50,000 A1 + A2 full-advance package from Enrollments. Select both sequential batches; the system reserves both seats, splits fees exactly and issues a combined receipt. Normal module-wise admission and installments remain available. Package sales require PKR currency.

Exam-based institute certificates are the next requested extension. Design is recorded under E01 in docs/TASKS.md; passing-rule clarification is pending and the legacy exam/certificate modules remain inactive.
