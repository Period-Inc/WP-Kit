# Draft: Testing Context / Shared Database and Optional Isolation (WP-Kit)

- Status: **Draft / NOT accepted / NOT implemented**.
- Date: 2026-10-11.
- Purpose: preserve a reusable WordPress testing architecture while allowing an application to test against an **existing shared database** with explicit, inspectable test objects.
- This is an *extension proposal*, not a dependency or requirement for any current application rollout.

## Background and recorded decision

A consumer project originally prepared an isolated E2E WordPress installation, a separate database, a deploy target, access gating, Stripe test-mode webhook delivery, snapshots, and reversible fixtures. That work is preserved in its originating project as a **Draft prototype**, not discarded or merged into WP-Kit.

The current consumer need is smaller: use the existing QA WordPress/database without creating a database or changing the global WordPress environment. The shared-DB mode becomes the near-term design; dedicated-DB mode is a future, optional strategy.

**Do not merge or enable the isolated-installation prototype as a prerequisite to ordinary application E2E.** The application may adopt shared-DB testing without adopting WP-Kit.

## Responsibility boundary

| Layer | Owns | Explicitly does not own |
| --- | --- | --- |
| WP-Kit Core (potential) | Immutable `TestingContext`, `TestingMarker` and policy interfaces; no Fanika or deployment identifiers | Business queries, migrations, role permissions, Stripe, gate configuration |
| WP-Kit Application Plugin (future) | WordPress user/post/order/subscription meta adapters, admin "TESTING" badges, bounded fixture-run journal | Theme deployment or payment-provider internals |
| Site-specific application | Fixture graph, ownership authorization, domain-specific derived effects, data visibility | Generic deploy protocol |
| Deploy Kit / site operator | Theme Slot binding, deploy receipts, environment selection, operator confirmation | Inferring whether a DB row is test data |
| Authentication / payment providers | Login gate, Stripe webhook validation and test/live configuration | Treating a test label as proof of payment safety |

WP-Kit may supply small reusable primitives, but MUST NOT create a global `testing` toggle that bypasses authorization, subscription rights, payment validations, or business audit trails. A testing context and a theme slot are **distinct dimensions**.

## Shared-DB contract (immediate consumer pattern)

### Markers on every intentionally created test object

Suggested logical names; application-specific meta keys are mapped by an adapter:

- `testing = true` (storage example: `fanika_testing = '1'`)
- `test_run_id` (storage example: `fanika_test_run_id = 'OF089-20261011-...'`)
- `test_owner` / `test_case` as optional display/audit metadata (never secrets)
- Existing legacy fixture marker, e.g. `fanika_stg_seed`, stays readable for backward compatibility and does **not** itself authorize deletion.

On `WP_User` use usermeta; on ordinary posts/products use postmeta; on WooCommerce orders/subscriptions use **WooCommerce CRUD metadata** so HPOS and legacy order storage are both supported. No new tables or columns in MVP.

**Existing records with no testing meta are normal records**; absence, an empty value, malformed data, or unknown marker must not retroactively classify them as test records. Previous users/orders/relationships keep their normal behavior. Never bulk-backfill without a separate migration approval.

Testing metadata is an **ownership label**, not an authorization bypass or a filter applied indiscriminately to public/financial reads. Queries that intentionally select a test cohort use explicit `run_id` and verified owner identity.

### Per-run execution flag (distinct from persisted record marker)

A destructive fixture operation requires all of:

1. An **explicit opt-in in that CLI process**, e.g. `FANIKA_TESTING_MODE=1` (consumer-specific spelling; no default).
2. A site/environment allowlist and an explicit run identifier; no production site.
3. Operator approval and a read-only `plan`; no `apply` by default.
4. Preflight checks: intended hostname, PHP, expected shared DB identity, live/test payment-key separation, namespace/code collisions, and background runner effects.
5. A run journal identifying exactly which IDs were created, their marker, and the effect of each write.

Do NOT persistently change `WP_ENVIRONMENT_TYPE`, `siteurl`, `home`, gateway credentials, or global subscription/payment settings to manufacture a "staging" context. The generic framework must not treat a `testing` flag as permission to perform real payments.

### UX and evidence

- Admin users: a clearly visible **TESTING** badge on test users, products, orders/subscriptions, and test-run records, including the run ID on detail screens. Visual treatment is presentation only and must not alter canonical role, status or fulfillment logic.
- Test operators: a prominent testing-mode banner in the chosen theme review slot and report headers; keep theme Slot identity separate from test data identity.
- Reports, CSV, logs and screenshots: include `testing` and `test_run_id` columns/fields explicitly; aggregate production metrics should not silently incorporate or omit tests. Any decision to exclude test data from a business metric is a site-domain policy, covered by tests.
- Do not display raw tokens, webhook secrets, email bodies or identity documents in badges/logs.

