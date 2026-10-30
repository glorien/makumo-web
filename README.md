# Makumo HEMS – APK Download Portal

A small PHP portal with login where signed-in users can download the Android APKs of the Makumo HEMS app. Releases are read live from the GitHub Releases of the app repository.

## How it works

- **Login** uses sessions and bcrypt password hashes. Users are defined in `config/users.php`; there is no database.
- **Releases** are fetched from the GitHub API (`/repos/{owner}/{repo}/releases`). Only published releases (no drafts) with at least one `.apk` asset are shown, along with the first 5 lines of the changelog.
- **Cache:** The API response is stored in `cache/releases.json` for 5 minutes.
- **UI:** Tailwind (CDN), dark theme, German language.

## Structure

| Path | Contents |
|---|---|
| `index.php` | Redirects to `downloads.php` or `login.php` depending on login state |
| `login.php` / `logout.php` | Sign in and sign out |
| `downloads.php` | List of releases with APK download buttons |
| `includes/auth.php` | Session handling, login, logout |
| `includes/github.php` | GitHub API request, cache, formatting |
| `includes/layout.php` | HTML head, navigation, footer |
| `assets/style.css` | Additional styles |
| `config/*.php.example` | Configuration templates |
| `scripts/create-user.php` | Generates password hashes (CLI only) |

## Requirements

- PHP 8.0+ with the `curl` extension
- Apache with `.htaccess` enabled (blocks `config/`, `cache/`, `includes/`, `scripts/` and `.example` files)
- Write access for the web server in the project directory (for `cache/`)

## Configuration

Both files are gitignored and are **not** deployed automatically. Upload them once by hand via FTP.

1. **`config/env.php`** – copy from `config/env.php.example`:
   - `GITHUB_OWNER`, `GITHUB_REPO`: the repository whose releases are shown
   - `GITHUB_TOKEN`: personal access token with read access to the repository (optional for a public repository, but raises the rate limit)

2. **`config/users.php`** – copy from `config/users.php.example` and add users. Generate a hash with:

   ```bash
   php scripts/create-user.php "MyPassword"
   ```

   The password must be at least 8 characters long.

## Running locally

```bash
cp config/env.php.example config/env.php
cp config/users.php.example config/users.php
php -S localhost:8000
```

Then open `http://localhost:8000`. Note: the PHP built-in server ignores `.htaccess`.

## Deployment

Every push to `main` uploads the project via FTP using GitHub Actions (`.github/workflows/deploy.yml`). Excluded are `.git/`, `.github/`, `config/`, `cache/`, `scripts/`, log files and `.DS_Store`.

Required repository secrets:

| Secret | Meaning |
|---|---|
| `FTP_HOST` | FTP server |
| `FTP_USER` | FTP user |
| `FTP_PASSWORD` | FTP password |
| `FTP_SERVER_DIR` | Target directory on the server (with trailing `/`) |

## Known limitations

- Download links point directly to GitHub's `browser_download_url`. If the app repository is private, they do not work in the browser without a GitHub login.
- No CSRF protection and no limit on login attempts.
- Tailwind is loaded from the Play CDN, which is not intended for production use.
