# AMPNM PHP (XAMPP-ready)

A PHP-first scaffold of the AMPNM app for deployments outside Docker. Drop this folder under your portal at `portalitsupport.com.bd/script-ampnm` (or `C:/xampp/htdocs/script-ampnm`) and extend it with the same device, map, and alerting logic used in the Docker AMPNM build.

## Quick start
1. Copy `config.sample.php` to `config.php` and update database and SMTP credentials.
2. Ensure PHP 8.1+ is enabled in XAMPP/Apache.
3. Visit `/script-ampnm/` to load the starter UI and test the manual ping endpoint at `/api/ping.php`.
4. Move your map images and sound assets into `assets/` (binary assets are not tracked in git).

## Extending
- Implement device, topology, and auth endpoints inside `api/` following the structure in `docker-ampnm/api`.
- Add shared helpers to `includes/` and share config through `config.php`.
- Wire SMTP settings from `config.php['mail']` to send password resets, product launch emails, and update notifications.

## Notes
- Binary files (maps, icons, sounds) are intentionally excluded; add them manually to `assets/` when deploying.
- The `ping` API detects Windows vs. Unix flags so manual ping tests work on XAMPP as well as Linux hosts.
