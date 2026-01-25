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

## CI/CD

GitHub Actions run on pull requests:

- **Tests** - PHPUnit test suite with SQLite and Redis
- **Static Analysis** - PHPStan at level 5

## Deployment

Hosted on [Render](https://render.com) with automatic deployments from the `main` branch.

## License

This project is private and not licensed for public use.
