# SENI-CNF EDU Backend Laravel

Ce dossier correspond au depot GitHub backend demande dans l'aide-memoire. Il contient l'application Laravel, les routes API, les migrations, les seeders, les paiements, les factures, l'administration et la logique metier.

## Demarrage local

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8016
```

## Variables importantes

- `APP_URL=http://127.0.0.1:8016`
- `FRONTEND_URL=http://localhost:5173`
- `CORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173`
- `PAYMENT_ENV=test` pour le developpement local
- `PAYMENT_DEFAULT_PROVIDER=fake` uniquement en local

## Depot GitHub

```powershell
git init
git add .
git commit -m "Initialisation du backend Laravel"
git branch -M main
git remote add origin https://github.com/<ton-utilisateur>/laravel-backend.git
git push -u origin main
```

## Securite

Ne jamais pousser `.env`, `vendor`, `node_modules`, les caches ou les fichiers de stockage prives. Le fichier `.env.example` est fourni pour documenter la configuration.
