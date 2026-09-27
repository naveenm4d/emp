# EMP

The EMP event management platform as a single Laravel 13 + Inertia v3 + React 19 app: the guest-facing site, the client dashboard and the staff console. It ports the domains of the earlier Go backend (`backend/`, not part of this repo) and keeps their domain-first structure. There is no JSON API; every page calls the domain services directly.

## Who uses what

| Actor                                  | Name in code              | Interface                                       | Auth                                |
| -------------------------------------- | ------------------------- | ----------------------------------------------- | ----------------------------------- |
| Subscribed customer who creates events | **Client**                | Client dashboard, `/app`                        | session guard `client`              |
| EMP platform staff                     | **Staff** (`StaffMember`) | Internal console, `/internal`                   | session guard `staff` + permissions |
| Event attendee                         | **Guest** (no account)    | Site: `/`, `/{slug}/{code}`, `/preview/{event}` | none: short link code               |

## Layout

```
app/
  Core/                         shared kernel
    Contracts/RepositoryInterface.php
    Repositories/BaseRepository.php     abstract Eloquent repository
    Services/BaseService.php            transaction helper
    DTOs/DataTransferObject.php         immutable input
    DTOs/PartialData.php                PATCH input (tracks which keys were sent)
    Exceptions/DomainException.php      business-rule errors (status + error code)
    Http/Controllers/InertiaController.php
    Http/Controllers/Site/HomeController.php  marketing homepage
    Http/Middleware/ContentSecurityPolicy.php strict CSP for site pages
    Http/Responses/InvitationPage.php   renders an invitation + og meta tags
    Providers/DomainServiceProvider.php bindings, policies, listeners
  Domains/
    Client/  Staff/  Template/  Event/  Guest/  Invitation/  Notification/
```

Every domain uses the same layout:

```
Domains/Event/
  Models/  Enums/  DTOs/  Exceptions/  Policies/  Events/  Listeners/  Jobs/
  Contracts/        EventRepositoryInterface, EventServiceInterface, EventQueryServiceInterface
  Repositories/     the only place Eloquent queries are built
  Services/         EventService (writes) + EventQueryService (reads)
  Http/
    Controllers/Site/       Inertia for guests       -> routes/web.php (root view: site)
    Controllers/Dashboard/  Inertia for clients      -> routes/web.php (root view: app)
    Controllers/Internal/   Inertia for staff        -> routes/internal.php
    Requests/  Resources/
  Providers/EventServiceProvider.php   binds interfaces, registers policies and listeners
```

### Rules

- **Request flow:** FormRequest → DTO → service interface → Resource.
- **Services** hold every business rule and throw `DomainException` subclasses. After a form post they render as a flash error; on a page load, as the matching HTTP error page.
- **Domains depend on each other only through interfaces or domain events.** Two examples:
    - `InvitationSent` → the Notification domain sends WhatsApp, and the Guest domain marks the RSVP as pending.
    - Staff statistics come from each domain's query service.
- **Client ownership** is enforced by policies. **Staff access** uses `can:<permission>` route middleware, backed by the `StaffPermission` gates. `super_admin` passes every permission check.

## Guest-facing site

Public Inertia pages with their own root view (`resources/views/site.blade.php`), stylesheet (`resources/css/site.css`) and a strict Content-Security-Policy (only this app's nonce'd scripts run; see `config/csp.php`).

| Method   | Path                                 |                                                                     |
| -------- | ------------------------------------ | ------------------------------------------------------------------- |
| GET      | `/`                                  | marketing homepage; its CTAs lead to `/app`                         |
| GET      | `/{slug}/{code}`                     | short link: the event's public page, or a guest's invitation + RSVP |
| POST     | `/{slug}/{code}/register`            | public registration (capacity, duplicates, approval)                |
| POST     | `/{slug}/{code}/accept` · `/decline` | idempotent; an expired invitation flashes an error                  |
| GET      | `/e/{slug}`, `/rsvp/{token}`         | older link formats; 301 to the short link                           |
| GET      | `/preview/{event}`                   | client preview of the invitation; needs the dashboard's signed link |
| GET/POST | `/webhooks/whatsapp`                 | Meta verification; HMAC-signed status callbacks (no session / CSRF) |

Unknown tokens and pages show `site/errors/not-found`, a bad or expired preview signature shows `site/errors/link-expired`, and throttling or server errors show `site/errors/unavailable`. Invitation pages put the event's title, description and cover image into `og:` meta tags so shared links preview nicely.

## Invitation templates

Invitations work like WordPress themes. The template code is fixed, and the client only fills in its placeholders.

