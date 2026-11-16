# Secure account provisioning and activation

Default role seeding creates only the student, registrar and admin roles. It no longer creates privileged or student accounts with source-defined passwords. Default catalog/settings seeding is repeatable and preserves existing document weights and the program list. No existing user/password is automatically changed by this code update.

## First administrator

On a trusted server console after applying migrations and normal role/catalog provisioning:

```bash
php artisan db:seed --force
php artisan app:create-admin administrator@example.edu --name="School Administrator"
```

Use the actual approved administrator email. The command asks twice for a hidden password of 12–72 characters, with a 72-byte limit for bcrypt (multibyte characters use more than one byte). It never accepts a password command-line argument or prints the password. Do not pipe a password through shell history. An existing email is refused: this command cannot silently promote a student, replace staff credentials or overwrite another account. Store credentials according to the institution's access procedures.

**Existing installations:** changing seeders does not rotate previously seeded passwords. The responsible account owners must replace any legacy/shared credentials through the existing password-change/reset mechanisms before exposure. Do not indiscriminately delete or rename existing accounts or create another admin using someone else's email. Review any roleless legacy accounts with school records; there is deliberately no automatic student/admin-role backfill.

## Student accounts and activation

Authorized admin/registrar staff first create the student through the existing student-management workflow, verifying the student ID, school-held email, program and category. These records remain authoritative. Existing staff-created passwords continue to work; if using activation onboarding, use a unique temporary password at provisioning and avoid distributing a shared password.

Send an activation invitation from the trusted console:

```bash
php artisan students:invite SCHOOL-STUDENT-ID
```

The command emails only the provisioned student's address; it does not accept an arbitrary destination or print the token. Staff/dual-role accounts are not eligible. This invitation is sent through the configured mailer directly, independently of the document-status outbox. SMTP failure is reported; retry after resolving mail settings and respecting the password broker's throttle interval.

The student follows `/register?email=...&token=...`, supplies the matching student ID and sets a confirmed password. Activation cannot create a user, change school-provisioned identity or choose a role. The password broker provides hashed token storage, expiration and single-use consumption; a row lock/recheck protects concurrent replay. Existing student-role access is preserved, the session ID is regenerated, and the student reaches their dashboard. Registration POST is throttled. The page remains available without a token to explain the invitation requirement, but activation requires valid details.

Activation deliberately shares Laravel's password-reset broker with normal password recovery. A new invitation/reset token invalidates the prior one for that email, and a valid password-recovery token for an eligible student can also set their password through activation. This grants no additional identity/role privileges. Expiration follows `auth.passwords.users.expire` (normally 60 minutes); resending respects its throttle (normally 60 seconds). Tokens are credentials: keep links out of logs/screenshots and use HTTPS; hosting access logs should redact sensitive query strings. Staff accounts continue using normal password recovery rather than student activation.

Roleless users are refused login and receive a contact-school message. The authenticated dashboard also denies users without a supported role rather than redirecting them into an inaccessible student page. Existing admin/registrar/student role middleware and record-ownership restrictions remain in force.

## Demo tools

Student/request generators, demo student seeding and destructive demo cleanup are restricted to `local` or `testing`; they refuse production and staging environments. A local environment flag is not a substitute for separating demo and real databases. Never label a live database as local to bypass this restriction. Generated students get unique random passwords by default and no passwords are printed. An explicit local-only generator password option remains for controlled demo use; it is not a production onboarding path.

Do not run demo cleanup against real records. It is still a destructive local tool, not a release-preparation command. The update does not execute any generator, seeder, invitation or account-creation command against existing project data.

## CSV export protection

Student and request exporters share `CsvCell::escape`. Formula-leading text, including whitespace/control/Unicode prefixes, is apostrophe-prefixed before proper CSV encoding. Genuine numeric fields remain numeric; ordinary text, commas and quotes retain normal CSV behavior. Tabs/newlines at the start of text are escaped. Formula text may visibly include the protective apostrophe in some importers; do not strip it automatically or reinterpret exported text as formulas. This reduces spreadsheet formula interpretation; it is not a replacement for endpoint authorization or user care when importing/editing CSV files.

## Automated checks and remaining operational checks

Tests cover repeatable account-free seeding, hidden-password provisioning, existing-account refusal, production demo guards, invitation expiry/replay/identity/role boundaries, and formula cells through both staff export routes. They use fake email and an isolated database. No UAT, real invitation, real account reset or hosted scheduler verification is performed by those tests.

Before real email use, confirm approved SMTP configuration and the scheduler described in [status-email operations](REQUEST_RELIABILITY_DEPLOYMENT.md). Scheduler availability on the hosting server cannot be established from a local code check. Activation invitations and persisted request-status emails use different delivery paths; verify both in a controlled operational check when authorized.


## Account removal safeguards

Student-management, registrar-management and self-service profile deletion use [DeleteUnusedAccount](app/Services/DeleteUnusedAccount.php). Accounts owning any request or referenced as a request-event actor are retained. The last admin cannot self-delete; an unused admin may leave while another admin remains. Blocked self-service requests preserve authentication and display the reason.

Unused-account removal transactionally clears its database sessions, reset token, notifications and role links. No physical-file cleanup runs: request owners are retained with their attachments. Registrar CRUD manages registrar-only accounts.

These guards do not protect manual SQL or destructive demo cleanup. Do not use those paths to bypass retention. Agree retention/deactivation policy before adding purge functionality; reconcile historical orphans separately. Verify concurrent operations and foreign-key behavior on the eventual hosting database; automated tests use SQLite. This batch deletes no real accounts or files and requires no migration.
