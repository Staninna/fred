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

## Docker

You can run Fred in a container using Docker:

- Ensure `.env` exists (copy `.env.example` to `.env`) before building so configuration is available in the image.
- The default image installs Composer dependencies and runs migrations during build.

### Build the image
```bash
docker build -t fred-app .
```

### Run the container
```bash
docker run -p 8080:80 fred-app

# Optional: persist uploads/logs
docker run -p 8080:80 -v fred_storage:/var/www/html/storage fred-app
```

### Seeded image (optional)
If you want an image with demo data baked in, build with the seed Dockerfile:
```bash
docker build -t fred-app-seeded -f Dockerfile.seed .
docker run -p 8080:80 fred-app-seeded
```

Then visit [http://localhost:8080](http://localhost:8080) in your browser.
