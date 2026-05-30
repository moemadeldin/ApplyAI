# JobPilot / ApplyAI

> AI-powered job application platform built with Laravel 13

[![CI](https://github.com/moemadeldin/JobPilot/actions/workflows/ci.yml/badge.svg)](https://github.com/moemadeldin/JobPilot/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.5-blue.svg)](https://php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-13-red.svg)](https://laravel.com/)
[![MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Docker](https://img.shields.io/badge/Docker-ready-2496ED.svg)](https://docker.com/)

## Features

- **AI Resume Evaluation** - Analyze resume-to-job compatibility using Groq Llama models
- **AI Cover Letter Generator** - Generate tailored cover letters
- **AI Mock Interview Generator** - Create practice Q&A pairs
- **PDF Text Extraction** - Parse resumes from PDF files
- **API Token Authentication** - Secure Laravel Sanctum auth
- **REST API** - Full CRUD operations
- **Async Queue Jobs** - Background processing via Redis queue
- **Redis Caching** - In-memory cache for API responses and rate limiting
- **CI/CD** - Automated testing and deployment via GitHub Actions
- **Dockerized** - Containerized deployment with Docker Compose

## Tech Stack

- Laravel 13
- PHP 8.5
- PostgreSQL 16
- Redis 7 (queue + cache)
- Nginx
- Laravel Sanctum
- Groq API (Llama 3.3)
- Pest PHP
- Docker

## Installation

### Local Development

```bash
# Install
composer install
npm install

# Setup
cp .env.example .env
php artisan key:generate
php artisan migrate

# Run
composer dev
```

### Docker

```bash
# Start all services
docker compose up -d

# Services: postgres, redis, app (PHP-FPM), queue worker, nginx
```

## CI/CD

On push to `main`:
1. **CI** runs PHPStan static analysis + Pest test suite on GitHub Actions
2. **Deploy** builds the Docker image, pushes to GHCR, and deploys to EC2

## Environment

```env
APP_NAME=ApplyAI
APP_ENV=local

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=applyai
DB_USERNAME=
DB_PASSWORD=

REDIS_HOST=127.0.0.1
CACHE_STORE=redis
QUEUE_CONNECTION=redis

GROQ_API_KEY=your_groq_api_key
```

## API Endpoints

### Public (Rate Limited)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/register` | Create account |
| POST | `/api/v1/login` | User login |
| POST | `/api/v1/forgot-password` | Request password reset |

### Protected (Requires Authentication)

#### Session

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/me` | Get current user |
| DELETE | `/api/v1/logout` | User logout |

#### Profile

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/profile` | Create profile |
| POST | `/api/v1/profile/password` | Change password |
| DELETE | `/api/v1/profile` | Delete account |

#### Custom Job Vacancies

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/custom-vacancies` | List vacancies |
| POST | `/api/v1/custom-vacancies` | Create vacancy |
| GET | `/api/v1/custom-vacancies/{id}` | Get vacancy |
| DELETE | `/api/v1/custom-vacancies/{id}` | Delete vacancy |

#### Custom Applications

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/custom-applications` | List applications |
| GET | `/api/v1/custom-applications/{id}` | Get application |
| GET | `/api/v1/custom-applications/{id}/mock` | Get mock interview |

#### Resumes

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/resumes` | List resumes |
| POST | `/api/v1/resumes` | Upload resume |

#### Password Reset

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/verify-code` | Verify reset code |
| POST | `/api/v1/reset-password` | Reset password |

## Authentication

All protected endpoints require a Bearer token:

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://api.example.com/api/v1/me
```

Get token via login response:

```json
{
  "token": "abc123...",
  "token_type": "Bearer"
}
```

## Testing

```bash
# All tests
composer test

# Type checking
composer test:types

# Coverage
composer test:unit
```

## Commands

```bash
# Create user interactively
php artisan users:create

# Clear expired verification codes
php artisan verification:clear
```

## Project Structure

```
app/
├── Actions/              # Action classes
├── Console/              # Artisan commands
├── DTOs/                 # Data Transfer Objects
├── Enums/                # Enumerations
├── Http/
│   ├── Controllers/      # API controllers
│   ├── Requests/         # Form requests
│   └── Resources/        # API resources
├── Jobs/                 # Queue jobs (async via Redis)
├── Models/               # Eloquent models
├── Queries/              # Query builders
├── Services/             # Business services
└── Traits/               # Shared traits
```

## License

MIT License
