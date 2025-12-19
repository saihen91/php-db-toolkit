![PHP CI](https://github.com/saihen91/php-db-toolkit/actions/workflows/ci.yml/badge.svg)

# PHP DB Toolkit

Lightweight PDO toolkit for MySQL: prepared statements, CRUD helpers, transactions, and pagination.

## Features
- PDO wrapper with safe defaults
- `selectAll()`, `selectOne()`, `value()`
- `insert()`, `update()`, `delete()` helpers
- Transaction helper: `transaction(fn() => ...)`
- Pagination helper (LIMIT/OFFSET)
- Debug: last query, params, execution time

## Requirements
- PHP 8.1+
- PDO extension + pdo_mysql

## Install (local)
```bash
composer install
