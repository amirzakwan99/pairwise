# Pairwise backend

Laravel 12 REST API with Sanctum stateful SPA sessions, MySQL migrations, authorization policies, transactional expense services and integer-cent pairwise settlement calculation.

See the [root README](../README.md) for installation, environment settings, development commands, tests and deployment. Install dependencies using `composer install` against `composer.lock`.

```sh
php artisan migrate
php artisan db:seed          # Development only
php artisan serve --host=127.0.0.1 --port=8000
php artisan test
php vendor/laravel/pint/builds/pint --test
```
