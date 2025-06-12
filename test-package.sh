#!/bin/bash

# Script to run package tests
# Usage: ./test-package.sh [filter]

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "Running Padmission Tickets package tests..."
echo "Package directory: $SCRIPT_DIR"

cd "$SCRIPT_DIR"

# Ensure vendor is available
if [ ! -d "vendor" ]; then
    echo "Installing composer dependencies..."
    composer install --no-interaction
fi

if [ -n "$1" ]; then
    echo "Running filtered tests: $1"
    ../../../vendor/bin/pest --filter="$1"
else
    echo "Running all package tests..."
    ./vendor/bin/pest
fi
