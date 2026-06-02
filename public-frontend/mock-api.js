// RAFT — Range And Forecasting Tool
// Mock API for demo mode
// Overrides the real api() function with in-memory data.
// Data persists in sessionStorage — survives refresh, resets on tab close.

const DEMO_DEFAULTS = {
    project: {
        id: 1,
        user_id: 0,
        project_name: 'Demo Project',
        initiative_name: 'Platform Modernization',
        initiative_link: null,
        team_name: 'Team Calypso',
        team_link: null,
        pointed_sp: 160,
        estimated_additional_sp: 40,
        buffer_pct: 25,
        avg_velocity: 45,
        velocity_auto: 0,
        current_weather_pct: 55,
        weather_auto: 0,
        sprint_prefix: 'Sprint',
        use_year: 1,
        year_format: 'yyyy',
        number_format: 'xx',
        sprint_duration_weeks: 2,
        cadence_start_date: '2026-01-05',
        initiative_start_year: 2026,
        initiative_start_number: 3,
        created_at: '2026-01-05 09:00:00',
        updated_at: '2026-01-05 09:00:00',
    },
    entries: [
        { id: 1, project_id: 1, sprint_number: 3, sprint_year: 2026, total_sprint_sp: 48, initiative_work_done: 25 },
        { id: 2, project_id: 1, sprint_number: 4, sprint_year: 2026, total_sprint_sp: 44, initiative_work_done: 22 },
        { id: 3, project_id: 1, sprint_number: 5, sprint_year: 2026, total_sprint_sp: 52, initiative_work_done: 34 },
    ]
};

const DEMO_VERSION = 4;

// Load from sessionStorage or use defaults; stale data is discarded on version mismatch
function loadDemoState() {
    try {
        const saved = sessionStorage.getItem('raft_demo');
        if (saved) {
            const parsed = JSON.parse(saved);
            if (parsed._version === DEMO_VERSION) return parsed;
        }
    } catch (e) {}
    return JSON.parse(JSON.stringify(DEMO_DEFAULTS));
}

function saveDemoState(state) {
    try {
        sessionStorage.setItem('raft_demo', JSON.stringify({ ...state, _version: DEMO_VERSION }));
    } catch (e) {}
}

// Sprint name generator (mirrors PHP logic)
function sprintName(project, number, year) {
    const prefix = project.sprint_prefix || 'Sprint';
    const num    = project.number_format === 'xx' ? String(number).padStart(2, '0') : String(number);
    if (!project.use_year || year == null) return `${prefix} ${num}`;
    const y = project.year_format === 'yy' ? String(year).slice(2) : String(year);
    return `${prefix} ${y}.${num}`;
}

// Calculate total points
function totalPoints(project) {
    const base   = (project.pointed_sp || 0) + (project.estimated_additional_sp || 0);
    const buffer = 1 + ((project.buffer_pct || 25) / 100);
    return Math.ceil(base * buffer);
}

// Date helpers — mirror PHP sprint_start_date / sprint_end_date
function sprintStartDate(project, number, year) {
    if (!project.cadence_start_date || !project.sprint_duration_weeks) return null;
    const anchor   = new Date(project.cadence_start_date);
    const duration = project.sprint_duration_weeks;
    let offset;
    if (project.use_year && year != null) {
        const sprintsPerYear = Math.floor(52 / duration);
        offset = (year - anchor.getFullYear()) * sprintsPerYear + (number - 1);
    } else {
        offset = number - 1;
    }
    const d = new Date(anchor);
    d.setDate(d.getDate() + offset * duration * 7);
    return d.toISOString().split('T')[0];
}

function sprintEndDate(startDate, durationWeeks) {
    const d = new Date(startDate);
    d.setDate(d.getDate() + durationWeeks * 7 - 1);
    return d.toISOString().split('T')[0];
}

// Build done table — mirrors PHP build_done_table, adds sprint_name so the chart can label each row
function buildDoneTable(entries, totalPts, project) {
    let remaining = totalPts;
    return entries.map(e => {
        const focus_pct = e.total_sprint_sp > 0 ? Math.round((e.initiative_work_done / e.total_sprint_sp) * 100) : 0;
        remaining      -= e.initiative_work_done;
        const start_date = sprintStartDate(project, e.sprint_number, e.sprint_year);
        const end_date   = start_date ? sprintEndDate(start_date, project.sprint_duration_weeks) : null;
        return {
            ...e,
            sprint_name:      sprintName(project, e.sprint_number, e.sprint_year),
            start_date,
            end_date,
            focus_pct,
            points_remaining: Math.max(0, Math.round(remaining)),
        };
    });
}

// Next sprint
function nextSprint(project, number, year) {
    if (!project.use_year || year == null) return [number + 1, null];
    const duration       = project.sprint_duration_weeks || 2;
    const sprintsPerYear = Math.floor(52 / duration);
    let nextNum          = number + 1;
    let nextYear         = year;
    if (nextNum > sprintsPerYear) { nextNum = 1; nextYear++; }
    return [nextNum, nextYear];
}

