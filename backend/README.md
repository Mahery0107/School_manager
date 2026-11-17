# Backend — API Laravel

API REST de l'application Gestion École (Laravel 13, MySQL `school_manager`).

Installation, configuration, règles de gestion et liste des routes : voir le [README principal](../README.md).

```bash
php artisan serve   # http://127.0.0.1:8000
php artisan test    # tests sur SQLite en mémoire (ne touche pas à MySQL)
```

⚠️ Ne jamais lancer `migrate:fresh` / `db:seed` sur la base réelle.
