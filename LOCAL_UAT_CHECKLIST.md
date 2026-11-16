# Local UAT checklist and entry review

Prepared: 2026-11-12. Status: **Not started; password-path blocker resolved, isolated base setup verified.**

This is an execution plan, not acceptance evidence. Latest completed automated verification: 215 PHP tests / 1270 assertions; four navigation tests passed in the preceding batch. Read-only local `deploy:check` passed all seven checks and migration status showed all 16 migrations applied. Production-mode checks were not run.

## Entry decision

The local database/storage passed the seven read-only preflight checks during entry review. The password-path gap identified there has now been fixed: [profile password changes](app/Http/Controllers/Auth/PasswordController.php), [password reset](app/Http/Controllers/Auth/NewPasswordController.php), and [activation](app/Http/Controllers/Auth/RegisteredUserController.php) use the shared [hash-input rule](app/Rules/PasswordHashInput.php), retaining existing confirmation/current-password/token checks.

Nine regression cases cover each path with oversized ASCII, oversized multibyte input and null bytes. Rejection leaves account attributes and tokens unchanged, and a valid 72-byte retry succeeds. Full automated suite: 215 tests / 1270 assertions passed, using isolated SQLite and no live mail/AI. No source accounts or credentials were changed.

**Isolated base setup verified:** see [local access and runtime limits](LOCAL_UAT_ACCESS.md). Separate database/storage/session/cache, nine synthetic accounts and local log-mail capture are ready. Controlled AI and SMTP outage/recovery browser scenarios remain blocked. Browser UAT has not started; no acceptance result is implied by the automated checks. Live-provider scenarios remain blocked until a controlled provider is available.

## Safe local setup

- Use a separate local SQLite database, storage, sessions and cache; never point UAT at the working database or its attachment directory.
- Verify effective paths and a visible local-only environment label before any create/update/delete scenario. Preserve the existing application key; do not run setup scripts or regenerate it against existing data.
- Provision synthetic admin, registrar, regular student, graduating student and alumni accounts. Include another unrelated student, an unused account, a staff account with recorded actions and a sole-admin fixture.
- Use synthetic PDFs/images only. Include active/completed/archived requests, priority ties, two tabs for stale edits, a custom course and customized category labels.
- Capture mail locally with a sandbox inbox. Array mail alone does not prove that a human can receive and follow an invitation/reset link. Keep live SMTP and live Gemini disabled.
- Use controlled provider responses for AI success/failure scenarios. If no mock provider is configured for browser use, mark those cases **Blocked**, not passed. Automated HTTP fakes are separate evidence.
- For testing outbox behavior, operate only on the isolated database with a local mail capture transport; do not invoke delivery against the working database.

Base setup is now performed and documented in [local access](LOCAL_UAT_ACCESS.md); controlled external-service scenarios still require setup. Do not run demo generators/cleanup on existing school records.

## Scenarios

Execution update (November 16, 2026): recorded developer-run results, fixes, remaining browser acceptance checks and hosting blockers are in [the dated UAT results](LOCAL_UAT_RESULTS_2026-11-16.md). The table below retains the original scenario definitions; its initial "Not run" cells are not the current execution ledger. Client sign-off remains outstanding.

Every row starts **Not run**. Record browser/device, actual result, evidence and defect ID when executing.

