# Form Builder Foundation

## Domain and storage model

`form_definitions` is the stable organization-owned (or deliberately global) identity. `form_versions` contains draft, published, and archived revisions. `form_fields` is the ordered normalized field catalog used to validate and render a version. `schema`, `ui_schema`, and `configuration` are bounded JSONB extension points for a future designer; they are not executable definitions.

`form_submissions` pins data to one exact published version and optionally to a subject and workflow instance/transition. Drafts may change; submitted, superseded, and cancelled rows are immutable in both the service and a PostgreSQL trigger. `form_submission_files` stores attachment metadata while private storage paths are hidden from API serialization.

## Lifecycle

A definition may have multiple drafts, but version numbers are serialized under a row lock. Only a valid draft with at least one active field can be published. Published versions and fields cannot be edited through normal APIs. A published version is cloned to a new numbered draft for changes. Only published versions can be archived. Every definition/version/field lifecycle mutation is audited.

## Field catalog and validation

Supported types are: `text`, `textarea`, `number`, `decimal`, `boolean`, `date`, `datetime`, `select`, `multi_select`, `radio`, `checkbox_group`, `user`, `asset`, `organization_unit`, `zone`, `geopoint`, `geometry`, `image`, `file`, `signature`, and `repeatable_group`.

Configuration never accepts Laravel rule strings. The allowlist is `min`, `max`, `min_length`, `max_length`, controlled `pattern`, `date_min`, `date_max`, `max_items`, `unique`, `mime_types`, and `max_size_kb`. Choice options are objects containing only `value`, `label`, and optional `disabled`, capped at 200. Repeatable schemas are capped at 50 nested fields, depth is bounded by condition/schema validation, and item count is capped at 100. Submission JSON is capped at 512 KiB and 500 top-level keys.

Conditions reuse `WorkflowConditionEvaluator`: `all`, `any`, `not`, and leaf `{field, operator, value}` with operators `eq`, `neq`, `in`, `not_in`, `truthy`, `falsy`, `gt`, `gte`, `lt`, `lte`, `exists`, and `not_exists`. Form fields use the safe `input.<field_key>` root. No PHP, JavaScript, SQL, `eval`, or arbitrary expression is evaluated.

User and asset references are checked against organization and asset spatial visibility. Zone references must exist. GIS values are bounded GeoJSON geometry objects without a client CRS member; the accepted coordinate reference is WGS84/SRID 4326. Raw WKT/EWKT and SQL geometry expressions are rejected.

## Field access

`access_definition` supports `default`, `roles`, and `users`, with states `edit`, `readonly`, or `hidden`. `DataAccessPolicy.field_rules` for resource `form_submissions` is then applied. The most restrictive result wins (`hidden` over `readonly` over `edit`). Hidden fields are omitted from resolution/read payloads and rejected on writes. Readonly values cannot be changed. This makes form-local rules a rendering constraint and policy rules the centrally administered upper bound.

Linked work-order/asset/spatial authorization remains enforced by the existing access services. Organization policy checks apply to all definitions, versions, submissions, and files. Global definitions are read-only to ordinary organizations and only visible where `form_definitions:view` is deliberately granted.

## Attachments

Only `file`, `image`, and `signature` fields accept uploads, only while the submission is a draft and the field is editable. MIME types and sizes use an allowlist, with a hard 20 MiB ceiling. Files are stored on a non-public disk under `organizations/{organization}/forms/{submission}` using generated storage names; original names are metadata only. API responses never expose disk or private path. Delete is limited to authorized draft files. Legacy operation image/photo storage is untouched.

## Workflow integration and resolution

The selected strategy is direct resolution by `required_form_key`. For the workflow instance organization, resolution first selects an active organization-specific definition and otherwise an active global definition, then selects its latest published version. A submission pins that exact version. This is unambiguous because database indexes enforce one organization/key pair and one global key.

A required transition is available/executable only when a `submitted` submission uses the currently resolved published version and matches organization, workflow instance, transition, subject type, and subject id. Archived/draft versions and submissions from another context never satisfy it. A missing form blocks only transitions that declare `required_form_key`; transitions without it and static work orders behave exactly as before.

Future asset-type/work-order-type binding can be added as a precedence layer ahead of direct `resource_key` resolution without changing submission pinning.

## API

All endpoints are Sanctum-protected under `/api/platform`: definition list/create/show/update; version create/show/update/publish/clone/archive; field create/update/reorder; submission list/create/show/draft-save/submit; file upload/delete; and `GET forms/resolve`. Resolution returns only published schema, UI schema, public definition metadata, and caller-visible fields. Admin-only version configuration and private file paths are not returned.

## Compatibility and future architecture

No legacy operation form/photo table, riser feature, `workflow_transfers`, API, or route was changed. This milestone adds backend primitives only and no visual-builder dependency.

An RTL visual designer can later edit drafts through these normalized endpoints, maintain a local component palette keyed by `field_type`, and preview from the same resolution payload used by clients. Offline clients should cache definition key/version/schema with an opaque local draft id, queue attachment hashes separately, submit with the pinned version, and use timestamps/idempotency tokens in a future sync endpoint. Submitted snapshots remain the conflict-free record.

For reporting, retain JSONB snapshots as the source of truth and add opt-in expression/GIN indexes or an asynchronous typed projection table per explicitly reportable field. Never generate SQL directly from form configuration; reporting fields must be allowlisted and indexed through migrations or a controlled index registry.