// Build forecast table
function buildForecastTable(project, remaining, nextNumber, nextYear) {
    const velocity   = project.avg_velocity;
    const badPct     = 0.35;
    const currentPct = project.current_weather_pct / 100;
    const goodPct    = 0.75;

    let badRem = remaining, currentRem = remaining, goodRem = remaining;
    let number = nextNumber, year = nextYear;
    const rows = [];

    while (badRem > 0 || currentRem > 0 || goodRem > 0) {
        const badDone     = Math.min(badRem,     Math.round(velocity * badPct));
        const currentDone = Math.min(currentRem, Math.round(velocity * currentPct));
        const goodDone    = Math.min(goodRem,    Math.round(velocity * goodPct));

        badRem     = Math.max(0, badRem     - badDone);
        currentRem = Math.max(0, currentRem - currentDone);
        goodRem    = Math.max(0, goodRem    - goodDone);

        const start_date = sprintStartDate(project, number, year);
        const end_date   = start_date ? sprintEndDate(start_date, project.sprint_duration_weeks) : null;
        rows.push({
            sprint_name:   sprintName(project, number, year),
            sprint_number: number,
            sprint_year:   year,
            start_date,
            end_date,
            bad_done: badDone,     bad_remaining:  badRem,
            cur_done: currentDone, cur_remaining:  currentRem,
            good_done: goodDone,   good_remaining: goodRem,
        });

        [number, year] = nextSprint(project, number, year);
    }
    return rows;
}

// Build full project response (mirrors api.php get_project)
function buildProjectResponse(state) {
    const { entries }  = state;
    const project      = { ...state.project };

    // Mirror PHP calc_avg_velocity / calc_avg_weather when auto flags are set
    if (project.velocity_auto && entries.length > 0) {
        const total = entries.reduce((s, e) => s + e.total_sprint_sp, 0);
        project.avg_velocity = Math.round((total / entries.length) * 100) / 100;
    }
    if (project.weather_auto && entries.length > 0) {
        const pcts = entries
            .filter(e => e.total_sprint_sp > 0)
            .map(e => (e.initiative_work_done / e.total_sprint_sp) * 100);
        if (pcts.length > 0)
            project.current_weather_pct = Math.round(pcts.reduce((a, b) => a + b, 0) / pcts.length * 10) / 10;
    }

    const totalPts  = totalPoints(project);
    const doneTable = buildDoneTable(entries, totalPts, project);
    const lastRemaining  = doneTable.length > 0 ? doneTable[doneTable.length - 1].points_remaining : totalPts;

    let [curNumber, curYear] = entries.length > 0
        ? nextSprint(project, entries[entries.length - 1].sprint_number, entries[entries.length - 1].sprint_year)
        : [project.initiative_start_number, project.initiative_start_year];

    const forecastTable = buildForecastTable(project, lastRemaining, curNumber, curYear);

    return {
        project,
        total_points:     totalPts,
        done_table:       doneTable,
        forecast_table:   forecastTable,
        current_sprint:   sprintName(project, curNumber, curYear),
        points_remaining: lastRemaining,
    };
}

// Override the global api() function
window._mockApi = async function(action, params = {}) {
    await new Promise(r => setTimeout(r, 80)); // simulate network

    const state = loadDemoState();

    if (action === 'session') {
        return { logged_in: true, username: 'demo', demo: true };
    }

    if (action === 'login') {
        return { ok: true, username: 'demo', demo: true };
    }

    if (action === 'logout') {
        return { ok: true };
    }

    if (action === 'get_projects') {
        const totalPts      = totalPoints(state.project);
        const doneTable     = buildDoneTable(state.entries, totalPts, state.project);
        const lastRemaining = doneTable.length > 0 ? doneTable[doneTable.length - 1].points_remaining : totalPts;
        const [curNum, curYr] = state.entries.length > 0
            ? nextSprint(state.project, state.entries[state.entries.length - 1].sprint_number, state.entries[state.entries.length - 1].sprint_year)
            : [state.project.initiative_start_number, state.project.initiative_start_year];
        return [{
            ...state.project,
            current_sprint_name: sprintName(state.project, curNum, curYr),
            points_remaining:    lastRemaining,
            total_points:        totalPts,
        }];
    }

    if (action === 'get_project') {
        return buildProjectResponse(state);
    }

    if (action === 'complete_sprint') {
        const { sprint_number, sprint_year, total_sprint_sp, initiative_work_done } = params;
        state.entries.push({
            id:                   state.entries.length + 1,
            project_id:           1,
            sprint_number:        parseInt(sprint_number),
            sprint_year:          sprint_year ? parseInt(sprint_year) : null,
            total_sprint_sp:      parseInt(total_sprint_sp),
            initiative_work_done: parseInt(initiative_work_done),
        });
        saveDemoState(state);
        return { ok: true };
    }

    if (action === 'update_project') {
        const allowed = [
            'project_name', 'initiative_name', 'initiative_link',
            'team_name', 'team_link',
            'pointed_sp', 'estimated_additional_sp', 'buffer_pct',
            'avg_velocity', 'velocity_auto', 'current_weather_pct', 'weather_auto',
            'sprint_prefix', 'use_year', 'year_format', 'number_format',
            'sprint_duration_weeks', 'cadence_start_date',
            'initiative_start_year', 'initiative_start_number',
        ];
        const numeric = new Set([
            'pointed_sp', 'estimated_additional_sp', 'buffer_pct',
            'avg_velocity', 'velocity_auto', 'current_weather_pct', 'weather_auto',
            'use_year', 'sprint_duration_weeks',
            'initiative_start_year', 'initiative_start_number',
        ]);
        allowed.forEach(k => {
            if (params[k] !== undefined)
                state.project[k] = numeric.has(k) ? (parseFloat(params[k]) || 0) : params[k];
        });
        saveDemoState(state);
        return { ok: true };
    }

    if (['create_project', 'delete_project'].includes(action)) {
        throw new Error('Demo mode — changes are not saved');
    }

    throw new Error('Unknown action: ' + action);
};