- **A template** is a catalogue entry (`templates`: key, name, author, category, price, display price, and so on) with immutable **versions** (`template_versions`).
- **A version's code** is a package stored on the template disk at `templates/{key}/{version}/`:

    ```
    template.json   metadata: key, name, version, author, category, price, display_price, fonts, slot_labels
    template.html   markup with placeholders
    styles.css      optional; only {{ asset:… }} placeholders are allowed here
    thumbnail.webp  optional (png/jpg/webp)
    assets/…        optional images the code uses via {{ asset:path }}
    ```

- **Placeholders** use a tiny language that never executes code (`app/Domains/Template/Support/PlaceholderSyntax.php`):

    | Placeholder                                                                     | Meaning                                                          |
    | ------------------------------------------------------------------------------- | ---------------------------------------------------------------- |
    | `{{ img_1 }}` … `{{ img_N }}`, `{{ video_1 }}` …, `{{ bg_music }}`              | media slots the client uploads into; the value is the file's URL |
    | `{{ event.title }}`, `event.date`, `event.start_time`, `event.location_name`, … | event details                                                    |
    | `{{ guest.name }}`                                                              | filled per guest when the invitation is viewed                   |
    | `{{ asset:images/bg.png }}`                                                     | a file from the package's `assets/` folder                       |
    | `{{ rsvp }}`                                                                    | where the site mounts the Accept / Decline buttons               |
    | `{{#if name}} … {{/if}}`                                                        | keeps the block only when `name` has a value                     |

    **The media slots come from the code itself.** A media placeholder outside every `{{#if}}` is required, and an event cannot be published until it is uploaded. A media placeholder used only inside `{{#if}}` blocks is optional.

    Background music is written as `<audio data-emp-bg-music src="{{ bg_music }}" loop></audio>`, and the site adds the play button. The RSVP block uses the classes `.emp-rsvp`, `.emp-rsvp__message`, `.emp-rsvp__actions` and `.emp-rsvp__button--accept/--decline`, which templates may style.

- **Import:** `php artisan templates:import path/to/package` (a folder or `.zip`).
    - It validates the metadata.
    - It rejects unsafe markup: `<script>`, `on…=` handlers, `javascript:` URLs, iframes and similar.
    - It parses the slots and uploads the package.
    - A version can never be changed after import. Publish a new version instead.
    - The starter templates (`classic`, `modern`, `floral`) are in `database/seeders/templates` and are imported by the seeder.
- **Events** must pick a template, and they are **pinned** to its current version. When a newer version is imported, the Design tab offers an upgrade.
- **Rendering:** whenever an event's details, template or media change (the `EventDesignChanged` event), the app fills the event and media placeholders and writes the result to the render disk (`invitations/{event}/{hash}.html`). Only links to the media go into that file. `{{ guest.name }}` is filled per request. `/rsvp/{token}` displays it inside a shadow root.
- **Disks** (`config/emp.php`):
    - `EMP_TEMPLATE_DISK`: default `public`. It must be publicly readable, because browsers load template assets.
    - `EMP_MEDIA_DISK`: default `public`, for client uploads.
    - `EMP_RENDER_DISK`: default `local` (private), for the generated invitations.
    - In production, all three can point at one S3 or R2 bucket.
- Media limits are set per type in `config/emp.php → media`: image 5 MB, video 50 MB, audio 10 MB. For video uploads, raise PHP's `upload_max_filesize` and `post_max_size` to at least 50M.

## Getting started

Requires PHP 8.4+ (with `pdo_pgsql`), Node 22+ and Docker. On this machine Homebrew's current PHP is at `/opt/homebrew/bin/php`. The `php` first on `PATH` is `php@8.2`, so put `/opt/homebrew/bin` ahead of it: `export PATH=/opt/homebrew/bin:$PATH`.

```bash
docker compose up -d                  # Postgres 16 on :5433 (plus the emp_testing database)
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed          # also imports the starter templates
php artisan storage:link
composer run dev                      # server on :8000, queue worker, logs, Vite
```

Events now require a template, so a database created before templates existed needs `php artisan migrate:fresh --seed`.

Seeded accounts (password `password`):

- `client@emp.test` → `/app` (e.g. open the Summer Gala's public page at `/e/summer-gala-2026`)
- `admin@emp.test` (super admin) → `/internal`
- `viewer@emp.test` → `/internal`

To create a real staff account, run `php artisan staff:create`.

WhatsApp:

- Leave `WHATSAPP_API_TOKEN` empty to use the mock provider.
- Set `META_APP_SECRET` to verify webhook signatures.
- The scheduler runs `notifications:requeue-pending` every 5 minutes (`php artisan schedule:work`).

## Checks

```bash
php artisan test          # Pest, against the Postgres emp_testing database
vendor/bin/phpstan analyse
vendor/bin/pint --test
npm run types:check && npm run check
```
