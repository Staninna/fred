# Fred

## Install
- `composer install`
- Copy `.env.example` to `.env` and adjust any values (database path, base URL, etc.).
- Run `./run/migrate` or `php run/migrate.php` if available to initialize the SQLite schema.

## Run
- `php -S localhost:8000 -t public public/index.php` to start the built-in server.
- Visit `http://localhost:8000` in your browser.
- Alternatively, run `php public/index.php` with your preferred web server configuration.

## Tests
- `./vendor/bin/phpunit`
