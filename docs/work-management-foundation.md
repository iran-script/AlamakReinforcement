# Work Management foundation

## Scope and compatibility

Work Management is an additive module in the existing Laravel modular monolith. It introduces generic work orders for any asset type without changing or migrating `riser`, `operations`, `workflow_transfers`, their controllers, reports, or routes. Legacy operations remain the production maintenance record until a later, separately validated compatibility migration.

## Tables and relationships

- `work_order_types` stores organization-specific or global definitions, defaults, and JSONB configuration. PostgreSQL partial uniqueness guarantees one global key; organization/key is unique for owned definitions.
- `work_orders` belongs to a type and optionally an organization, requester, creator, assignee, and existing contract. It stores scheduling timestamps, JSONB properties, traceability fields, and optional PostGIS `Geometry` at SRID 4326.
- `work_order_assets` links a work order to any number of assets as `primary`, `affected`, or `related`. A work-order/asset pair can occur only once.

Codes are unique per organization when present, including a dedicated partial unique index for global records. Useful organization, state, type, assignee, scheduling, legacy-reference, and GiST geometry indexes are included.

## Initial state model

Statuses are `draft`, `open`, `assigned`, `in_progress`, `on_hold`, `completed`, and `cancelled`. Priorities are `low`, `normal`, `high`, and `critical`. The PHP enums are the single source of truth. Transitions are explicit, completed/cancelled are terminal, assignment requires an appropriate active state, and completion records `completed_at`.

This static transition model is temporary. Workflow Builder will later provide versioned, publishable state definitions and replace these hard-coded transitions without changing the work-order identity or history.

## API

All endpoints require Sanctum authentication and live under `/api/platform`:

- `GET /work-order-types`
- `GET|POST /work-orders`
- `GET|PATCH /work-orders/{workOrder}`
- `POST /work-orders/{workOrder}/assign`
- `POST /work-orders/{workOrder}/status`
- `POST /work-orders/{workOrder}/assets`
- `DELETE /work-orders/{workOrder}/assets/{asset}`

The list is paginated and supports `status`, `priority`, `type`, `assigned_to`, `date_from`, `date_to`, and `asset_id` filters.

## Authorization and audit

`WorkOrderPolicy` delegates action decisions to `AccessPolicyService` using resource key `work_orders`. Supported actions are `view`, `create`, `update`, `assign`, `change_status`, and `attach_asset`. Non-superadmins require an explicit allow policy, cannot access another organization's work order, and cannot access global work orders. Superadmin bypass remains controlled by the existing role/Gate mechanism.

Asset attachment, detachment, asset filtering, and returned asset details require visibility through `AccessPolicyService::visibleAssets()`, including spatial-scope intersection. Organization-owned assets must match the work order; global legacy assets may be linked only when spatially visible.

Audit events are `work_order.created`, `work_order.updated`, `work_order.assigned`, `work_order.status_changed`, `work_order.completed`, `work_order.asset_attached`, and `work_order.asset_detached`. They include the authenticated actor through `AuditLogger`; no audit internals are returned by this API.

## Future legacy migration

A later milestone may map historical `operations` to work orders using explicit `legacy_source` and `legacy_id`, reconciliation reports, dry-run counts, and repeat-safe identity rules. It must retain `operations.riser_id` and all legacy routes until parity and rollback validation are complete. No historical operation migration occurs in this foundation milestone.
