# Spark Admin frontend architecture

The current portal uses the supplied Spark Admin v1.0.0 foundation: primary blue navigation (#293681), secondary blue actions/accents (#4274D9), tertiary highlights (#95CCDD), quaternary tinted surfaces (#D0E7E6), rounded cards, Bootstrap form/table styling and Bootstrap Icons. See [migration report](FRONTEND_MIGRATION_REPORT.md) and [implementation plan](FRONTEND_MIGRATION_PLAN.md).

## Integration

- [Authenticated shell](resources/views/layouts/dashboard.blade.php) supplies role-aware navigation, search, account/profile access, notifications, theme selection and mobile navigation.
- [Profile adapter](resources/views/layouts/app.blade.php) now uses that same shell. Profile no longer starts a separate Vite/Alpine 3 runtime.
- [Guest shell](resources/views/layouts/guest.blade.php) supplies Spark account-access cards for all authentication forms.
- [Style loader](resources/views/partials/portal-styles.blade.php) loads local utility compatibility CSS, Spark Bootstrap, Bootstrap Icons, Spark main CSS, existing portal compatibility rules and [Spark integration CSS](public/assets/css/spark-portal.css), in that order. Tabler CSS is no longer active.
- [Credit partial](resources/views/partials/spark-credit.blade.php) preserves the supplied free-template attribution. Do not remove it without checking the distribution terms.
- [Application interactions](public/assets/js/spark-portal.js) add non-destructive form loading indicators. Existing Alpine 2 controls menus/modals; Spark demo JavaScript and Bootstrap JavaScript are not loaded, avoiding duplicate handlers.
- [Chart controller](public/assets/js/portal-charts.js) retains actual server data and forecasting behavior using locally pinned ApexCharts, with the Spark palette and reduced-motion support. No template sample statistics are imported.

## Compatibility and maintenance

The existing utility stylesheet and portal base rules remain deliberate compatibility dependencies for Blade forms and modal transitions. Existing vendor directories remain on disk, but inactive Tabler/demo scripts are not loaded by the active layouts. Do not remove compatibility files without a reference audit and regression checks. No dependency installation or lockfile changes were required.

All HTTP methods, route helpers, CSRF tokens, upload names, validation output and authorization conditions stay on their existing backend contracts. Source template files were read/copied, never edited. Google Fonts imports were removed only from the copied stylesheet to keep runtime assets local.

## Verification

Final isolated run: 215 PHP tests, 1,270 assertions and seven frontend tests passed; all Blade templates compiled. Browser checks covered each role, module rendering, registrar CRUD, student multipart submission/private download, staff status/history, filtering/CSV, profile confirmation modal and mobile navigation. Working database checksum remained unchanged. These are developer checks; formal local UAT and controlled SMTP/AI service simulations remain pending. See the report for limitations and screenshots.

## Brand palette

Edit the four --portal-primary/secondary/tertiary/quaternary tokens in [Spark integration CSS](public/assets/css/spark-portal.css). Spark legacy variable names map onto these tokens; the chart controller reads them directly. Hover and dark-mode companion shades are defined nearby. Light tertiary/quaternary surfaces use primary-colored text. Semantic success/warning/error colors remain distinct. Email header color is separately inlined for mail-client compatibility.

## Reports and analytics
Admin and registrar navigation includes Reports & analytics. The shared report controller validates academic year/semester, optional date overrides, status, category, document and archive-scope filters. Submission dates determine report membership; status totals represent present recorded outcomes. Reports include archived history by default. Monthly demand, document demand, categories, copies and status totals share one aggregation with CSV and paginated PDF summary exports. CSV cells use formula-injection escaping; exports omit student identity fields. PDF uses a minimal dependency-free text renderer (ASCII transliteration, no chart images). Local UAT exports identify synthetic data. This module does not perform model training, staffing prediction or actual processing-time analysis.
