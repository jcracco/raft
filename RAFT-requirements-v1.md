# RAFT — Range And Forecasting Tool
## Requirements v2
**Author**: Jerome Cracco  
**Last Updated**: June 2026  
**Stack**: PHP + MariaDB (hosted on cracco.ch)

---

## 1. Overview

RAFT (Range And Forecasting Tool) is a web application for project managers to forecast when a team will complete a large initiative. Based on remaining story points, team velocity, and three focus scenarios (bad weather / current weather / good weather). Inspired by three-point estimation (PERT).

Users can track multiple concurrent initiatives. Each sprint completion updates the forecast, tightening the delivery range over time.

---

## 2. Authentication & User Management

- Login required to access any functionality
- First admin user created manually in the database
- Admin accesses a hidden (unlisted) page to create additional users (username + password)
- Self-registration: deferred to a future version
- Demo mode available at a specific URL (see section 8)

---

## 3. Project List Page

After login, the user sees a list of their projects. Each row shows:
- Project name
- Team name (if provided)
- Current sprint
- Initiative points remaining (current weather)

Actions per row:
- Open project
- Delete project (requires typing DELETE in a confirmation modal)

Button to create a new project.

---

## 4. Creating a Project

A modal collects the following:

### 4.1 Required Fields
- **Project name**
- **Total pointed story points** (stories already estimated)
- **Average team velocity** (story points per sprint)

### 4.2 Optional Fields
- Initiative name + link (e.g. JIRA epic URL)
- Team name + link (e.g. JIRA backlog or Confluence page)

### 4.3 Sprint Naming & Dates

The user configures how sprints are named and optionally anchors them to real calendar dates.

**Two independent inputs:**

**A) Team's sprint cadence start date**  
The date the team's very first sprint started (e.g. Monday January 5, 2026). This is used as the anchor to calculate all sprint numbers and dates across the entire team history. From this date + sprint duration, the app knows that June 1, 2026 falls in sprint 2026.12, for example.

**B) Initiative start sprint**  
The sprint number when the team begins working on this initiative (e.g. 2026.12). This does not have to be sprint 1 — the team may have been running for months before this initiative started.

**Sprint name format options:**
- Include year: yes / no
- Year format (if included): `yy` or `yyyy`
- Sprint number format: `x` (no leading zero) or `xx` (leading zero)

**This produces four possible formats (examples with prefix "Sprint"):**
- `Sprint 7` — no year, no leading zero
- `Sprint 07` — no year, with leading zero
- `Sprint 2026.7` — year yyyy, no leading zero
- `Sprint 2026.07` — year yyyy, with leading zero
- (same variants with `yy`)

**Prefix:** User can set a custom prefix. Default: `Sprint`. Example: `Team A` produces `Team A 2026.07`.

**Year rollover:** When using year + increment, the increment resets to 01 when the year changes. The app auto-calculates this based on the cadence start date and sprint duration.

**Sprint duration & dates (optional):**
- Sprint duration in weeks (e.g. 2)
- Team cadence start date (exact date, e.g. Monday January 5, 2026)

If both are provided, the app auto-calculates start and end dates for all sprints (past and future) and displays them alongside sprint names throughout the app.

### 4.4 Points Settings
- **Estimated additional points**: points for stories not yet estimated. Example: 70 points have been estimated; the remaining stories are expected to add ~60 more. This is separate from scope creep — it represents known but unpointed work. Null if not provided.
- **Buffer %**: safety margin applied to total points. Default 25% if not provided.

**Total points calculation**: `(pointed SP + estimated additional SP) × (1 + buffer %)`

### 4.5 Focus Scenarios
- Bad weather focus: fixed at 35%
- Good weather focus: fixed at 75%
- Current weather focus: default 50%, editable by user

---

## 5. Project View Page

### 5.1 Page Header
- Page title: Project name
- Subtitle: Team name (if provided), clickable if team link is set

### 5.2 Initiative Summary Table
| Initiative Name | Pointed SP | Est. Additional SP | Buffer % | Total Points |
|---|---|---|---|---|
| (project name if no initiative name set) | | | | (pointed + additional) × (1 + buffer%) |

