<?php
// RAFT — Range And Forecasting Tool
// API endpoint — handles all CRUD operations
// All responses are JSON.

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Demo mode — no writes
$demo = is_demo();

// Helper: send JSON response
function respond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function respond_error(string $message, int $code = 400): void {
    respond(['error' => $message], $code);
}

// ---------------------------------------------------------------------------
// AUTH
// ---------------------------------------------------------------------------

if ($action === 'login' && $method === 'POST') {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';

    if ($demo) {
        // Demo login: accept demo/demo only
        if ($username === 'demo' && $password === 'demo') {
            start_session();
            $_SESSION['user_id']  = 0;
            $_SESSION['username'] = 'demo';
            $_SESSION['is_admin'] = false;
            respond(['ok' => true, 'username' => 'demo', 'demo' => true]);
        }
        respond_error('Invalid credentials', 401);
    }

    if (login($username, $password)) {
        respond(['ok' => true, 'username' => $username]);
    }
    respond_error('Invalid username or password', 401);
}

if ($action === 'logout' && $method === 'POST') {
    logout();
    respond(['ok' => true]);
}

if ($action === 'session') {
    start_session();
    if (is_logged_in() || ($demo && isset($_SESSION['user_id']))) {
        respond([
            'logged_in' => true,
            'username'  => $_SESSION['username'] ?? 'demo',
            'demo'      => $demo,
        ]);
    }
    respond(['logged_in' => false]);
}

// ---------------------------------------------------------------------------
// PROJECTS
// ---------------------------------------------------------------------------

