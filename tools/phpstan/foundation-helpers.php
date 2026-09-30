<?php

// PHPStan scan-only declarations (never autoloaded or executed).
//
// Package service providers call these illuminate/foundation helpers when
// publishing config and migrations. The host Laravel app defines them; the
// packages only require illuminate/* components, so PHPStan needs signatures.

function config_path(string $path = ''): string {}

function database_path(string $path = ''): string {}
