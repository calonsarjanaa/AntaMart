# AntaMart Transaction & Fulfillment API

Laravel 10 API for checkout, inventory reservations, Xendit payments, cancellations, refunds, shipment tracking, guest order lookup, and audit history.

## Requirements

- PHP 8.1 or later with the extensions required by Laravel 10
- Composer
- MySQL (the migrations use MySQL-compatible schema features)
- A running database queue worker for delayed payment expiry jobs
- Xendit credentials for real payment sessions and webhook callbacks

## Local setup

From this directory (`app/`):

```sh
composer install
cp .env.example .env
php artisan key:generate
```

Set the database connection and Xendit settings in `.env`:

```dotenv
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=antamart
DB_USERNAME=root
DB_PASSWORD=
QUEUE_CONNECTION=database
XENDIT_SECRET_KEY=
XENDIT_WEBHOOK_TOKEN=
XENDIT_BASE_URL=https://api.xendit.co
```

Then create the schema and start the app and queue worker in separate terminals:

```sh
php artisan migrate
php artisan db:seed
```

Start the app and queue worker in separate terminals:

```sh
php artisan serve
```

```sh
php artisan queue:work database
```

The default seeder creates synthetic catalog items for local UI development. It uses `firstOrCreate` for variants so rerunning it does not reset existing variant stock.

The API is served below `/api`. The queue worker is needed for delayed order-expiry jobs. For a local callback, configure the Xendit dashboard or a webhook forwarder to send callbacks to `POST /api/webhooks/xendit` and set the same callback token in `XENDIT_WEBHOOK_TOKEN`.

## API reference

- [OpenAPI 3.0 specification](../docs/openapi.yaml)
- [Postman collection](../docs/AntaMart.postman_collection.json)
- [Known limitations and operational notes](../docs/KNOWN_LIMITATIONS.md)
- [Implementation roadmap](../TECHNICAL_PLAN.md)
- [Overall UI implementation plan](../UI_IMPLEMENTATION_PLAN.md)

All API requests and responses use JSON. Store-owner routes require a Sanctum-authenticated user with the `store_owner` role. The customer authentication flow is outside this MVP; use an existing Sanctum token for authenticated routes.

## Tests

Run the feature and integration suite from `app/`:

```sh
php artisan test
```