if ($action === 'get_projects') {
    require_login();
    $db   = get_db();
    $stmt = $db->prepare('SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([current_user_id()]);
    $projects = $stmt->fetchAll();

    // Attach current sprint info to each project
    foreach ($projects as &$p) {
        $entries = get_entries($p['id']);
        $total   = total_points($p);

        if ($p['velocity_auto'] && !empty($entries)) {
            $p['avg_velocity'] = calc_avg_velocity($entries);
        }
        if ($p['weather_auto'] && !empty($entries)) {
            $p['current_weather_pct'] = calc_avg_weather($entries);
        }

        [$cur_number, $cur_year] = current_sprint($p, $entries);
        $done_table              = build_done_table($entries, $total);
        $last_remaining          = empty($done_table) ? $total : end($done_table)['points_remaining'];

        $p['current_sprint_name'] = sprint_name($p, $cur_number, $cur_year);
        $p['points_remaining']    = $last_remaining;
        $p['total_points']        = $total;
    }

    respond($projects);
}

if ($action === 'get_project') {
    require_login();
    $id = (int) ($_GET['id'] ?? 0);
    $p  = get_project_for_user($id);

    $entries = get_entries($id);

    if ($p['velocity_auto'] && !empty($entries)) {
        $p['avg_velocity'] = calc_avg_velocity($entries);
    }
    if ($p['weather_auto'] && !empty($entries)) {
        $p['current_weather_pct'] = calc_avg_weather($entries);
    }

    $total                   = total_points($p);
    $done_table              = build_done_table($entries, $total);
    $last_remaining          = empty($done_table) ? $total : end($done_table)['points_remaining'];
    [$cur_number, $cur_year] = current_sprint($p, $entries);

    // Attach sprint names and dates to done table rows
    foreach ($done_table as &$row) {
        $row['sprint_name'] = sprint_name($p, $row['sprint_number'], $row['sprint_year']);
        $start              = sprint_start_date($p, $row['sprint_number'], $row['sprint_year']);
        $row['start_date']  = $start;
        $row['end_date']    = ($start && $p['sprint_duration_weeks'])
            ? sprint_end_date($start, (int) $p['sprint_duration_weeks'])
            : null;
    }

    [$next_number, $next_year] = current_sprint($p, $entries);
    $forecast_table = build_forecast_table($p, $last_remaining, $next_number, $next_year);

    respond([
        'project'        => $p,
        'total_points'   => $total,
        'done_table'     => $done_table,
        'forecast_table' => $forecast_table,
        'current_sprint' => sprint_name($p, $cur_number, $cur_year),
        'points_remaining' => $last_remaining,
        'calc_velocity'  => $p['velocity_auto'] ? calc_avg_velocity($entries) : null,
        'calc_weather'   => $p['weather_auto']  ? calc_avg_weather($entries)  : null,
    ]);
}

if ($action === 'create_project' && $method === 'POST') {
    require_login();
    if ($demo) respond_error('Demo mode — changes are not saved', 403);

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $p    = sanitize_project($body);
    if (isset($p['error'])) respond_error($p['error']);

    $db = get_db();
    $db->prepare('
        INSERT INTO projects (
            user_id, project_name, initiative_name, initiative_link,
            team_name, team_link, pointed_sp, estimated_additional_sp, buffer_pct,
            avg_velocity, velocity_auto, current_weather_pct, weather_auto,
            sprint_prefix, use_year, year_format, number_format,
            sprint_duration_weeks, cadence_start_date,
            initiative_start_year, initiative_start_number
        ) VALUES (
            :user_id, :project_name, :initiative_name, :initiative_link,
            :team_name, :team_link, :pointed_sp, :estimated_additional_sp, :buffer_pct,
            :avg_velocity, :velocity_auto, :current_weather_pct, :weather_auto,
            :sprint_prefix, :use_year, :year_format, :number_format,
            :sprint_duration_weeks, :cadence_start_date,
            :initiative_start_year, :initiative_start_number
        )
    ')->execute(array_merge(['user_id' => current_user_id()], $p));

    respond(['ok' => true, 'id' => (int) $db->lastInsertId()]);
}

if ($action === 'update_project' && $method === 'POST') {
    require_login();
    if ($demo) respond_error('Demo mode — changes are not saved', 403);

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int) ($body['id'] ?? 0);
    get_project_for_user($id); // ownership check

    $p = sanitize_project($body);
    if (isset($p['error'])) respond_error($p['error']);

    $db = get_db();
    $db->prepare('
        UPDATE projects SET
            project_name=:project_name, initiative_name=:initiative_name,
            initiative_link=:initiative_link, team_name=:team_name, team_link=:team_link,
            pointed_sp=:pointed_sp, estimated_additional_sp=:estimated_additional_sp,
            buffer_pct=:buffer_pct, avg_velocity=:avg_velocity, velocity_auto=:velocity_auto,
            current_weather_pct=:current_weather_pct, weather_auto=:weather_auto,
            sprint_prefix=:sprint_prefix, use_year=:use_year, year_format=:year_format,
            number_format=:number_format, sprint_duration_weeks=:sprint_duration_weeks,
            cadence_start_date=:cadence_start_date, initiative_start_year=:initiative_start_year,
            initiative_start_number=:initiative_start_number
        WHERE id = :id
    ')->execute(array_merge($p, ['id' => $id]));

    respond(['ok' => true]);
}

if ($action === 'delete_project' && $method === 'POST') {
    require_login();
    if ($demo) respond_error('Demo mode — changes are not saved', 403);

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int) ($body['id'] ?? 0);
    get_project_for_user($id); // ownership check

    get_db()->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
    respond(['ok' => true]);
}

// ---------------------------------------------------------------------------
// SPRINT ENTRIES
// ---------------------------------------------------------------------------

if ($action === 'complete_sprint' && $method === 'POST') {
    require_login();
    if ($demo) respond_error('Demo mode — changes are not saved', 403);

    $body              = json_decode(file_get_contents('php://input'), true) ?? [];
    $project_id        = (int) ($body['project_id'] ?? 0);
    $total_sprint_sp   = (int) ($body['total_sprint_sp'] ?? 0);
    $initiative_done   = (int) ($body['initiative_work_done'] ?? 0);
    $sprint_number     = (int) ($body['sprint_number'] ?? 0);
    $sprint_year       = isset($body['sprint_year']) ? (int) $body['sprint_year'] : null;

    if (!$project_id || !$sprint_number || $total_sprint_sp <= 0) {
        respond_error('Missing required fields');
    }
    if ($initiative_done > $total_sprint_sp) {
        respond_error('Initiative work done cannot exceed total sprint points');
    }

    get_project_for_user($project_id); // ownership check

    $db = get_db();
    $db->prepare('
        INSERT INTO sprint_entries (project_id, sprint_number, sprint_year, total_sprint_sp, initiative_work_done)
        VALUES (?, ?, ?, ?, ?)
    ')->execute([$project_id, $sprint_number, $sprint_year, $total_sprint_sp, $initiative_done]);

    respond(['ok' => true]);
}

// ---------------------------------------------------------------------------
// HELPERS
// ---------------------------------------------------------------------------

function get_project_for_user(int $id): array {
    if (!$id) respond_error('Invalid project ID', 400);
    $db   = get_db();
    $stmt = $db->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, current_user_id()]);
    $p = $stmt->fetch();
    if (!$p) respond_error('Project not found', 404);
    return $p;
}

