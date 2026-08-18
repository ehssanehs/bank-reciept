# Frontend (customer web + admin panel)

The web application is delivered as **Laravel Blade views** (server-rendered) located in
`backend/resources/views/`. This keeps deployment simple: no separate Node runtime is
required to run the application.

This directory holds the **optional** asset pipeline for teams that want to develop the
UI with Vite + Tailwind.

## Build

```bash
cd frontend
npm install
npm run build   # compiles to backend/public/css/app.css + backend/public/js/app.js
```

The compiled `backend/public/css/app.css` is already committed, so the app runs without
running the build.

## RTL / LTR

The `<html>` element receives `dir="rtl"` or `dir="ltr"` and `lang="fa"|"en"` from the
Blade layout based on the active locale (`layouts/web.blade.php`,
`layouts/admin.blade.php`). All layouts, forms, and tables flow correctly for both.

## Language switch

`GET /locale/{locale}` persists the choice in the session and on the user profile.
