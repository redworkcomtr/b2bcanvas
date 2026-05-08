# Implementation Notes

- The project is a Laravel 13 application with an embedded Vue 3 + TypeScript SPA.
- UI primitives are local shadcn-vue-style components under `resources/js/components/ui`.
- The backend includes real migrations, seed data, JSON endpoints, and service classes for CSV parsing and product mapping.
- The current authentication surface is scaffolded around Laravel users and tenant ownership. Full login/session screens can be hardened with Laravel Breeze/Sanctum in a later sprint without changing the Vue module structure.
- SQLite is configured for local development through Laravel's generated `.env`; PostgreSQL can be enabled by changing the DB variables and running the same migrations.
