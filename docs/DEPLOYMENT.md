# Deploiement production

## 1. Preparer le depot

Versionner le code sans `.env`, `vendor`, `node_modules` ni les fichiers de stockage prives. Creer deux depots GitHub si l'organisation impose une separation stricte: ce dossier Laravel peut servir d'API et le build React est deja integre par Vite. Si un frontend doit etre heberge separement, reutiliser les endpoints `/api` et definir son URL d'API dans une variable d'environnement publique du frontend.

## 2. Variables d'environnement

Configurer les variables dans Render, Railway ou l'hebergeur choisi, jamais dans Git:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.example
APP_KEY=base64:GENEREE_PAR_LARAVEL
LOG_CHANNEL=stack
DB_CONNECTION=mysql
DB_HOST=adresse-de-la-base
DB_PORT=3306
DB_DATABASE=seni_cnf_edu
DB_USERNAME=utilisateur-dedie
DB_PASSWORD=mot-de-passe-fort
QUEUE_CONNECTION=database
FILESYSTEM_DISK=private
PAYMENT_ENV=production
PAYMENT_ENABLED_PROVIDERS=mtn,moov
```

Generer `APP_KEY` avec `php artisan key:generate --show`. Configurer les identifiants MTN/Moov et les secrets de webhook exclusivement dans l'espace de variables securise de l'hebergeur.
Configurer aussi le mail SMTP de production: il sert aux liens de recuperation de mot de passe et aux factures envoyees a l'adresse e-mail de facturation apres confirmation serveur du paiement. Ne pas garder `MAIL_MAILER=log` en production, car ce mode ecrit les e-mails dans les logs sans les remettre a la boite du client.

Variables MTN a renseigner selon le contrat marchand:

```dotenv
MTN_PROD_BASE_URL=
MTN_PROD_API_USER=
MTN_PROD_API_KEY=
MTN_PROD_SUBSCRIPTION_KEY=
MTN_PROD_TARGET_ENVIRONMENT=
MTN_PROD_COLLECTION_VERSION=v1_0
MTN_PROD_CALLBACK_URL=https://votre-domaine.example/webhooks/mtn
MTN_WEBHOOK_SECRET=
```

Variables Moov a renseigner selon le contrat marchand ou l'agregateur choisi:

```dotenv
MOOV_MERCHANT_NUMBER=
MOOV_PROD_BASE_URL=
MOOV_PROD_INITIATE_PATH=
MOOV_PROD_VERIFY_PATH=
MOOV_PROD_CLIENT_ID=
MOOV_PROD_CLIENT_SECRET=
MOOV_PROD_API_KEY=
MOOV_PROD_CALLBACK_URL=https://votre-domaine.example/webhooks/moov
MOOV_WEBHOOK_SECRET=
```

Ne jamais configurer de PIN, OTP permanent ou secret dans Git. Les numeros saisis par les clients servent uniquement a initier la demande de paiement.

## 3. Construction et lancement

Les commandes de construction sont:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Le serveur web doit exposer le dossier `public/`, utiliser HTTPS, et demarrer le worker de queue avec `php artisan queue:work --tries=3 --timeout=90`. Les taches planifiees doivent executer `php artisan schedule:run` chaque minute. Cette planification lance `payments:verify-pending` pour confirmer automatiquement les paiements MTN/Moov qui n'ont pas encore envoye de webhook. Elle lance aussi `invoices:retry` toutes les cinq minutes pour retenter les factures dont la connexion SMTP etait indisponible.

## 4. Verification apres publication

1. Visiter `/up`, `/application` et le catalogue.
2. Creer un compte non administrateur puis verifier qu'il ne peut pas ajouter un livre.
3. Creer un paiement sandbox avec le fournisseur concerne, puis verifier que seul le webhook valide ou la verification serveur donne acces au fichier.
4. Verifier que le fichier stocke sur le disque prive ne peut pas etre ouvert par URL directe.
5. Verifier qu'un lien de recuperation de mot de passe peut etre envoye, qu'une facture est consultable/imprimable et qu'un e-mail de facture part via le mailer configure.
6. Verifier qu'un emprunt paye donne acces au document pendant 30 jours, puis bloque l'acces apres expiration.
7. Acheter un livre numerique au prix reel, verifier la facture, puis confirmer que le telechargement protege fonctionne uniquement pour l'acheteur.
8. Retourner un emprunt, puis verifier que l'utilisateur peut le retirer de sa liste sans supprimer l'historique cote administration.
9. Configurer les sauvegardes quotidiennes MySQL, la retention des logs et une surveillance des erreurs.

## 5. Avant d'accepter de vrais paiements

Desactiver `fake`, definir et tester les secrets de webhook de production, verifier une petite transaction reelle, mettre a jour les pages legales et ne publier que du contenu dont les droits de diffusion sont documentes.
