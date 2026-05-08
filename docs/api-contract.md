# B2B Canvas API Contract

This Laravel application serves a Vue SPA and same-origin JSON API.

## Portal Bootstrap

- `GET /api/portal`
- Returns tenant, current user, metrics, orders, product types, product mappings, issues, required actions, and notification subscriptions.

## Orders

- `GET /api/orders`
- `GET /api/orders/{uuid}`
- `POST /api/orders`
- `POST /api/orders/imports/preview`

Order statuses: `draft`, `validation_failed`, `action_needed`, `verified`, `submitted`, `in_production`, `shipped`, `closed`, `cancelled`.

## Product Mappings

- `POST /api/product-mappings`

Rule fields: `sku`, `name`, `fulfillment_sku`.

Rule operators: `equals`, `contains`, `starts_with`, `regex`.

## Issues

- `POST /api/issues/ticket`
- `POST /api/issues/claim`

Issue statuses: `open`, `in_progress`, `waiting_customer`, `resolved`, `closed`.

## Notifications

- `PATCH /api/notifications/subscriptions/{subscription}`

Events: `ORDER_SHIPPED`, `ORDER_ACTION_NEEDED`, `ORDER_ISSUE_COMMENT_ADDED`, `ORDER_VALIDATION_FAILED`.