### 5.3 Scenario Cards (3 cards)
One card per scenario (bad / current / good weather), showing:
- Scenario label and focus %
- Estimated completion sprint
- Sprints remaining
- Estimated completion date (if sprint dates are configured)

Current weather card is visually highlighted (primary/active state).

### 5.4 Burndown Chart
- Y axis: story points remaining (top = total points)
- X axis: sprint names; hover shows dates if configured
- Grey line: actual work done (past sprints)
- Vertical line: current sprint marker
- Three projection lines from current sprint forward: bad (red), current (blue), good (green)
- Chart updates immediately when scope creep points are added in settings card

**Charting library**: Chart.js

### 5.5 Settings Card (left panel)
Read-only display:
- Current sprint (informational only — updated automatically when a sprint is completed)
- Work remaining (informational only — updated automatically from the done table)

Editable:
- Average team velocity
  - Manual entry, OR
  - Auto-calculated toggle: uses average of total sprint points from done table
- Current weather focus %
  - Manual entry, OR
  - Auto-calculated toggle: uses average of focus % from done table
- Add story points (scope creep): adds temporarily to remaining work and updates chart/cards in real time. **Not persisted** — resets on page reload. Used for quick "what if" checks.
- Link to full settings edit (opens same modal as project creation)

### 5.6 Done Table (past sprints)
| Dates | Sprint | Total Sprint Points | Initiative Work Done | Focus % | Initiative Points Remaining |
|---|---|---|---|---|---|

- Dates shown if sprint cadence start date + duration are configured
- Focus % = Initiative Work Done ÷ Total Sprint Points
- Initiative Points Remaining = previous row's remaining − this row's initiative work done (first row uses total points)
- Editing individual rows: deferred to v2

### 5.7 Forecast Table (future sprints)
Columns: Dates (if configured) | Sprint | Bad weather Done / Remaining | Current weather Done / Remaining | Good weather Done / Remaining

- Dates and sprint names shown for each row
- Done = Average Team Velocity × focus % (rounded)
- Remaining = previous remaining − done
- First row picks up from last row of done table
- Rows auto-generate until remaining reaches 0 for all three scenarios (table ends at the longest scenario)

**Complete sprint action**: A button on the first row of the forecast table opens a modal. User enters:
- Total sprint points
- Initiative work done

On save:
- Row moves from forecast table to done table with entered values
- Current sprint advances to the next sprint (auto-generated name and dates if configured)
- Forecast table recalculates from new current sprint
- Burndown chart updates

---

## 6. Editing Project Settings

Full edit available via the settings card link — same modal as project creation, all fields editable.

Quick edits directly in settings card:
- Average team velocity (manual or auto-calculated toggle)
- Current weather focus % (manual or auto-calculated toggle)
- Scope creep points (not persisted)

---

## 7. Deleting a Project

Available from the project list page. Clicking delete opens a confirmation modal requiring the user to type `DELETE` before confirming.

---

## 8. Demo Mode

Accessible at a specific URL (e.g. `/demo`).

- User is logged in automatically as demo/demo
- Single default project, not editable beyond the forecast simulator interactions
- No project list, no project creation
- All changes stored in local storage only — not persisted to the database
- Default project values:
  - Project name: Demo Project
  - Total story points: 160
  - Average team velocity: 45
  - Current weather focus: 55%
  - Sprint format: `Sprint yyyy.xx`, starting at current year and sprint 01
  - Sprint duration: 2 weeks, cadence start: Monday of the current week

---

## 9. Technical Notes

- **Stack**: PHP + MariaDB (via phpMyAdmin on cracco.ch)
- **Charting**: Chart.js (CDN)
- **Session management**: Standard PHP sessions for auth
- **Demo mode**: Local storage only, no DB writes
- **File structure**: Credentials and non-public files stored in a private folder outside the public web root. Both `config.php` and the bootstrap file are gitignored. Tracked example files (`config.example.php`, `bootstrap.example.php`) serve as setup templates.

---

## 10. Out of Scope (Deferred)

| Feature | Version |
|---|---|
| Editing past sprint rows | Soon |
| Self-registration | Future |
| Email notifications | Future |
| Export to PDF/Excel | Future |
