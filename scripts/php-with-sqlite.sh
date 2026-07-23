#!/usr/bin/env bash
# Loads project-local SQLite PHP extensions when the system package is unavailable.
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR:+$PHP_INI_SCAN_DIR:}$ROOT/.php-ext/conf.d"
exec php "$@"
