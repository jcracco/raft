<?php
// RAFT — Range And Forecasting Tool
// Bootstrap file — public web root entry point to private backend
// Copy this file to bootstrap.php and set PRIVATE_PATH to your private-backend/ directory.
// bootstrap.php is gitignored and never committed.

define('PRIVATE_PATH', '/absolute/path/to/your/private-backend/');

require_once PRIVATE_PATH . 'config.php';
require_once PRIVATE_PATH . 'db.php';
require_once PRIVATE_PATH . 'auth.php';
require_once PRIVATE_PATH . 'sprint_calculator.php';