## Safe lifecycle (shared DB)

`plan -> approve -> apply -> verify -> teardown -> inspect residue`; each phase creates audit evidence.

- `apply` is idempotent/fail-closed: journal or marked data already present for the run => refuse; do not create duplicated orders/subscriptions.
- Creation writes the marker **before or with** the first persistent save where possible and immediately journals the resulting ID. If a vendor object is saved before it can be marked, an interrupted creation remains **HOLD**, not eligible for heuristic deletion.
- `teardown` deletes only an item simultaneously matching (a) the testing marker, (b) the unique run ID, (c) the declared fixture identity, and (d) the journal. IDs/logins alone never prove ownership.
- If any item cannot be verified/deleted, preserve the journal as `teardown_incomplete` and refuse further `apply` until manual review.
- Review async jobs, derived tables, coupons, ledger/profit rows, uploaded files, emails, notifications and external provider effects separately. **Deletion of primary objects is not a rollback of side effects.**
- Never import/restore a full shared-DB snapshot as a normal fixture cleanup: it would overwrite other testers' intervening work. A full restore requires an exclusive maintenance window and owner approval.
- Unknown derived effects or payment side effects => HOLD or move that scenario to an isolated environment.

### Testing limits

Theme review slots may share one database, plugin state, WPF configuration, hooks, Action Scheduler and roles. Tags distinguish records but cannot make global mutations independent. In particular, card or bank/konbini payments, renewals, permission elevation, plugin schema migrations and asynchronous provider callbacks require additional provider-specific controls and may remain out of scope for shared-DB automation. A green unit test is not real Stripe/WCS E2E acceptance.

## Backward Compatibility Basis (schema changes only if unavoidable)

1. **Prefer no schema changes**: existing metadata and WooCommerce CRUD.
2. When schema extension is proven necessary, first document affected readers/writers/joins and data ownership; obtain application-owner approval.
3. Add only optional, nullable/default-safe attributes or additive tables. Old code must continue operating on old and new data. **Absence means ordinary/non-test**, without converting old rows.
4. Phase: optional schema addition -> dual-read/dual-write where justified -> backfill **only explicit opt-in fixtures** with an audited plan -> compatibility tests -> gated cutover. Never silently redefine existing keys or drop existing columns.
5. Preserve DB and plugin version interop during rolling deployments; provide explicit rollback and data-reconciliation plan.
6. Test old-app/new-schema and new-app/old-schema combinations where applicable, WooCommerce HPOS and legacy order storage, and without the testing feature enabled.
7. No migration or data edit may be auto-executed by the library.

## Future extension: isolated-installation strategy

The earlier separate-DB prototype is intentionally **deferred**, not rejected. It contains reusable ideas for a dedicated WordPress, per-site credentials, provisioning lockdown, WP Rampart gate, webhook exposure test, snapshot and restore.

Before porting any of that code:
- split generic lifecycle and policy from consumer-specific E2E scripts;
- keep credentials, database names, provider routes and domain fixtures in the consumer;
- use WP-Kit Application Plugin for a concrete WordPress UI or runtime responsibility rather than loading it unconditionally into WP-Kit Core;
- verify the selected infrastructure host and PHP/REST nonce contracts through actual integration tests;
- do not transplant hardcoded domain names, role names, payment options or deployment paths.

## Acceptance gates for a future implementation

- No behavior change when the feature is disabled and no `testing` meta exists.
- Shared-DB test fixtures can be created/read/reviewed/deleted without mutating any unmarked record.
- Every test record and run is visibly labeled, traceable, and reversible to the extent stated; ambiguous cleanup leaves HOLD and the journal.
- The same fixture can be run twice without duplication; partial failure does not delete an unrelated user/order.
- Theme Slot and testing data metadata can be configured independently.
- Real WordPress, WooCommerce Subscriptions, HPOS, async scheduling and provider integration tests pass where those operations are claimed to be supported.
- Dedicated-DB mode, schema migration, payment execution and deployment remain opt-in separate work items.

## Decision history

- **2026-10-11:** a dedicated-DB E2E prototype was prepared for a consumer QA effort, but the Owner chose **shared database for the current delivery**. The isolated installation is preserved as a future WP-Kit extension *Draft*. This document records the general design; it does not approve any destructive operation, schema migration, site creation or production change.
