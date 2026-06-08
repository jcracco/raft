# Changelog

## v2.0.0 — In Progress
- Add url_token to projects table (breaking schema change — run migration)
- Hash routing updated to use url_token instead of database ID
- Read-only view for unauthenticated users and non-owners
- Sign in button in nav for unauthenticated project views

## v1.0.0 — 2026-06-08
- Initial release
- Multi-project forecasting with bad/current/good weather scenarios
- Sprint completion flow with burndown chart
- Demo mode with sessionStorage persistence
- Light/dark theme
- Admin user management
- Quick Plan stateless calculator card