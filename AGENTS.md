# B2B Canvas Codex Rules

## Test Policy

- Do not add PHPUnit, Vitest, Playwright, E2E, smoke, unit, feature, or integration tests.
- Do not reintroduce test frameworks, test config files, or `tests/` folders.
- The only automated verification is PHP syntax checking.
- Use `composer syntax` for syntax verification.
- Frontend `npm run build` is allowed only as a production asset build check, not as a test strategy.

## Production Workflow

- Write changes for the live/staging server path, not for simulated-only flows.
- Verify functional behavior on the real staging/live surface when credentials and target server are available.
- Do not commit `.env`, secrets, logs, uploads, caches, `vendor`, `node_modules`, or generated build artifacts.
