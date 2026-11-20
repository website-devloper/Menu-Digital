# Wevtex · Menu Digital

A reusable **digital menu + admin dashboard** for restaurants.
Customers scan a QR code and see the menu. The restaurant manages categories,
dishes, photos and availability from an admin dashboard.

The look & feel is **inherited** from the Foores restaurant site and its admin
dashboard (plum `#2A1324` + gold `#F1AC18`, cream canvas `#F2EEE6`,
Plus Jakarta Sans), via a single `shared/theme.css` file.

---

## Stack

- **PHP 8+** (no framework, no build step) — same stack as the Foores site
- **SQLite** (`data/menu.db`) — single-file DB, zero setup, easy to back up
- **Vanilla HTML/CSS/JS** — Plus Jakarta Sans via Google Fonts CDN
- **QR libs via CDN**
  - Generate: [`qrcodejs`](https://github.com/davidshimjs/qrcodejs)
  - Scan: [`html5-qrcode`](https://github.com/mebjas/html5-qrcode)

No Node, no `npm install`, no compilation.

---

## Run it locally

```bash
cd menu-digital
php -S localhost:8000
```

Then open **http://localhost:8000/setup.php** once — this creates the SQLite DB
and seeds a demo restaurant (`foores`) with categories and dishes.

Credentials (seeded):
- **Email:** `admin@foores.ma`
- **Password:** `admin1234`

Links after setup:
- Public menu: http://localhost:8000/menu.php?r=foores
- Admin: http://localhost:8000/admin/

---

## Folder layout

```
menu-digital/
├── setup.php              one-shot DB bootstrap + seed
├── index.php              redirects to the first restaurant's menu
├── menu.php               PUBLIC digital menu (QR target)
├── shared/
│   ├── theme.css          design tokens extracted from the restaurant site + admin
│   ├── config.php         DB, session, CSRF, helpers
│   ├── layout.php         admin shell (sidebar + topbar, plum/gold)
│   └── layout_foot.php    admin shell closing + toast
├── admin/
│   ├── login.php          auth
│   ├── logout.php
│   ├── index.php          dashboard overview
│   ├── categories.php     manage sections (CRUD + reorder)
│   ├── dishes.php         manage dishes (CRUD + photo upload + availability)
│   ├── qr.php             generate QR (PNG/SVG + print)
│   └── scan.php           scan QR via camera (html5-qrcode)
├── data/                  SQLite DB lives here (git-ignored)
└── uploads/               dish photos (git-ignored)
```

---

## Where the theme came from

Values were extracted from the existing Foores project:

| Token            | Value       | Source                                                         |
|------------------|-------------|----------------------------------------------------------------|
| `--dark`         | `#2A1324`   | `restaurant2/css/theme.css:1019` (public plum rebrand)         |
| `--light`        | `#F2EEE6`   | `restaurant2/css/theme.css:1020`                               |
| `--accent`       | `#883A61`   | `restaurant2/css/theme.css:1021`                               |
| `--gold`         | `#F1AC18`   | `restaurant2/css/theme.css:1024` + `admin/styles.css:6840`     |
| Font             | Plus Jakarta Sans | `restaurant2/css/theme.css:1028` + `admin/styles.css:6835` |
| Sidebar / nav    | plum + gold-active | `restaurant2/admin/styles.css:6852..6863`                |
| Primary button   | gold gradient `135deg (--gold, --amber)` + `--shadow-gold` | `admin/styles.css:6879` |
| Stars            | always gold (`var(--gold)`) | per project memory `gold-stars.md`           |

To tweak the identity for a different brand, edit **`shared/theme.css`**
only — every page re-reads those tokens.

---

## Adding a new restaurant

The schema is already multi-tenant (`restaurants` table with a unique `slug`,
and every category/dish/user is scoped by `restaurant_id`). To add one:

```sql
-- via sqlite3 data/menu.db
INSERT INTO restaurants (slug, name, tagline) VALUES ('mybistro', 'My Bistro', 'Cuisine locale');
INSERT INTO users (restaurant_id, email, password_hash, display_name)
VALUES (
  (SELECT id FROM restaurants WHERE slug = 'mybistro'),
  'owner@mybistro.ma',
  -- bcrypt of 'change-me-1234' — generate via `php -r "echo password_hash('your-pass', PASSWORD_DEFAULT);"`
  '$2y$10$...',
  'Owner'
);
```

Then:

- Public menu URL: `/menu.php?r=mybistro`
- Admin: `/admin/` (log in with that owner's credentials)
- QR page generates a QR pointing to the public URL above

A future enhancement would be a super-admin page to manage restaurants from the UI;
the data model is already ready for it.

---

## Security & production notes

- CSRF tokens on every write form (`shared/config.php` → `csrf_token()` / `csrf_check()`).
- Passwords hashed with `password_hash(..., PASSWORD_DEFAULT)` (bcrypt).
- SQL via PDO prepared statements; `PRAGMA foreign_keys = ON`.
- Image uploads are validated via `getimagesize` + extension allowlist
  (`jpg/png/webp/gif`), capped at 6 MB, and renamed to a random hex token —
  the original filename is never used on disk.
- For production, serve behind HTTPS and set `session.cookie_secure` /
  `session.cookie_samesite=Lax`. The DB file in `data/` should **not** be
  web-accessible — move it outside the webroot or add an `.htaccess` deny.

---

## What was NOT touched

Per instruction, the existing Foores project at `../restaurant2/` was **read
only** to extract the design tokens. Nothing in that folder was modified.
