<?php
// RAFT — Range And Forecasting Tool
// Configuration file
// Copy this file to config.php and fill in your values.
// config.php is gitignored and never committed.

// Database
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'raft_db');
define('DB_USER', 'raft_user');
define('DB_PASS', 'your_password_here');

// Demo mode detection
// The app checks if the current hostname contains this string.
// Set to your demo subdomain
define('IS_DEMO_DOMAIN', 'demo.cracco.ch');

// ── Analytics ─────────────────────────────────────────────────────────────────
define('UMAMI_ENABLED', false);                    // ← set to true to enable
define('UMAMI_WEBSITE_ID', 'your-website-id');     // ← replace with your Umami ID

// Admin page token
// The hidden admin page is at /admin.php?token=THIS_VALUE
// Change this to something hard to guess.
define('ADMIN_TOKEN', 'change_this_to_a_random_string');

// Session name (change if running multiple apps on same domain)
define('SESSION_NAME', 'raft_session');

// Session lifetime in seconds (default: 30 days)
define('SESSION_LIFETIME', 60 * 60 * 24 * 30);
