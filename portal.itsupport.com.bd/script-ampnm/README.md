# AMPNM PHP (XAMPP-ready)

A PHP-first scaffold of the AMPNM app for deployments outside Docker. Drop this folder under your portal at `portalitsupport.com.bd/script-ampnm` (or `C:/xampp/htdocs/script-ampnm`) and extend it with the same device, map, and alerting logic used in the Docker AMPNM build.

## Quick start
1. Copy this folder to `C:/xampp/htdocs/script-ampnm` (or your desired Apache virtual host root).
2. Browse to `http://localhost/script-ampnm` and complete the installer: DB host/name/user/password, base URL, and initial admin user.
3. On the next screen, enter your AMPNM license key (AMP-XXXX-XXXX-XXXX). The app will not run without an active license.
4. Sign in with the admin you created to reach the dashboard and APIs. Move any binary assets (icons, sounds, map backgrounds) into `assets/` manually.

## Included pages
- **Dashboard & Overview**: landing plus dashboard summary cards for uptime, alerts, and incident timelines.
- **Devices**: add/edit form with monitor method (ping vs port), interval, and a copy action to duplicate devices (e.g., `Firewall_copy`).
- **Map**: shareable topology placeholder pointing at future `/api/map` data and public-map URLs.
- **Monitoring graphs**: chart placeholders for latency and port availability plus an uptime leaderboard.
- **Connectivity**: manual ICMP tester wired to `api/ping.php` and guidance for port probes.
- **Logs**: status history table ready to bind to your `status_logs`/`history` data.
- **Notifications**: recent alerts list with a simulator form writing into the `notifications` table.
- **Users**: create/reset flows and role options so admin/client management mirrors the Docker portal.
- **License**: license summary plus separate activation gate to block unlicensed use.

## Extending
- Implement device, topology, and auth endpoints inside `api/` following the structure in `docker-ampnm/api`.
- Add shared helpers to `includes/` and share config through `config.php`.
- Wire SMTP settings from `config.php['mail']` to send password resets, product launch emails, and update notifications.

## Notes
- Binary files (maps, icons, sounds) are intentionally excluded; add them manually to `assets/` when deploying.
- The `ping` API detects Windows vs. Unix flags so manual ping tests work on XAMPP as well as Linux hosts.
