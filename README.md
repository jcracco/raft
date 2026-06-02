# RAFT — Range And Forecasting Tool

A self-hosted initiative forecasting tool for project managers. Built on the good weather / current weather / bad weather methodology — a three-point estimation approach that gives realistic delivery ranges rather than single-point guesses.

**[Live Demo →](https://demo.cracco.ch/raft?src=github)** — demo / demo

---

## Why I Built This

At Payscale I developed a forecasting framework using team velocity and focus scenarios to predict initiative delivery dates. Stakeholders got a range — not a promise — which gave them realistic expectations for renewals and planning. The original version was a Confluence document and an Excel file.

RAFT is that framework as a proper web app: multi-project, multi-user, sprint-by-sprint tracking that tightens the delivery range as work progresses.

---

## How It Works

Every forecast runs three scenarios based on what percentage of team capacity goes toward the initiative each sprint:

| Scenario | Focus | Interpretation |
|---|---|---|
| Bad weather | 35% | Interruptions, bugs, unplanned work |
| Current weather | variable | Your team's actual recent average |
| Good weather | 75% | Clean sprint, minimal distraction |

As you complete sprints and enter actual numbers, the current weather % auto-calibrates and the delivery range narrows. The burndown chart shows actual vs projected across all three scenarios.

---

## Features

- **Multi-project** — track concurrent initiatives across multiple teams
- **Three-scenario forecast** — bad / current / good weather delivery ranges
- **Sprint completion flow** — complete a sprint, enter actuals, chart and forecast update instantly
- **Auto-calibration** — velocity and focus % can be set manually or calculated from sprint history
- **Scope creep simulator** — add points temporarily to see delivery impact without saving
- **Sprint date calculation** — enter cadence start date and sprint duration, all dates auto-generate
- **Flexible sprint naming** — prefix + year (yyyy/yy) + number (01/1), starting from any sprint
- **Demo mode** — same codebase, in-memory data, resets on tab close

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | React 18 (CDN + Babel standalone, no build step) |
| Backend | PHP 8, PDO |
| Database | MySQL / MariaDB |
| Hosting | Shared hosting (Plesk) |
| Auth | bcrypt password hash, file-based session tokens, 30-day cookie |
| Charts | Chart.js 4 |

No npm, no webpack, no framework — intentional. Runs on any shared PHP host with zero build tooling.

---

## Project Structure

```
RAFT/
├── public-frontend/               # Web root
│   ├── index.php                  # Single page app shell, demo detection
│   ├── api.php                    # REST-ish API (all CRUD)
│   ├── admin.php                  # Hidden user management page
│   ├── logout.php                 # Token invalidation + redirect
│   ├── mock-api.js                # Demo mode: in-memory data, API override
│   └── bootstrap.example.php     # Copy to bootstrap.php, set PRIVATE_PATH
│
├── private-backend/               # Above web root (never web-accessible)
│   ├── config.example.php         # Configuration template → copy to config.php
│   ├── db.php                     # PDO connection singleton
│   ├── auth.php                   # Login, logout, token management
│   ├── sprint_calculator.php      # Sprint naming, date math, forecast logic
│   └── setup.sql                  # Database schema
│
├── .gitignore
├── LICENSE
└── README.md
```

---

## Setup (self-hosting)

> The demo at the link above requires no setup.

**1. Database**

Create a database and user, then run `private-backend/setup.sql` in phpMyAdmin.

```sql
CREATE DATABASE raft_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'raft_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON raft_db.* TO 'raft_user'@'localhost';
```

**2. Configuration**

Copy `private-backend/config.example.php` to `config.php` in the same folder and fill in your values — DB credentials, demo domain, admin token, session name.

**3. Bootstrap**

Copy `public-frontend/bootstrap.example.php` to `bootstrap.php` and set `PRIVATE_PATH` to the absolute path of your `private-backend/` directory.

**4. First user**

Visit `/raft/admin.php?token=YOUR_ADMIN_TOKEN` to create your first user. The admin token is set in `config.php`.

To generate a bcrypt hash manually:
```php
<?php echo password_hash('your_password', PASSWORD_DEFAULT);
```

**5. Deploy**

- Upload `private-backend/` above your web root
- Upload `public-frontend/` contents to your web root (e.g. `/raft/`)
- Confirm `bootstrap.php` points to the correct `PRIVATE_PATH`

---

## Demo Mode

Same codebase, hostname-based detection:

```php
$IS_DEMO = strpos($_SERVER['HTTP_HOST'], IS_DEMO_DOMAIN) !== false;
```

When demo mode is active:
- `mock-api.js` loads and overrides all API calls
- Data is served from an in-memory JavaScript dataset
- Changes persist in `sessionStorage` — survive refresh, reset on tab close
- An amber banner identifies the demo
- Login with `demo / demo`

No separate codebase. Deploy the same files to your demo subdomain with its own `config.php`.

---

## Background

Built during an active job search following a November 2025 layoff from Payscale. The forecasting methodology behind RAFT was developed in real sprint planning work — it gave engineering leadership and stakeholders reliable delivery ranges for a major multi-quarter initiative.

RAFT is the second tool in this series. The first is [CATS](https://github.com/jeromecracco/cats) — Candidate Application Tracking System, built to replace a 470-row Google Sheet tracking the same job search.

---

## Author

**Jerome Cracco** — Technical Program Manager / Senior Product Owner  
[LinkedIn](https://www.linkedin.com/in/jeromecracco/) · Boston, MA
