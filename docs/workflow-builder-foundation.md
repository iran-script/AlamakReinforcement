# Workflow Builder foundation

## Architecture and lifecycle

Workflow Builder is a backend-first module inside the Laravel modular monolith. A `workflow_definition` is the stable organization-owned identity and subject contract. A `workflow_version` is a numbered graph snapshot. States and transitions belong exclusively to one version. A `workflow_instance` runs one published version for one subject, while `workflow_instance_transitions` is its append-only execution history and `workflow_tasks` provides user/role inbox work.

Definitions may be global templates (`organization_id = NULL`) or organization-owned. PostgreSQL partial indexes make global keys unique. Versions move from `draft` to `published` to `archived`. Published graphs cannot be edited through services or normal APIs; changes require cloning to the next draft version. Existing instances continue referencing their original immutable version.

## Tables

- `workflow_definitions`: stable key, name, subject type, ownership and activation.
- `workflow_versions`: numbered draft/published/archived snapshots and publication identity.
- `workflow_states`: initial, intermediate and terminal graph nodes.
- `workflow_transitions`: graph edges, safe conditions, assignments, permission and future form requirements.
- `workflow_instances`: organization-aware execution state for a polymorphic subject reference.
- `workflow_instance_transitions`: immutable performed-transition history. Eloquent guards and a PostgreSQL trigger reject updates/deletes.
- `workflow_tasks`: open/completed/cancelled user or role assignments, due dates and metadata.

Composite foreign keys ensure transition endpoints and instance current states belong to the declared workflow version. A partial unique index prevents duplicate active instances for one subject/version.

## Publish validation

Only drafts can publish. Validation requires exactly one initial state, at least one terminal state, valid same-version transition references, valid condition and assignment definitions, and a reachable path from the initial state to a terminal state. Graphs trapped in cycles without a terminal path are rejected. Assignment definitions are declarative JSON and are validated before publication as well as at execution.

## Restricted condition DSL

Conditions are JSON data, never executable code. Supported boolean groups are `all`, `any`, and `not`. Leaf format:

```json
{"field":"work_order.priority","operator":"in","value":["high","critical"]}
```

Operators are `eq`, `neq`, `in`, `not_in`, `truthy`, `falsy`, `gt`, `gte`, `lt`, `lte`, `exists`, and `not_exists`. Numeric operators require numeric operands. Allowlisted fields are:

- `work_order.priority`, `work_order.status`, and one property key under `work_order.properties`
- one property key under `subject.properties`
- `actor.role_ids`
- one explicit input key under `input`

Keys must be simple identifiers, paths are limited to three segments, and condition nesting is capped. PHP, JavaScript, SQL, callbacks, `eval`, dynamic traversal, and arbitrary model access are unsupported and rejected.

## Runtime execution

Starting requires a published compatible version and explicit API action. A transition locks the instance row, verifies active/current state and version, checks organization, policy, required permission, work-order access and linked-asset spatial visibility, evaluates the DSL, appends history, cancels stale open tasks, creates any declarative assignment task, advances state, and completes the instance on a terminal state. All mutations use transactions and audit logging.

`required_permission` uses `resource_key:action` and is evaluated by `AccessPolicyService`. `required_form_key` is a safe Form Builder placeholder: until Form Builder exists, such a transition is unavailable and execution is rejected.

Assignment JSON currently accepts `assigned_to`, `assigned_role_id`, `title`, `due_hours`, and `metadata`. Tasks support personal and role inboxes, future SLA timers, escalation and approvals.

## Access and audit

Policies use `workflow_definitions` (`view`, `configure`, `publish`), `workflow_instances` (`view`, `start`, `transition`) and `workflow_tasks` (`view`, `transition`). Non-superadmins cannot read or mutate another organization's records. Global definitions are deliberately readable as reusable templates when `view` is granted, but only superadmins may configure global definitions. The existing controlled superadmin Gate bypass remains; service-level graph immutability still applies.

Audit events cover definition/version/state/transition creation and updates, publish/archive/clone, instance start/transition/completion/cancellation, and task creation/completion.

## Work Order coexistence

Work orders have nullable definition, version and instance references. Starting is opt-in through `POST /api/platform/workflows/instances`; existing work orders remain untouched. Work orders without a workflow continue using the static `WorkOrderStatus` transition service. Workflow state never changes static work-order status unless a future published integration configuration explicitly maps and validates that behavior. No `operations` or legacy `workflow_transfers` data or route is altered.

## Future architecture

Form Builder will resolve `required_form_key` to a published form version and persist validated submissions. A visual builder can edit draft state/transition JSON through the existing APIs, use stable node keys and sort/layout configuration, run server-side validation, and publish immutable versions. Later milestones can add SLA calendars, notifications, escalation policies, delegation, timers, event-driven transitions, and import/export mappings inspired by BPMN/DMN without executing arbitrary BPMN scripts or DMN expressions.
