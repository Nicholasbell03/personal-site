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

## Agent API

Agents can draft blog posts with images without anyone touching the admin panel. Every endpoint below needs a Sanctum token (create one on the **API Tokens** page in the admin panel, or with `php artisan app:generate-api-token`), sent as `Authorization: Bearer $TOKEN`.

These endpoints cannot publish anything. New posts are always drafts, only drafts can be edited, and publishing is done by a person in Filament.

A typical flow: upload each image, put the returned `url` in the post HTML, create the draft with one image as `featured_image`, then `PATCH` it to revise.

### POST /api/v1/media

**Route name:** `v1.media.store`
**Middleware:** `api`, `auth:sanctum`

Uploads an image to the `blog-images/` directory on the same disk as the Filament featured image upload (R2 in production). The file gets a ULID filename and public visibility.

| Parameter | Type | Rules |
|-----------|------|-------|
| file | file (multipart/form-data) | required; png, jpg/jpeg, webp, avif or gif (checked from the file contents, so SVG is rejected); max 10 MB |

```bash
curl -F file=@screenshot.png \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  https://api.nickbell.dev/api/v1/media
```

**Response `201`**

```json
{
  "data": {
    "path": "blog-images/01K6A1B2C3D4E5F6G7H8J9K0MN.png",
    "url": "https://assets.nickbell.dev/blog-images/01K6A1B2C3D4E5F6G7H8J9K0MN.png"
  }
}
```

Embed `url` in post content (`<img src="...">`). Pass `path` or `url` as `featured_image`.

### POST /api/v1/blogs

**Route name:** `v1.blogs.store`
**Middleware:** `api`, `auth:sanctum`

Creates a draft. Any `status` field is ignored.

| Parameter | Type | Rules |
|-----------|------|-------|
| title | string | required, max 255 |
| content | string (HTML) | required |
| slug | string | optional, max 255, unique; generated from the title if omitted |
| excerpt | string | optional, max 230 |
| meta_description | string | optional, max 255 |
| featured_image | string | optional; a `path` or `url` returned by `POST /api/v1/media` |

```bash
curl -X POST https://api.nickbell.dev/api/v1/blogs \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "title": "Shipping an agent API",
    "content": "<p>Intro</p><img src=\"https://assets.nickbell.dev/blog-images/01K6A1B2C3D4E5F6G7H8J9K0MN.png\" alt=\"Screenshot\">",
    "featured_image": "blog-images/01K6A1B2C3D4E5F6G7H8J9K0MN.png"
  }'
```

**Response `201` (BlogResource + admin link)**

```json
{
  "data": {
    "id": 42,
    "title": "Shipping an agent API",
    "slug": "shipping-an-agent-api",
    "excerpt": null,
    "content": "<p>Intro</p><img src=\"...\" alt=\"Screenshot\">",
    "featured_image": "https://assets.nickbell.dev/blog-images/01K6A1B2C3D4E5F6G7H8J9K0MN.png",
    "meta_description": null,
    "published_at": null,
    "read_time": 1
  },
  "admin_url": "https://api.nickbell.dev/admin/blogs/42/edit"
}
```

### PATCH /api/v1/blogs/{id}

**Route name:** `v1.blogs.update`
**Middleware:** `api`, `auth:sanctum`

Partially updates a draft. Only the fields you send are changed. Status and publish date can't be changed here.

| Parameter | Type | Rules |
|-----------|------|-------|
| title | string | optional, max 255 |
| content | string (HTML) | optional |
| slug | string | optional, max 255, unique (the post's own slug is allowed) |
| excerpt | string \| null | optional, max 230 |
| meta_description | string \| null | optional, max 255 |
| featured_image | string \| null | optional; a `path` or `url` from `POST /api/v1/media`, or `null` to remove it |

```bash
curl -X PATCH https://api.nickbell.dev/api/v1/blogs/42 \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"title": "Shipping a small agent API", "excerpt": "Uploads, drafts and revisions."}'
```

**Response `200`:** same shape as `POST /api/v1/blogs`.

**Errors**

| Status | When |
|--------|------|
| `401` | Missing or invalid token |
| `404` | No blog with that id |
| `409` | The blog is published: `{"message": "Only draft blogs can be updated through the API."}` |
| `422` | Validation failed, e.g. slug taken or `featured_image` not uploaded through `/media` |

## CI/CD

GitHub Actions run on pull requests:

- **Tests** - PHPUnit test suite with SQLite and Redis
- **Static Analysis** - PHPStan at level 5

## Deployment

Hosted on [Render](https://render.com) with automatic deployments from the `main` branch.

## License

This project is private and not licensed for public use.
