# SmileCraft Dental

## Local development

This project is configured for MAMP's Apache and MySQL services. Start MAMP
from Terminal with:

```sh
open -a MAMP
```

In MAMP, start the servers, then open:

- Public website: <http://localhost:8888/zubari/dental-template/>
- Administrator login: <http://localhost:8888/zubari/dental-template/admin/login.php>
- First administrator setup: <http://localhost:8888/zubari/dental-template/admin/setup.php>
- phpMyAdmin: <http://localhost:8888/phpMyAdmin/>

The local `.env` uses MAMP's default MySQL port (`8889`) and local credentials
(`root` / `root`). If you changed the ports or MySQL password in MAMP, update
`DB_PORT` or `DB_PASSWORD` in `.env` to match. Do not open the HTML files as
`file://` URLs; PHP booking and administrator endpoints need MAMP's web server.

The local `dental_booking` database is initialized by applying the SQL files
in `database/migrations/` once, in filename order. On a new setup, use the
Import tab in phpMyAdmin to import each file in that order. The migrations
create a sample clinic and services. After that, open the first-administrator
setup URL above, create your account, and then sign in at the administrator
login URL. The setup page closes after the first account is created.

## Languages

The website supports Serbian Latin (`sr-Latn`), Serbian Cyrillic (`sr-Cyrl`),
English (`en`), Russian (`ru`), and German (`de`). A `?lang=<locale>` query
parameter selects a shareable language version, for example
`http://localhost:8888/zubari/dental-template/booking.html?lang=en`. Without a
valid parameter, the site uses the saved language preference and then Serbian
Latin. The administrator interface stores its language preference separately
from the public website.

## Themes

The public pages share a light/dark theme defined in `css/theme.css`. The
selected appearance is stored in `localStorage` as `smilecraft-theme`; on a
first visit, the browser's `prefers-color-scheme` setting is used. Admin pages
use a separate `smilecraft-admin-theme` preference, shared between the
dashboard and administrator login/setup screens. Each theme control is in the
main navigation or admin header and works without reloading the page.

## Deployment configuration

Copy `.env.example` to `.env` or configure the same values in the PHP process
environment. Set `APP_URL` to the canonical public URL (including any
subdirectory), and provide `DB_HOST`, `DB_PORT`, `DB_NAME`, and `DB_USER`.
`DB_PASSWORD` may be empty only when the database account is configured that
way. Never publish or commit `.env`.

The root `.htaccess` denies dotfiles, dependency manifests, database scripts,
and private backend helpers on Apache. For Nginx or another server, configure
equivalent access restrictions before deployment; application checks do not
replace web-server access controls.

## GitHub Pages preview

The GitHub Actions workflow publishes the public HTML, CSS, JavaScript,
locales, and images to `https://matijakorbic83-dotcom.github.io/sajt/`. This is
a static preview only: GitHub Pages does not execute PHP, so booking requests
and administrator pages are unavailable there. Use a PHP and MySQL hosting
provider for a working booking and administrator backend.

Apply the SQL files in `database/migrations/` in date order. The
`2026_11_14_stabilize_booking_and_admin_auth.sql` migration requires both
existing schema migrations. It adds clinic scope to administrator accounts,
copies legacy `admins` credentials into `admin_users`, and snapshots service
duration for existing appointments. Accounts without a matching legacy clinic
remain unassigned and cannot sign in until assigned to a clinic.

Apply `2026_11_14_create_admin_content.sql` after the existing clinic schema.
It adds clinic-scoped weekly opening hours and a small content-item table for
dentist profiles, reviews, gallery entries, and FAQs. Existing clinics are
initialized with Monday–Friday, 09:00–17:00 hours and a 13:00–14:00 break;
Saturday and Sunday are closed. The schedule remains on 30-minute start
increments. The administrator's opening-hour edits are also used by booking
availability and booking submission.

Apply `2026_11_15_add_categorized_service_catalog.sql` after the existing
service schema migrations. It adds service categories and seeds the 18-item
Serbian dental catalog for each clinic, updating matching catalog entries and
preserving all other services and appointment records.

## Administrator

Open <http://localhost:8888/zubari/dental-template/admin/login.php> to sign in.
For a fresh local installation, use
<http://localhost:8888/zubari/dental-template/admin/setup.php> from the same
computer to create the first administrator and select its clinic. This
one-time setup page only accepts loopback client and host addresses and is
locked as soon as any `admin_users` account exists. Requests with proxy
forwarding headers are denied unless `ADMIN_SETUP_KEY` is configured; when a
key is configured, enter it on the setup form. Keep this key private and
remove it after setup. It never displays or pre-fills a password.
Alternatively, create the account from the project root with
`php backend/init_admin.php`; configure `ADMIN_CLINIC_ID` or enter a clinic ID
when prompted. That utility is CLI-only. The legacy
`/backend/admin.php` dashboard URL redirects to `/admin/index.php`.

The panel provides clinic-scoped appointment search, filters, details,
rescheduling and patient-contact updates, confirmation/cancellation, service
management, clinic contacts/opening hours, and CRUD for the four content-item
types. Appointments are cancelled rather than deleted to retain their booking
history; services can be deactivated rather than deleted because historical
appointments refer to them. Content records are currently stored in the
administrator library but are not rendered by the static public pages. There
is no patient registry: the dashboard's patient metric counts distinct
normalized appointment email addresses, falling back to phone for a blank
email. It is an estimate based on appointment records, not a count of verified
people.

Administrator sessions are revalidated against the account on each request, so
deactivating an account or changing its password invalidates existing sessions.
Sessions expire after 8 hours, or after 30 days when “remember me” is selected.

Configure the `SMTP_*` values and each clinic's notification email address to
enable appointment email.

## Booking schedule

Booking currently uses Europe/Belgrade local time, Monday through Friday,
09:00–17:00, with a 13:00–14:00 break and 30-minute start-time increments.
Availability and booking submission use the same rules; daylight-saving
changes do not alter these local opening hours. Appointment notes are not
collected or stored because the field invited sensitive health information
without providing appropriate storage or access controls.

Future scheduling work should move working weekdays, opening/closing times,
breaks, slot increments, and clinic timezone into clinic-specific settings
with validation and a documented migration policy. Public holidays and
one-off clinic closures are not currently represented and should be included
in that scheduling configuration.
