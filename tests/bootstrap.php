<?php
// Minimal bootstrap for package test isolation
// This file is intentionally lightweight for package-only PHPUnit runs.

// If you need to load helpers, Composer autoload, or env, do it here:
require_once __DIR__ . '/../vendor/autoload.php';

// Optionally set up env vars or constants for package tests
// putenv('APP_ENV=testing');
