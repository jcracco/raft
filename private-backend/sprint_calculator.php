<?php
// RAFT — Range And Forecasting Tool
// Sprint calculation helpers

/**
 * Generate a sprint name from a project config and a sprint number + year.
 */
function sprint_name(array $project, int $number, ?int $year = null): string {
    $prefix = $project['sprint_prefix'];
    $num    = $project['number_format'] === 'xx' ? sprintf('%02d', $number) : (string) $number;

    if (!$project['use_year'] || $year === null) {
        return "$prefix $num";
    }

    $y = $project['year_format'] === 'yy' ? substr((string) $year, 2) : (string) $year;
    return "$prefix $y.$num";
}

/**
 * Given a project config and a sprint number + year,
 * return the sprint start date (Y-m-d) or null if cadence not configured.
 */
function sprint_start_date(array $project, int $number, ?int $year = null): ?string {
    if (!$project['cadence_start_date'] || !$project['sprint_duration_weeks']) {
        return null;
    }

    $anchor       = new DateTime($project['cadence_start_date']);
    $duration     = (int) $project['sprint_duration_weeks'];
    $anchor_year  = (int) $anchor->format('Y');
    $anchor_num   = 1; // cadence_start_date is always sprint #1 of its year (or overall)

    // Calculate total sprints from anchor to target
    if ($project['use_year'] && $year !== null) {
        // How many full years between anchor year and target year?
        // We need to know sprints per year — derived from 52 weeks / duration
        $sprints_per_year = (int) floor(52 / $duration);
        $year_offset      = ($year - $anchor_year) * $sprints_per_year;
        $sprint_offset    = $year_offset + ($number - 1);
    } else {
        $sprint_offset = $number - 1;
    }

    $days = $sprint_offset * $duration * 7;
    $anchor->modify("+{$days} days");
    return $anchor->format('Y-m-d');
}

/**
 * Return sprint end date given a start date and duration in weeks.
 */
function sprint_end_date(string $start_date, int $duration_weeks): string {
    $d = new DateTime($start_date);
    $d->modify('+' . ($duration_weeks * 7 - 1) . ' days');
    return $d->format('Y-m-d');
}

/**
 * Calculate total points for a project.
 * (pointed_sp + estimated_additional_sp) * (1 + buffer_pct / 100)
 */
function total_points(array $project): int {
    $base   = (int) $project['pointed_sp'] + (int) ($project['estimated_additional_sp'] ?? 0);
    $buffer = 1 + ((float) $project['buffer_pct'] / 100);
    return (int) ceil($base * $buffer);
}

/**
 * Build the done table rows from sprint_entries.
 * Adds calculated focus_pct and points_remaining to each row.
 */
function build_done_table(array $entries, float $total_pts): array {
    $remaining = $total_pts;
    $rows      = [];

    foreach ($entries as $entry) {
        $focus_pct   = $entry['total_sprint_sp'] > 0
            ? (int) round(($entry['initiative_work_done'] / $entry['total_sprint_sp']) * 100)
            : 0;
        $remaining  -= $entry['initiative_work_done'];
        $rows[]      = array_merge($entry, [
            'focus_pct'          => $focus_pct,
            'points_remaining'   => max(0, (int) round($remaining)),
        ]);
    }

    return $rows;
}

/**
 * Build forecast rows for all three scenarios from the current remaining points.
 * Returns array of rows, each with sprint name, dates, and done/remaining per scenario.
 * Stops when all three scenarios reach 0.
 */
function build_forecast_table(array $project, float $remaining, int $next_number, ?int $next_year): array {
    $velocity     = (float) $project['avg_velocity'];
    $bad_pct      = 0.35;
    $current_pct  = (float) $project['current_weather_pct'] / 100;
    $good_pct     = 0.75;
    $duration     = (int) ($project['sprint_duration_weeks'] ?? 0);

    $bad_rem     = $remaining;
    $current_rem = $remaining;
    $good_rem    = $remaining;

    $rows   = [];
    $number = $next_number;
    $year   = $next_year;

    while ($bad_rem > 0 || $current_rem > 0 || $good_rem > 0) {
        $bad_done     = min($bad_rem,     (int) round($velocity * $bad_pct));
        $current_done = min($current_rem, (int) round($velocity * $current_pct));
        $good_done    = min($good_rem,    (int) round($velocity * $good_pct));

        $bad_rem     = max(0, $bad_rem     - $bad_done);
        $current_rem = max(0, $current_rem - $current_done);
        $good_rem    = max(0, $good_rem    - $good_done);

        $start = sprint_start_date($project, $number, $year);
        $end   = ($start && $duration) ? sprint_end_date($start, $duration) : null;

        $rows[] = [
            'sprint_name'    => sprint_name($project, $number, $year),
            'sprint_number'  => $number,
            'sprint_year'    => $year,
            'start_date'     => $start,
            'end_date'       => $end,
            'bad_done'       => $bad_done,
            'bad_remaining'  => $bad_rem,
            'cur_done'       => $current_done,
            'cur_remaining'  => $current_rem,
            'good_done'      => $good_done,
            'good_remaining' => $good_rem,
        ];

        // Advance sprint number
        [$number, $year] = next_sprint($project, $number, $year);
    }

    return $rows;
}

/**
 * Advance to the next sprint number (and year if applicable).
 * Returns [$next_number, $next_year].
 */
function next_sprint(array $project, int $number, ?int $year): array {
    if (!$project['use_year'] || $year === null) {
        return [$number + 1, null];
    }

    $duration         = (int) ($project['sprint_duration_weeks'] ?? 2);
    $sprints_per_year = (int) floor(52 / $duration);
    $next_number      = $number + 1;
    $next_year        = $year;

    if ($next_number > $sprints_per_year) {
        $next_number = 1;
        $next_year++;
    }

    return [$next_number, $next_year];
}

/**
 * Calculate avg_velocity from done table if velocity_auto is set.
 */
function calc_avg_velocity(array $entries): float {
    if (empty($entries)) return 0;
    $total = array_sum(array_column($entries, 'total_sprint_sp'));
    return round($total / count($entries), 2);
}

/**
 * Calculate current_weather_pct from done table if weather_auto is set.
 */
function calc_avg_weather(array $entries): float {
    if (empty($entries)) return 50.0;
    $pcts = [];
    foreach ($entries as $e) {
        if ($e['total_sprint_sp'] > 0) {
            $pcts[] = ($e['initiative_work_done'] / $e['total_sprint_sp']) * 100;
        }
    }
    return empty($pcts) ? 50.0 : round(array_sum($pcts) / count($pcts), 1);
}
