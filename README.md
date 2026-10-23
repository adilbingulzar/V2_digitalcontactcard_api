# TIQS API

Tipping and review platform: the Laravel API of the rewrite of the CodeIgniter 3 app (`/var/www/html/tips`). The website (Next.js) is in its own repo, [V2_digitalcontactcard](https://github.com/PeteRose/V2_digitalcontactcard).

| Part | Stack | Local URL |
|---|---|---|
| API (this repo) | Laravel 13 · PHP 8.5 · Sanctum | http://localhost:8000 |
| Database | MySQL 8.0 locally (8.4/9.7 LTS in production) | `tiqs_upgrade` |

Deployment: Laravel Forge. The API runs on the `api.` subdomain; the website runs on the main domain.

## Local setup

The server's default `php` is 8.1. `bin/` holds wrappers that run PHP 8.5 and Composer 2.10, so put it first on your PATH:

```bash
export PATH="$PWD/bin:$PATH"
composer install
cp .env.example .env && php artisan key:generate   # then set the DB_* values
php artisan migrate --seed
php artisan serve
```

**API and queue at once:** `bin/dev` starts the API (port 8000) and a queue worker for emails and text messages. `bin/dev seed` first resets the database with demo data, and `bin/dev stop` stops both. Logs go to `storage/logs/dev-*.log`. Start the website from its own repo with `npm run dev`.

## Languages

The site speaks the old app's 80+ languages; English texts are the translation keys.

- After changing texts in the website, run `npm run texts` in the website repo. It writes `database/seeders/data/ui-texts.json` in this repo (it looks for this repo next to the website repo; set `BACKEND_DIR` if it is somewhere else). Commit that file here.
- On every deploy run `php artisan tiqs:sync-ui-texts`: it adds new texts and the bundled Dutch translations without touching texts admins edited.
- Admins translate under Admin › Translations. With `GOOGLE_TRANSLATE_KEY` set, missing translations are filled in automatically in the background, as the old app did.

## Databases

| Database | Purpose |
|---|---|
| `tiqs_upgrade` | The new app's database, built with migrations and seeders |
| `tiqs_legacy` | Copy of the old CodeIgniter data. Only ever read. Locally the merged itsmycontactinfo + old site data is in `tiqs_legacy_merged` (`LEGACY_DB_DATABASE=tiqs_legacy_merged`) |
| `tiqs_upgrade_test` / `tiqs_legacy_test` | Used by the test suite (`php artisan test`) |

**Seeding (Forge):** `php artisan migrate --force && php artisan db:seed --force` fills a new database with all real data: the customer data from `database/seeders/data/customer-data.enc` (same ids, so printed QR codes keep working), the app settings and the translations. The file is encrypted: set `SEED_DATA_KEY` on the server to the value in the `.env` that exported it. Seeding never touches a database that already has users.

**Updating the seed files:** after the data changed here (for example a new import), run `php artisan tiqs:export-seed-data` and commit `customer-data.enc` and `translations.json`.

**Demo data** (local only): `php artisan migrate:fresh --seed` without `SEED_DATA_KEY` creates `admin@`, `business@`, `waiter@` and `legacy@tiqs.test`. Every password is `password`. Demo accounts are never added to a database with customers.

**Real data:** `php artisan tiqs:import-legacy --fresh --legacy-path=/var/www/html/tips` rebuilds the database and copies everything from `tiqs_legacy`. It ends with a per-table summary and a list of every data problem it fixed or skipped. It refuses to run on a database that already has users unless `--fresh` is given.

**Old photos:** the database keeps the old image file names. The images are in `public/assets/images` (not in git, see below); set `LEGACY_ASSETS_URL=https://<api domain>/assets`. `php artisan tiqs:import-images` would instead convert them to WebP and rename them in the database; it is not used.

Legacy passwords are unsalted md5. They're imported as-is and replaced with a modern hash the first time each user logs in.

## Files that are not in git

Files that users uploaded are never committed. Git ignores these folders, so a deploy does not touch them on the server:

| Folder | What is in it | On the server |
|---|---|---|
| `public/assets/images/` | Images uploaded in the old app: profile photos (`users/`), card logos (`business/`), card banners (`vcard/`) and tip page backgrounds (`pattern/`), with their old names | **Upload it from your computer once**, before go-live (see below) |
| `storage/app/public/` | Files users upload in this app: profile photos, card logos and banners, card pictures, tip page backgrounds. Shown under `/storage` | Filled by the users on the server. Run `php artisan storage:link` once. Do not upload the local copy: it only holds test files |
| `storage/app/private/` | Private uploads: business cards and pictures sent to the card assistant. Never public | Filled on the server. Do not upload the local copy |

**Uploading the old images** (from this repo's folder on your computer; replace the server and site folder with yours from Forge):

```bash
rsync -avz public/assets/ forge@SERVER_IP:/home/forge/SITE_FOLDER/public/assets/
```

- Then set `LEGACY_ASSETS_URL=https://<api domain>/assets` in the server's environment (Forge › Site › Environment).
- Without `--delete`, rsync only adds and updates files, so it never removes anything on the server.
- If the site uses zero-downtime deploys (a new release folder per deploy), keep these folders in the shared folder Forge links into every release, or they are lost on the next deploy.
- After go-live the server holds the only copy of the users' uploads: include `storage/app` and `public/assets` in the server backups. To move to another server, copy these folders from the old server, not from your computer.

`.env` (keys and passwords) is not in git either: fill it in on the server (Forge › Site › Environment).

## Optional services

Each is off until its settings are filled in `.env`; the site works without them.

| Service | Settings | What it does |
|---|---|---|
| Pay.nl | `PAYNL_*` | Tip and plan payments |
| SMS | `SMS_*` | Verification codes and reset links by text message |
| Google / Apple Wallet | `GOOGLE_WALLET_*`, `APPLE_WALLET_*` | Business card wallet passes |
| Google Translate | `GOOGLE_TRANSLATE_KEY` | Fills in missing translations |
| Sumsub | `SUMSUB_*` (webhook: `POST /api/v1/webhooks/sumsub`) | Identity verification |
| Web Push | `WEBPUSH_*` (make keys with `php artisan tiqs:webpush-keys`) | Browser push notifications |
