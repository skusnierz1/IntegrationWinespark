# Bundle Manager (PHP + MySQL)

Minimal Shopify-style admin to manage product bundles.

## Files
- `index.php` – Login page
- `dashboard.php` – Bundles summary (SKU, Name, Edit/Delete)
- `add_edit_bundle.php` – Add/Edit bundle with multiple product rows (SKU, Quantity, Price)
- `logout.php` – Ends session
- `db.php` – DB connection
- `config.php` – **Edit this with your DB creds and admin password hash**
- `database.sql` – MySQL schema
- `style.css` – Minimal UI

## Quick Deploy (Hostinger)
1. Create a MySQL database & user in Hostinger panel. Note the host, db name, user, password.  
2. Upload all files to your site root (e.g., `public_html/`).
3. Import `database.sql` via phpMyAdmin.
4. Edit `config.php`:
   - Set DB_HOST, DB_NAME, DB_USER, DB_PASS.
   - Set `ADMIN_USERNAME`.
   - Generate a password hash in PHP, e.g. make a `hash.php` locally with:  
     ```php
     <?php echo password_hash('YourStrongPassword', PASSWORD_DEFAULT);
     ```
     Copy the output into `ADMIN_PASSWORD_HASH`.
5. Visit `/index.php` to log in.

## Notes
- Deleting a bundle also deletes its items (foreign key ON DELETE CASCADE).
- All form actions use prepared statements.
- For production, consider HTTPS, CSRF tokens, and stricter session settings.
