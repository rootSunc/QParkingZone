# Contributing

Thanks for taking the time to improve QParking Zones. This project is a small
full-stack app, so the best contributions are focused, reproducible, and easy
to review.

## Development Setup

Requirements:

- Docker and Docker Compose
- Node.js 20.19+ or 22.12+
- PHP 8.3+ and Composer, or Docker for backend tasks

Install dependencies:

```bash
make install
```

Run the local stack:

```bash
make up
```

Or run each app separately:

```bash
cd apps/api
composer install
composer run db:init
php -S localhost:8000 -t public
```

```bash
cd apps/web
npm ci
cp .env.example .env
npm run dev
```

## Quality Checks

Before opening a pull request, run:

```bash
make ci
make test-e2e
```

Useful focused checks:

```bash
make api-test
make web-test
cd apps/web && npm run lint
cd apps/web && npm run lint:fix
```

## Pull Requests

- Keep changes scoped to one feature, fix, or maintenance task.
- Include tests for API contracts, routing behavior, query parsing, or UI flows.
- Update README or docs when behavior, setup, or deployment changes.
- Avoid unrelated formatting churn.

## Commit Style

Use conventional commit-style summaries when possible:

```text
feat(api): add zone facets endpoint
fix(web): preserve route query on detail links
chore(ci): split lint check and fix scripts
```

