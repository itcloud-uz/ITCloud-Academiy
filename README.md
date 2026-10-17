# ITCloud AI-Powered Coding Bootcamp Platform

Enterprise Highload Coding Bootcamp Platform built on **Laravel 11 (PHP 8.3)**, **Vue 3 (Composition API + Monaco Editor + Pinia)**, **PostgreSQL 16**, and **Redis 7.4**.

## Container Architecture (`itcloud_internal_net`)
- **`web`**: Nginx + PHP 8.3-FPM (OPcache + JIT enabled)
- **`db`**: PostgreSQL 16 (`SELECT ... FOR UPDATE` atomic ledger & AES-256 encrypted PII fields)
- **`cache`**: Redis 7.4 (Cache & Asynchronous Queue Broker)
- **`queue_worker`**: Linux Supervisor managing `ai-evaluations` and `ai-interviews` Redis queues

## Deployment on Ubuntu 24.04 LTS
```bash
cp .env.example .env
docker compose up -d --build
docker compose exec web php artisan migrate --seed --force
```
