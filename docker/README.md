# Dockerized Omeka S test environment

This repo now includes a reproducible Omeka S + MySQL stack for manual testing and CI integration tests.

## What it provisions

- Omeka S `v4.2.1`, cloned during the Docker image build
- PHP 8.3 CLI with the Omeka-required extensions
- MySQL 8
- This `Teams` module bind-mounted into `modules/Teams`
- A non-interactive installer that creates a test-only admin account and activates `Teams`

Test-only default admin credentials:

- Email: `admin@example.com`
- Password: `TeamsTestPass123!`

## Start the stack

From the repository root:

```bash
docker compose -f docker/docker-compose.yml up -d --build
```

## Install Omeka S and activate Teams

Run the installer inside the Omeka container:

```bash
docker compose -f docker/docker-compose.yml exec -T omeka bash modules/Teams/docker/install.sh
```

The script is idempotent: you can rerun it safely.

## Open the admin UI

Browse to:

- <http://localhost:8080/admin>

## Run the committed integration test

```bash
docker compose -f docker/docker-compose.yml exec -T omeka php -d xdebug.mode=off modules/Teams/tests/integration/run.php
```

The test boots the real Omeka application, authenticates as the admin user, and verifies:

- `Omeka\Api\Manager::search('team-user', [])` returns every `team_user` row without throwing
- `Teams\Service\SitePermissionManager::syncAllSitePermissions()` creates correct `site_permission` rows for all fixture users

## Tear everything down

```bash
docker compose -f docker/docker-compose.yml down -v
```

## Notes

- The Omeka application code lives in the image at `/var/www/omeka-s`.
- The database is persisted in the named Docker volume `omeka-db` until you run `down -v`.
- The installer writes a local `config/database.ini` and `config/local.config.php` inside the container for this disposable test environment.
- The credentials above are intentionally fake and are for local/CI testing only.