function get_entries(int $project_id): array {
    $db   = get_db();
    $stmt = $db->prepare('
        SELECT * FROM sprint_entries
        WHERE project_id = ?
        ORDER BY sprint_year ASC, sprint_number ASC
    ');
    $stmt->execute([$project_id]);
    return $stmt->fetchAll();
}

/**
 * Return the current sprint [number, year] — the next sprint after the last completed one,
 * or the initiative start sprint if no entries exist.
 */
function current_sprint(array $project, array $entries): array {
    if (empty($entries)) {
        return [
            (int) $project['initiative_start_number'],
            $project['initiative_start_year'] ? (int) $project['initiative_start_year'] : null,
        ];
    }
    $last = end($entries);
    return next_sprint($project, (int) $last['sprint_number'], $last['sprint_year'] ? (int) $last['sprint_year'] : null);
}

// Allow only http/https URLs; anything else (javascript:, data:, etc.) is rejected.
function sanitize_url(?string $url): ?string {
    if (!$url) return null;
    $url = trim($url);
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url)) return null;
    return substr($url, 0, 500);
}

// Accept only YYYY-MM-DD dates that are calendar-valid.
function sanitize_date(?string $d): ?string {
    if (!$d) return null;
    $d = trim($d);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return null;
    [$y, $m, $day] = explode('-', $d);
    return checkdate((int) $m, (int) $day, (int) $y) ? $d : null;
}

function sanitize_project(array $body): array {
    $required = ['project_name', 'pointed_sp', 'avg_velocity', 'initiative_start_number'];
    foreach ($required as $field) {
        if (empty($body[$field]) && $body[$field] !== 0) {
            return ['error' => "Field $field is required"];
        }
    }

    $use_year = !empty($body['use_year']) ? 1 : 0;

    return [
        'project_name'            => substr(trim($body['project_name']), 0, 100),
        'initiative_name'         => isset($body['initiative_name'])  ? substr(trim($body['initiative_name']), 0, 100) : null,
        'initiative_link'         => sanitize_url($body['initiative_link'] ?? null),
        'team_name'               => isset($body['team_name'])        ? substr(trim($body['team_name']), 0, 100)       : null,
        'team_link'               => sanitize_url($body['team_link'] ?? null),
        'pointed_sp'              => min(999, max(1,   (int)   $body['pointed_sp'])),
        'estimated_additional_sp' => isset($body['estimated_additional_sp']) ? min(999, max(0, (int) $body['estimated_additional_sp'])) : null,
        'buffer_pct'              => min(100.0, max(0.0, isset($body['buffer_pct']) ? (float) $body['buffer_pct'] : 25.0)),
        'avg_velocity'            => min(999, max(1,   (float) $body['avg_velocity'])),
        'velocity_auto'           => !empty($body['velocity_auto'])   ? 1 : 0,
        'current_weather_pct'     => min(100.0, max(1.0, isset($body['current_weather_pct']) ? (float) $body['current_weather_pct'] : 50.0)),
        'weather_auto'            => !empty($body['weather_auto'])    ? 1 : 0,
        'sprint_prefix'           => isset($body['sprint_prefix'])    ? substr(trim($body['sprint_prefix']), 0, 100) : 'Sprint',
        'use_year'                => $use_year,
        'year_format'             => ($use_year && in_array($body['year_format'] ?? '', ['yy', 'yyyy'])) ? $body['year_format'] : null,
        'number_format'           => in_array($body['number_format'] ?? '', ['x', 'xx']) ? $body['number_format'] : 'xx',
        'sprint_duration_weeks'   => isset($body['sprint_duration_weeks']) ? min(8, max(1, (int) $body['sprint_duration_weeks'])) : null,
        'cadence_start_date'      => sanitize_date($body['cadence_start_date'] ?? null),
        'initiative_start_year'   => ($use_year && isset($body['initiative_start_year'])) ? (int) $body['initiative_start_year'] : null,
        'initiative_start_number' => max(1, (int) $body['initiative_start_number']),
    ];
}

respond_error('Unknown action', 404);
