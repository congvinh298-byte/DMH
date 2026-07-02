# Environment And Htaccess Standard

## Database environment names
Use exactly one database configuration set in `.env`:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_CHARSET`

Do not use database aliases in application code:

- `DB_USER_BAOCAO`
- `DB_PASS_BAOCAO`
- `DB_USER_BAOCAO`
- `DB_PASS_BAOCAO`

`DB_NAME` must match the real cPanel database name exactly, including letter case. `DB_USER` must be assigned to that database in cPanel MySQL Databases.

## Deployment checks
- Upload `.htaccess` to the document root.
- Upload all files under `api/`; `api_master.php` requires these modules from disk.
- Upload `assets/images/` so product image URLs return real image files.
- Keep `.env` outside git. Edit it directly on the server.

## Htaccess behavior
- `/assets/` and `/uploads/` are served directly.
- `/api/openclaw_chat.php` is the only public direct PHP file under `/api/`.
- Internal API modules such as `/api/core.php` and `/api/webhooks.php` are blocked from direct browser access and loaded through `api_master.php`.
- Missing static/API files return plain 404/500 responses instead of being converted into `index.php` HTML.