| ID | Role | Steps | Expected result | Status |
| --- | --- | --- | --- | --- |
| U01 | All | Log in/out as each role; attempt another role's URL. | Correct dashboard; unauthorized access denied; logout removes access. | Not run |
| U02 | Student | Follow captured activation link; retry it and try mismatched identity. | Only provisioned identity activates; expired/replayed/mismatched attempts rejected. | Not run |
| U03 | All | Change/reset password, then try invalid inputs and invalid/expired reset tokens. | Validation feedback, unchanged credentials on rejection, valid recovery works; no server error. Password-path fix has automated coverage; browser acceptance remains pending. | Not run |
| U04 | Admin/registrar | Create/edit an unused student with a custom course and category label; try privileged account IDs. | Data and displayed labels persist; staff/mixed-role targets denied. | Not run |
| U05 | Admin | Create/edit/delete an unused registrar; try student/admin/mixed-role IDs. | Registrar-only management works; other account types remain unchanged. | Not run |
| U06 | Student | Submit a valid request and proof; double-click/retry the same form. | One request, attachment and submission event; successful feedback and private proof access. | Not run |
| U07 | Student | Submit missing/invalid/oversized proofs, invalid counts and an overlong filename. | Field feedback; no partial request or profile change. Controlled storage failures remain developer tests. | Not run |
| U08 | Alumni/regular | Alumni submits graduation year; regular student requests an alumni-only document. | Alumni year persists; restricted document denied for regular student. | Not run |
| U09 | Two students | Try the other student's request and attachment URL; try public proof paths and guest access. | Foreign/guest access denied; no public proof download. | Not run |
| U10 | Staff/student | Staff changes status and remarks; student checks own view/history. | Saved state, event and in-app feedback agree; no unintended staff-only identity disclosure. | Not run |
| U11 | Two staff tabs | Open same request twice; save first tab, then save/archive from stale second tab. | Second action rejected with useful feedback; first result/history retained. | Not run |
| U12 | Staff | Archive/unarchive; inspect active list, counters and priority queue. | Visibility/counts match filters; completed/rejected/archived requests excluded from active queue. | Not run |
| U13 | Staff | Search literal wildcard characters; combine category/status/archive filters and export CSV. | Export matches filtered listing; formula-like text remains escaped. | Not run |
| U14 | Admin | Rename a supported category; try unsupported codes/core deletion and removal of assigned program. | Label changes consistently; invalid/removal actions rejected without changing referenced data. | Not run |
| U15 | Admin | Save valid document/category/urgency weights, then missing/unknown/out-of-range keys. | Valid settings save; invalid payload leaves settings intact. Existing request scores follow documented snapshot behavior. | Not run |
| U16 | Staff/student | Delete an unused account, a request owner and a recorded staff actor; attempt sole-admin self-delete. | Only unused account removed; referenced accounts/last admin retained; blocked self-service stays logged in. | Not run |
| U17 | Staff | Load dashboard with no/valid/expired forecast cache; use Generate with controlled provider responses. | GET makes no provider call; explicit POST generates; stale/missing messages accurate; both staff roles work. | Not run |
| U18 | Staff | Use malformed/failed provider response and concurrent/repeated generation. | Safe errors; old cache retained; lock/cooldown feedback; diagnostic reference matches safe log. | Not run |
| U19 | Staff | Save request status with local SMTP unavailable; restore local capture and process isolated outbox. | Business change remains saved; deferred email eventually appears in capture in order; retries do not duplicate history. | Not run |
| U20 | All | Navigate at desktop/mobile widths and by keyboard; test menus, forms, dialogs and validation. | No blocking overflow; visible focus; dialog/menu dismissal and labels usable; error feedback discoverable. | Not run |

## Defect record

For each defect capture: ID, scenario, role, environment, reproducible steps, expected/actual result, severity, screenshot/log reference without credentials, affected records, owner, fix reference and retest result. Keep credentials, reset tokens and payment proofs out of shared evidence.

Security violations, data loss, broken core workflows and inability to complete keyboard/mobile tasks are acceptance blockers. Cosmetic issues may be deferred explicitly with an owner; do not silently mark a failed case passed.

## Exit and deployment separation

Local UAT passes only when required cases have recorded results, blockers are fixed/retested, affected regressions pass, and the project owner accepts the results. Any blocked scenario remains visible and needs a stated decision. No acceptance signature or result exists yet.

After local acceptance, complete hosting checks: legacy credential review/rotation, production debug/HTTPS/cookies, actual document-root mapping, MySQL migrations/concurrency/weekday queries, atomic cache locks, PHP/proxy timeouts, real SMTP/invitations/outbox scheduling, fresh dependency advisories, safe release ZIP and off-host restore. These do not become passed merely because local tests pass.

Useful runbooks: [account provisioning](ACCOUNT_PROVISIONING.md), [private files](PRIVATE_FILES_DEPLOYMENT.md), [request/forecast operations](REQUEST_RELIABILITY_DEPLOYMENT.md), [current matrix](PROJECT_FIX_MATRIX.md).
