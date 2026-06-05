# Implementation Notes

- The project is a Laravel 13 application with an embedded Vue 3 + TypeScript SPA.
- UI primitives are local shadcn-vue-style components under `resources/js/components/ui`.
- The backend includes real migrations, seed data, JSON endpoints, and service classes for CSV/XLSX parsing, import processing, product mapping, and required-action resolution.
- The current authentication surface is scaffolded around Laravel users and tenant ownership. Full login/session screens can be hardened with Laravel Breeze/Sanctum in a later sprint without changing the Vue module structure.
- Local development targets PostgreSQL 16. Automated test runners are intentionally out of the project path; use the Homebrew PostgreSQL service locally and managed PostgreSQL in production.
