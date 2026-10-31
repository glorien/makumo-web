# Makumo HEMS – APK Download Portal

A small PHP portal with login where signed-in users can download the Android APKs of the Makumo HEMS app. Releases are read live from the GitHub Releases of the app repository.

## How it works

- **Login** uses sessions and bcrypt password hashes. There is no database: fixed users live in `config/users.php` (uploaded by hand), invited users in `config/users.json` (written by the portal).
- **User management:** Admins (users in `config/users.php` with `'admin' => true`) see a "Nutzer" button that opens `admin.php`. There they invite a user by name and email. The portal shows a one-time link (`/invite?t=…`, valid for 48 hours) that the admin sends on themselves, e.g. by messenger; no email is sent. The invitee sets their own password via the link and is signed in. Only a SHA-256 hash of the token is stored. "Neuer Link" issues a fresh link for an invited user (expired invitation or forgotten password; the old password keeps working until the new one is set). "Löschen" removes an invited user and ends their open sessions. Fixed users from `config/users.php` cannot be changed on this page, and admin rights can only come from that file.
- **Login protection:** CSRF tokens on login and logout (logout is POST only). After 5 failed attempts from one IP, login is blocked for 15 minutes (`cache/login_attempts/`).
- **Releases** are fetched from the GitHub API (`/repos/{owner}/{repo}/releases`). Only published releases (no drafts) with at least one `.apk` asset are shown, along with the first 5 lines of the changelog and the GitHub download count.
- **Cache:** The API response is stored in `cache/releases-v3.json` for 5 minutes. If the API fails, the expired cache is used instead.
- **Clean URLs:** Pages are addressed without extension (`/login`, `/downloads`, `/admin`, …). `.htaccess` maps them to the PHP files via mod_rewrite and answers old URLs with extension (`/login.php`, `/index.php`) with a 301 to the clean form; POST requests to old URLs are served directly instead, since a redirect would drop the form data.
- **Downloads** go through `download.php`, which works with private repositories: it checks the login, accepts only assets from the release list, requests a short-lived signed URL from GitHub and redirects the browser there. The token never leaves the server and APK traffic does not pass through the web space.
- **Download log:** Every download is appended to `logs/downloads.log` (tab-separated: time, user email, tag, file name, IP).
- **UI:** Design system "Luminous Flux" from the app repository (violet canvas, amber for the server app, cyan for the client app), Tailwind (prebuilt `assets/tailwind.css`), Material Symbols icons (Google Fonts), German language. Text uses the app's "Outfit" type theme: the same static Outfit cuts (Light, Regular, Medium) are self-hosted in `assets/fonts/`, and the Tailwind weight scale is mapped like the app (`bold`/`semibold` → 500, `medium` → 400). Assets whose file name contains `server` or `client` get the matching app card.

## Structure

| Path | Contents |
|---|---|
| `index.php` | Redirects to `/downloads` or `/login` depending on login state |
| `login.php` / `logout.php` | Sign in and sign out |
| `admin.php` | User management for admins: invite, new link, delete |
| `invite.php` | Invitation link target: invitee sets their password |
| `downloads.php` | List of releases with APK download buttons |
| `download.php` | Download proxy: checks login, logs and redirects to GitHub |
| `includes/auth.php` | Session handling, login, logout, admin check |
| `includes/users.php` | User store (`config/users.php` + `config/users.json`), invitation tokens |
| `includes/github.php` | GitHub API requests, cache, signed download URLs, formatting |
| `includes/layout.php` | HTML head, navigation, footer |
| `assets/tailwind.css` | Prebuilt Tailwind CSS (generated, do not edit) |
| `assets/style.css` | Additional styles |
| `assets/fonts/outfit-*.ttf` | Outfit font, copied from the app repository's `ui-shared/src/main/res/font/` (SIL Open Font License 1.1) |
| `assets/logo*.svg` | Kangaroo logos (brand, server, client), copied from the app repository's `docs/assets/` |
| `tailwind.config.js`, `assets/tailwind.input.css` | Tailwind build input |
| `config/*.php.example` | Configuration templates |
| `scripts/create-user.php` | Generates password hashes (CLI only) |
| `scripts/router.php` | Router for the PHP built-in server (clean URLs locally) |

## Requirements

- PHP 8.0+ with the `curl` extension
- Apache with `.htaccess` enabled, mod_rewrite (clean URLs) and mod_alias (returns 404 for `config/`, `cache/`, `includes/`, `scripts/`, `logs/`, and blocks `.example` files)
- Write access for the web server in the project directory (for `cache/`, `logs/` and `config/users.json`)
- On hosts running PHP-FPM, the `php_flag` lines in `.htaccess` have no effect: turn off `display_errors` in the hosting control panel

## Configuration

Both files are gitignored and are **not** deployed automatically. Upload them once by hand via FTP. `config/users.json` is created by the portal itself; it is also gitignored and excluded from deployment, so pushes never overwrite it.

1. **`config/env.php`** – copy from `config/env.php.example`:
   - `GITHUB_OWNER`, `GITHUB_REPO`: the repository whose releases are shown
   - `GITHUB_TOKEN`: personal access token with read access to the repository's contents (required for a private repository; a fine-grained token with "Contents: read-only" is enough)

2. **`config/users.php`** – copy from `config/users.php.example` and add users. Generate a hash with:

   ```bash
   php scripts/create-user.php "MyPassword"
   ```

   The password must be at least 8 characters long. Give at least one user `'admin' => true`; everyone else can then be invited through the portal instead of editing this file.

## Running locally

```bash
cp config/env.php.example config/env.php
cp config/users.php.example config/users.php
php -S localhost:8000 scripts/router.php
```

Then open `http://localhost:8000`. The PHP built-in server ignores `.htaccess`; `scripts/router.php` mirrors its clean-URL and 404 rules.

## Building the CSS

Tailwind classes are compiled into `assets/tailwind.css`, which is committed so deployment needs no build step. After changing classes in the PHP files, rebuild it:

```bash
npx tailwindcss@3.4.17 -i assets/tailwind.input.css -o assets/tailwind.css --minify
```

## Deployment

Every push to `main` uploads the project via FTP using GitHub Actions (`.github/workflows/deploy.yml`). Excluded are `.git/`, `.github/`, `config/`, `cache/`, `logs/`, `scripts/`, log files, `.DS_Store`, the Tailwind build input and this README.

Required repository secrets:

| Secret | Meaning |
|---|---|
| `FTP_HOST` | FTP server |
| `FTP_USER` | FTP user |
| `FTP_PASSWORD` | FTP password |
| `FTP_SERVER_DIR` | Target directory on the server (with trailing `/`) |

## Known limitations

- The login lock is per IP address. Users behind the same IP (e.g. an office network) share one counter.
- Download counts come from GitHub and can be up to 5 minutes old because of the cache.
