# Auction Platform

A Laravel-based auction platform running with **Laravel Sail** (Docker).

## Prerequisites

Before getting started, ensure you have:

- **Operating System**
  - Linux
  - WSL2 (recommended for Windows)
  - macOS
- **Docker Desktop**
  - Make sure Docker Desktop is installed and running.
- **Windows Users**
  - Install WSL2 with Ubuntu (recommended).

---

# Installation

## 1. Clone the Repository

```bash
git clone https://github.com/KarryOwn/auction-platform.git
cd auction-platform
```

## 2. Install Composer Dependencies

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php82-composer:latest \
    composer install --ignore-platform-reqs
```

## 3. Create the Environment File

```bash
cp .env.example .env
```

## 4. Start Laravel Sail

```bash
./vendor/bin/sail up -d
```

## 5. Generate the Application Key

```bash
./vendor/bin/sail artisan key:generate
```

## 6. Run Database Migrations and Seeders

```bash
./vendor/bin/sail artisan migrate --seed
```

## 7. Install Frontend Dependencies

```bash
./vendor/bin/sail npm install
```

## 8. Build Frontend Assets

```bash
./vendor/bin/sail npm run build
```

---

# Access the Application

| Service | URL |
|---------|-----|
| Application | http://localhost |
| Horizon Dashboard | http://localhost/horizon |
| Mailpit | http://localhost:8025 |

---

# Seeded Accounts

| Role | Email | Password |
|------|-------|----------|
| Buyer | `buyer@buyer.com` | `buyer` |
| Seller | `seller@seller.com` | `seller` |
| Admin | `admin@admin.com` | `admin` |

> Google Login is also available if it has been configured.

---

# Common Laravel Sail Commands

| Action | Command |
|---------|---------|
| Start containers | `./vendor/bin/sail up -d` |
| Stop containers | `./vendor/bin/sail down` |
| Restart containers | `./vendor/bin/sail down && ./vendor/bin/sail up -d` |
| Artisan commands | `./vendor/bin/sail artisan <command>` |
| List routes | `./vendor/bin/sail artisan route:list` |
| Tinker | `./vendor/bin/sail artisan tinker` |
| Run tests | `./vendor/bin/sail test` |
| Composer | `./vendor/bin/sail composer <command>` |
| NPM | `./vendor/bin/sail npm <command>` |

---

# Stress Testing

## Seed Stress Test Bots

Creates test users with an initial balance.

```bash
./vendor/bin/sail artisan stress:seed-bots 100 --balance=1000000
```

Example:

```bash
./vendor/bin/sail artisan stress:seed-bots 500 --balance=5000000
```

---

## Run Stress Test

```bash
./vendor/bin/sail artisan stress:test <auction_id> <number_of_bids> --scenario=single-hot --driver=http
```

Example:

```bash
./vendor/bin/sail artisan stress:test 406 100 --scenario=single-hot --driver=http
```

Where:

- `<auction_id>` – ID of the auction to bid on.
- `<number_of_bids>` – Total number of bids to simulate.

- Docker Desktop must be running before starting Sail.
- The first installation may take several minutes while Docker images are downloaded.
- If using Google Login, ensure the required OAuth credentials are configured in your `.env` file.
