# Personal Site Backend

A content management backend for my personal website, built with Laravel and Filament.

## Overview

This application serves as a headless CMS, providing API endpoints and an admin panel to manage content across my personal sites. It handles blog posts, and will expand to support additional content types as needed.

## Tech Stack

- **Framework:** Laravel 12
- **Admin Panel:** Filament 5
- **Database:** PostgreSQL 18
- **PHP:** 8.4
- **Containerization:** Docker

## Development

### Prerequisites

- Docker and Docker Compose

### Getting Started

```bash
# Clone the repository
git clone https://github.com/Nicholasbell03/personal-site.git
cd personal-site

# Copy environment file
cp .env.example .env

# Start containers
docker compose up -d

# Install dependencies and set up the application
docker exec laravel_app composer install
docker exec laravel_app php artisan key:generate
docker exec laravel_app php artisan migrate
```

The application will be available at `http://localhost:8080`.

### Running Commands

All PHP/Artisan commands should be run inside the Docker container:

```bash
docker exec laravel_app php artisan <command>
docker exec laravel_app composer <command>
```

### Code Quality

```bash
# Run tests
docker exec laravel_app php artisan test

# Static analysis
docker exec laravel_app vendor/bin/phpstan analyse

# Code formatting
docker exec laravel_app vendor/bin/pint
```

## API

The full API reference is generated from the code by [Scramble](https://scramble.dedoc.co):

- **Docs UI:** `/docs/api` (sends you to the admin login if you are not signed in)
- **OpenAPI spec:** `/docs/api.json` (send `Authorization: Bearer $TOKEN`)

Both are private. They need a Filament login session or a Sanctum token.

### Agent workflow

Agents can draft blog posts with images without anyone touching the admin panel. Create a token on the **API Tokens** page in the admin panel, or with `php artisan app:generate-api-token`.

These endpoints cannot publish anything. New posts are always drafts, only drafts can be edited (published posts return `409`), and publishing is done by a person in Filament.

1. **Upload each image.** png, jpg/jpeg, webp, avif or gif, up to 10 MB. SVG is rejected.

   ```bash
   curl -F file=@screenshot.png \
     -H "Accept: application/json" \
     -H "Authorization: Bearer $TOKEN" \
     https://api.nickbell.dev/api/v1/media
   # {"data": {"path": "blog-images/01K....png", "url": "https://assets.nickbell.dev/blog-images/01K....png"}}
   ```

2. **Create the draft.** Embed each image's `url` in the HTML, and pass one `path` or `url` as `featured_image`.

   ```bash
   curl -X POST https://api.nickbell.dev/api/v1/blogs \
     -H "Accept: application/json" \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer $TOKEN" \
     -d '{
       "title": "Shipping an agent API",
       "content": "<p>Intro</p><img src=\"https://assets.nickbell.dev/blog-images/01K....png\" alt=\"Screenshot\">",
       "featured_image": "blog-images/01K....png"
     }'
   ```

3. **Revise it.** Send only the fields to change. `"featured_image": null` removes the image.

   ```bash
   curl -X PATCH https://api.nickbell.dev/api/v1/blogs/42 \
     -H "Accept: application/json" \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer $TOKEN" \
     -d '{"title": "Shipping a small agent API"}'
   ```

Create and update both return the blog as `data` plus an `admin_url` link to it in Filament.

## CI/CD

GitHub Actions run on pull requests:

- **Tests** - PHPUnit test suite with SQLite and Redis
- **Static Analysis** - PHPStan at level 5

## Deployment

Hosted on [Render](https://render.com) with automatic deployments from the `main` branch.

## License

This project is private and not licensed for public use.
