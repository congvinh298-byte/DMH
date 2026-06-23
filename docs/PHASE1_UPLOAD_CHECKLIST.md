# Phase 1 Upload Checklist

## Files to upload
- Upload `.htaccess` to the hosting document root.
- Upload `assets/images/` with all product images.
- Upload all `api/*.php` modules, especially `api/webhooks.php`.
- Upload changed PHP files if deploying from this workspace:
  - `api_master.php`
  - `api/core.php`
  - `admin_xxx.php`
  - `api/openclaw_chat.php`
  - `database/run_migration.php`
  - `index.php`
  - `insert_sims.php`

## Server `.env` verification
- Confirm `.env` exists in the project root on Vinahost.
- Use one canonical database key set only:
  - `DB_HOST`
  - `DB_NAME`
  - `DB_USER`
  - `DB_PASS`
  - `DB_CHARSET`
- Remove legacy aliases from the server `.env` if present:
  - `DB_USER_BAOCAO`
  - `DB_PASS_BAOCAO`
  - `DB_USER_SHIPPER`
  - `DB_PASS_SHIPPER`
- `DB_NAME` must exactly match the cPanel database name, including letter case.
- In cPanel MySQL Databases, confirm `DB_USER` is assigned to `DB_NAME` and has the required privileges.
- Do not commit or upload `.env` through git. Edit it directly in hosting file manager or SFTP.

## Post-upload checks
- Open the homepage and confirm CSS/JS load with `text/css` and `application/javascript`.
- Open direct asset URLs under `/assets/` and confirm they do not route to `index.php`.
- Open product image URLs under `/assets/images/` and confirm they return `200`.
- Test one API endpoint that touches the database and confirm there is no HTTP 500.
- Check PHP error logs after the first request.

## Rollback
- Keep the previous server `.htaccess` and changed PHP files as timestamped backups before replacing them.
- If the site returns HTTP 500 after upload, restore the PHP files first, then inspect `.env` and PHP error logs before changing `api/core.php`.
