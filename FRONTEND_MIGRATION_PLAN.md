# Spark Admin v1.0.0 migration plan

Source: `D:/templates/spark-admin-v1.0.0` (read-only). Target: existing Laravel Blade application. Prepared before implementation.

## Inspection and integration contract

The source has nine HTML pages (dashboard, blank, login, 404, basic tables, buttons and forms, plus documentation), compiled Bootstrap CSS/JS, Bootstrap Icons/fonts, ApexCharts, Flatpickr, main.css, dashboard.js and auth.js. Its dashboard JS owns navigation and includes static demonstration financial series. It must not replace application controllers or real chart inputs. Source README says MIT; bundled documentation explicitly requires the Spark Admin footer attribution. Preserve both notices and visible footer credit. Remove the CSS Google Fonts import in the vendored adaptation; use local portal typography with no runtime font CDN.

Existing views comprise admin/registrar/student dashboards, requests, student/registrar management, settings, profile, six authentication screens, 404, pagination, email and unreferenced starter/demo views. Routes use GET lists/filters/exports, POST creation, PUT/PATCH updates and DELETE removal with Blade CSRF/method fields. Staff status/archive forms carry signed edit tokens and validation state. Student submission carries a retry token and multipart proof upload. Notification links/mark-read are authorized server-side. Forecast GET is read-only; POST generation requires CSRF and explicit action. Preserve these contracts and existing payload names, IDs, focus handlers and Alpine callbacks.

## Page/component mapping

| Existing module/views | Spark foundation | Presentation changes | Preserved behavior / risks |
| --- | --- | --- | --- |
| layouts/dashboard + portal partials | sidebar-wrapper, sidebar-brand, sidebar-menu-link, main-wrapper, navbar-custom, footer-custom | Native forest/lime sidebar/header/footer, responsive content frame, animated menus and visible attribution | Role-aware links, global search filters, notifications, logout, inert background/focus trap; avoid loading competing Spark demo navigation JS |
| Staff dashboards / staff-overview / forecasting | card/card-stat, stat-label/value, chart cards | Spark metrics, priority list and actual-data ApexCharts with motion | Existing aggregation, forecast cache/generation and histories; reduced-motion support |
| Student dashboard | Spark cards and data lists | Same component system with student-specific actions | Own counts/requests, submission link, permissions |
| Admin/registrar request index + shared table | Spark premium table + responsive records | Same seven fields/groups, compact actions, wrap/stack at narrow widths | Rank, search/status/archive filters, pagination, exports and detail URLs |
| All role request detail/create views + history/conflict partials | Spark content/form cards, badges, alerts | Native form controls, grouped review sections, consistent action hierarchy | CSRF/edit/submission tokens, status/remarks drafts, uploads/downloads, history pagination and error bags |
| Student CRUD shared by staff, registrar CRUD | Spark tables, form cards and dialogs | Redesign standalone create/edit and modal forms consistently | Fields, role scopes, all create/edit/delete actions, validations and confirmation behavior |
| All settings screens and document-type fields | Spark form cards, lists and modals | Same inputs, category/program management and weight layouts with native controls | Setting keys, complete arrays, reference guards, modal identity and errors |
| layouts/guest + six auth views | login-wrapper, login-card, native controls | Spark centered authentication card/decorations and attribution | Login/activation/reset/verify/confirmation methods, tokens, error feedback; no demo auth.js redirect |
| Profile + layouts/app + input/button/modal components | Shared Spark shell/form cards/dialog treatment | Unify profile with portal shell instead of separate Breeze navigation/runtime | Profile/password/delete actions, named error bags, confirmation/current password; avoid duplicate Alpine runtimes |
| 404 + unreferenced starter views | Spark error/content layout | Consistent error screen and inherited shared shell where referenced | Existing links; dormant template demo data must not become production dashboard data |
| Pagination and emails | Spark pagination; plain email-safe branding | Pagination styling and noninteractive message consistency | Query strings, mail snapshot/content; email does not load browser template JS/CSS |

## Stages and verification

1. Copy necessary Spark CSS, Bootstrap and icons locally with source notices; preserve source template. Shared shell/guest/error foundations and integration stylesheet. Keep only one component event runtime: existing Alpine. Keep MIT-pinned ApexCharts rather than replacing it with an unverified bundled build.
2. Apply native Spark classes across module cards, tables, form controls, actions, alerts, modal panels and shared components. Rebuild profile shell and role navigation consistently. Retain compatibility layout utilities where required for existing Alpine dialogs; no Tabler CSS in active shell.
3. Adapt actual-data charts, theme tokens, entrance/hover/dropdown motion and reduced-motion handling. Preserve compact no-scroll request list.
4. Compile Blade; run full PHP and frontend regressions. Verify desktop/laptop/tablet/mobile layouts with isolated fixtures, roles, forms/modals, errors, search/pagination, private-file paths, notifications and forecast behavior. Record console/missing-asset problems and fix them. Local-only mail/HTTP simulations remain distinct from live-provider verification.
5. Write a new FRONTEND_MIGRATION_REPORT.md without overwriting an existing report. Include inventory, files, preserved contracts, exact evidence, screenshots when available and remaining limits. Do not claim full UAT acceptance or real integrations from page-load checks.

## Scope boundaries

No backend, route, authorization, database, dependency installation or source-school-data changes. Old assets are removed only when references and release packaging are verified; otherwise leave dormant assets on disk and state so. Existing user changes are preserved. All verification mutations use isolated local UAT or in-memory fixtures. No deployment, real email or live AI is authorized by this migration.
