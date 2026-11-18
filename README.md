# Lumen — a small blog application

HTML/CSS/JS frontend, PHP backend, MySQL database. Register, log in, write
Markdown posts, edit or delete only your own — with a live preview editor,
a reading-progress bar, toasts, and a mobile-responsive layout in a
sunrise-to-teal color theme. Posts support drafts, tags, cover images, stable
URLs, author profiles, and paginated browsing.

## 1. What's in here

```
lumen-blog/
├── config/db.php          # database credentials — edit this first
├── sql/schema.sql          # fresh database schema
├── sql/migrate_features.sql # one-time upgrade for existing posts/tags
├── sql/migrate_social_features.sql # one-time upgrade for comments/social features
├── includes/
│   ├── session.php         # login state, flash messages, CSRF tokens
│   ├── functions.php       # shared lists, slugs, tags, URLs, escaping
│   ├── uploads.php         # validated image processing and storage
│   ├── markdown.php        # Markdown → HTML converter (server-side)
│   ├── header.php          # shared <head>, nav bar
│   └── footer.php          # shared footer, script includes
├── api/                    # JSON like, bookmark, follow, and report endpoints
├── css/style.css           # all styling
├── js/
│   ├── main.js              # nav toggle, toast notifications, card reveal
│   ├── editor.js            # live Markdown preview, word count, autosave
│   └── post-view.js         # delete-confirm modal, reading-progress bar
├── bookmarks.php            # Signed-in user's saved published posts
├── feed.php                 # Signed-in following feed
├── feed.xml.php             # RSS 2.0 feed (also /rss.xml)
├── index.php                # Home — published posts, search, pagination
├── tag.php                  # Published posts for a tag
├── profile.php              # Public author profile and posts
├── settings.php             # Edit the signed-in user's bio and avatar
├── drafts.php               # Signed-in user's drafts
├── view.php                 # Single post view
├── comment-delete.php       # Comment delete handler (POST + owner check)
├── register.php / login.php / logout.php
├── editor.php               # Create AND edit a post (same file)
└── delete.php                # Delete handler (POST only, owner only)
```

Each page above is a separate file — open any one directly to see its code.

## 2. Local setup (test it on your own machine first)

1. Install **XAMPP** (Windows/Mac) or **MAMP** (Mac), or use PHP + MySQL
   directly if you already have them. You need PHP 8+, MySQL/MariaDB, and
   the PHP GD extension for image uploads.
2. Copy the `lumen-blog` folder into your server's web root
   (e.g. `htdocs/lumen-blog` for XAMPP).
3. Start Apache and MySQL from your control panel.
4. Open **phpMyAdmin**, create a database named `lumen_blog`, select it,
   then click **Import**, choose `sql/schema.sql`, and run it. This creates all
   tables in the selected database. For an existing Lumen database, import
   `sql/migrate_features.sql` and then `sql/migrate_social_features.sql` once.
   The first migration preserves old `updated_at` values while initializing
   `published_at`.
5. Open `config/db.php` and set `DB_USER` / `DB_PASS` to match your local
   MySQL (XAMPP default is usually `root` with an empty password — already
   set for you).
6. Visit `http://localhost/lumen-blog/index.php`. Click **Sign up**,
   create an account, then **+ New post** to publish something.

If you see "Database connection failed," it's almost always
`config/db.php` — double check the host/user/password/database name.

## 3. How the pieces fit together

- **Auth** — `register.php` and `login.php` hash/verify passwords with
  PHP's `password_hash()`/`password_verify()` (bcrypt) and store the
  session in `$_SESSION['user_id']`. Every form includes a CSRF token
  checked by `csrf_valid()`.
- **Authorization** — `editor.php` and `delete.php` both re-check
  `$post['user_id'] === current_user_id()` server-side before allowing an
  edit or delete, even though the UI already hides those buttons from
  non-owners. Never trust the frontend alone for this.
- **Markdown** — posts are stored as raw Markdown in `blogPost.content`.
  `includes/markdown.php` converts it to HTML on the server for the
  public view page (raw HTML is escaped, and link/image URLs are restricted
  to safe schemes and relative references). `js/editor.js` applies the same
  URL restrictions in the live preview while writing.
- **Slugs** — `make_slug()` in `includes/functions.php` creates Unicode-aware
  slugs and protects historical links. Renamed published posts keep old slugs
  in `slug_history`, which redirect to the current URL.
- **Visibility** — Home, search, tag, and profile pages only list published
  posts. Authors can preview their drafts and manage them from **My drafts**.
- **Uploads** — covers and avatars are decoded and re-encoded with GD, then
  stored with random names beneath `uploads/`. The web server should honor
  `uploads/.htaccess` so scripts cannot execute in upload directories.
- **Community** — comments, likes, bookmarks, author follows, and a following
  feed are available to signed-in users. Configure `SITE_URL` in
  `config/db.php` to the canonical absolute site URL for RSS links.

## 4. Step-by-step: hosting it online (InfinityFree example)

The same steps apply to 000WebHost or any host that gives you PHP +
MySQL — only the exact screen names differ slightly.

1. **Create a free account** at infinityfree.net (or your chosen host)
   and create a new hosting account — you'll get a free subdomain like
   `yourname.infinityfreeapp.com`.
2. **Create a MySQL database** from the control panel (usually under
   "MySQL Databases"). Note down the **host**, **database name**,
   **username**, and **password** it gives you — the host is often
   *not* `localhost` on free hosts (e.g. `sql200.infinityfree.com`).
3. **Import the schema.** Open **phpMyAdmin** from the control panel,
   select your new database, go to **Import**, and upload
   `sql/schema.sql`. The schema only creates tables in the selected
   database, so it does not require permission to create or select a
   database.
4. **Edit `config/db.php`** with the real host/database/username/password
   from step 2.
5. **Upload the files.** Use the control panel's File Manager or an FTP
   client (FileZilla) to upload everything *inside* `lumen-blog/` into
   your `htdocs/` (or `public_html/`) folder — not the `lumen-blog`
   folder itself, its contents.
6. **Visit your site**: `http://yourname.infinityfreeapp.com/index.php`.
   Register an account and publish a post to confirm writes work too,
   not just reads.
7. **Double-check permissions** if something fails: PHP files usually
   need `644`, folders `755`. Most hosts set this automatically on
   upload.

That's it — the hosted version uses the exact same files as your local
one, so anything that works on `localhost` will work once uploaded.

## 5. Extending it further (optional ideas)

- Add comments, post moderation, or scheduled publishing.
- Add database backups and a routine for cleaning up unused tag records.
- Swap the hand-written Markdown parser for a library like Parsedown if
  you want full CommonMark support (tables, footnotes, etc.).

# Lumen-Blog
A php based blogging application
