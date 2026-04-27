# AURUM — Deployment Guide

## Folder Structure After Extraction

```
/your-server-root/
├── backend/          ← PHP backend (place outside public HTML if possible)
│   ├── index.php
│   ├── .env          ← created from .env.example (never committed)
│   ├── .htaccess
│   ├── config/
│   ├── controllers/
│   ├── middleware/
│   ├── routes/
│   └── utils/
└── frontend/         ← Static HTML/CSS/JS
    ├── config.js     ← EDIT THIS: set API_BASE to your backend URL
    ├── index.html
    ├── auth.html
    ├── owner.html
    ├── owner-dashboard.html
    ├── app.js
    ├── auth.js
    ├── owner.js
    └── owner-dashboard.js
```

---

## Step 1 — Create the database

```sql
mysql -u root -p < backend/database.sql
```

---

## Step 2 — Configure the backend

```bash
cp backend/.env.example backend/.env
```

Edit `backend/.env`:
```
DB_HOST=localhost
DB_NAME=hotel_management
DB_USER=your_db_user
DB_PASS=your_db_password

# Generate a real secret: openssl rand -hex 32
JWT_SECRET=REPLACE_WITH_64_CHAR_RANDOM_STRING

# Get a free key at: https://console.groq.com
GROQ_API_KEY=gsk_...

FRONTEND_URL=https://yourdomain.com
```

---

## Step 3 — Set API_BASE in the frontend

Edit `frontend/config.js`:

```js
// Same server, Apache/Nginx proxying /backend → backend/index.php
const API_BASE = '/backend';

// OR: separate server (GitHub Pages frontend + hosted backend)
const API_BASE = 'https://api.yourdomain.com';
```

---

## Step 4a — Apache (shared hosting / XAMPP)

1. Place `backend/` inside `htdocs/` or `public_html/`
2. Place `frontend/` in the same root
3. The `backend/.htaccess` handles routing automatically
4. Visit `http://localhost/frontend/index.html`

**XAMPP local path example:**
```
C:/xampp/htdocs/aurum/backend/    → http://localhost/aurum/backend/
C:/xampp/htdocs/aurum/frontend/   → http://localhost/aurum/frontend/
```
Set `API_BASE = '/aurum/backend'` in `config.js`.

---

## Step 4b — Nginx + PHP-FPM

Copy `backend/nginx.conf` to `/etc/nginx/sites-available/aurum` and update paths, then:
```bash
nginx -t && systemctl reload nginx
```

---

## Step 4c — GitHub Pages (frontend only)

GitHub Pages serves only static files — the PHP backend must be hosted separately.

1. Host backend on any PHP server (DigitalOcean, InfinityFree, etc.)
2. In `frontend/config.js` set:
   ```js
   const API_BASE = 'https://your-backend-server.com/backend';
   ```
3. In `backend/.env` set:
   ```
   FRONTEND_URL=*
   ```
   (allows CORS from any origin, including GitHub Pages)
4. Push only the `frontend/` contents to your GitHub Pages repo

---

## Demo Credentials (seeded automatically)

| Role        | Email                | Password    |
|-------------|----------------------|-------------|
| Guest       | guest@aurum.com      | guest123    |
| Hotel Owner | owner@aurum.com      | owner123    |
| Admin       | (username) superadmin| admin123    |

**Change all passwords before going to production.**

---

## Security Checklist Before Going Live

- [ ] Set `JWT_SECRET` to a random 64-char string
- [ ] Set `GROQ_API_KEY` to your real key
- [ ] Change demo user passwords
- [ ] Set `DB_PASS` to a strong password
- [ ] Set `FRONTEND_URL` to your actual domain (not `*`)
- [ ] Enable HTTPS (Let's Encrypt)
- [ ] Set `APP_DEBUG` to `false` (or remove it)
- [ ] Remove `database.sql` from the web root
