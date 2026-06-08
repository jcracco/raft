<?php
require_once __DIR__ . '/bootstrap.php';
$IS_DEMO = is_demo();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $IS_DEMO ? 'RAFT - Demo' : 'RAFT - Range and Forecasting Tool'; ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%231e2535'/%3E%3Cpath d='M4 7 Q18 10 28 18' stroke='%23ef4444' stroke-width='1.4' fill='none' stroke-linecap='round' opacity='0.85'/%3E%3Cpath d='M4 15 Q16 15 28 18' stroke='%2360a5fa' stroke-width='1.8' fill='none' stroke-linecap='round'/%3E%3Cpath d='M4 23 Q18 20 28 18' stroke='%234ade80' stroke-width='1.4' fill='none' stroke-linecap='round' opacity='0.85'/%3E%3Ccircle cx='28' cy='18' r='1.5' fill='%23e2e8f0' opacity='0.4'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/react/18.2.0/umd/react.production.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/react-dom/18.2.0/umd/react-dom.production.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/babel-standalone/7.23.2/babel.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <?php if ($IS_DEMO): ?>
    <script src="mock-api.js"></script>
    <?php endif; ?>
    <?php if ($IS_DEMO && defined('UMAMI_ENABLED') && UMAMI_ENABLED): ?>
    <script>
        // Allows ?src to be tracked as ?utm_source in umami
            (function() {
            const url = new URL(window.location.href);
            const shortSrc = url.searchParams.get('src');
            
            if (shortSrc) {
                url.searchParams.set('utm_source', shortSrc);
                url.searchParams.delete('src');
                
                // Updates the address bar dynamically without a page refresh
                window.history.replaceState(null, '', url.pathname + url.search);
            }
            })();
        </script>
    <script defer src="https://cloud.umami.is/script.js" data-website-id="<?php echo UMAMI_WEBSITE_ID; ?>"></script>
<?php endif; ?>
</head>
<body>
<?php if ($IS_DEMO): ?>
<div class="demo-banner">
    <span>⚠ <strong>Demo version</strong> — all data is fictional. Changes reset when you close the tab.</span>
    <a href="https://github.com/jcracco/" target="_blank" rel="noreferrer" class="demo-banner-link">View on GitHub ↗</a>
</div>
<?php endif; ?>

<div id="root"></div>

<script type="text/babel">
const { useState, useEffect, useRef, useCallback, useMemo } = React;
// ── Lucide-style inline SVG icons ────────────────────────────────────────────
function LucideIcon({ size = 24, children }) {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24"
             fill="none" stroke="currentColor" strokeWidth="2"
             strokeLinecap="round" strokeLinejoin="round"
             style={{display:'inline-block',verticalAlign:'middle'}}>
            {children}
        </svg>
    );
}
const ArrowLeft = ({ size = 24 }) => <LucideIcon size={size}><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></LucideIcon>;
const Share2   = ({ size = 24 }) => <LucideIcon size={size}><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></LucideIcon>;
const LogOut   = ({ size = 24 }) => <LucideIcon size={size}><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></LucideIcon>;
const Settings = ({ size = 24 }) => <LucideIcon size={size}><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></LucideIcon>;
const Sun      = ({ size = 24 }) => <LucideIcon size={size}><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></LucideIcon>;
const Moon     = ({ size = 24 }) => <LucideIcon size={size}><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></LucideIcon>;
const Check    = ({ size = 24 }) => <LucideIcon size={size}><polyline points="20 6 9 17 4 12"/></LucideIcon>;
const IS_DEMO = <?= $IS_DEMO ? 'true' : 'false' ?>;

// Clipboard helper — falls back to execCommand for non-HTTPS contexts
function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    }
    const el = Object.assign(document.createElement('textarea'), { value: text });
    Object.assign(el.style, { position: 'fixed', opacity: '0' });
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
    return Promise.resolve();
}

// ── API helper ──────────────────────────────────────────────────────────────
async function api(action, params = {}, method = 'GET') {
    if (IS_DEMO && window._mockApi) return window._mockApi(action, params);

    let url = `api.php?action=${action}`;
    const opts = { headers: { 'Content-Type': 'application/json' } };

    if (method === 'POST') {
        opts.method = 'POST';
        opts.body   = JSON.stringify(params);
    } else if (Object.keys(params).length) {
        url += '&' + new URLSearchParams(params).toString();
    }

    const res  = await fetch(url, opts);
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Request failed');
    return data;
}

// ── Sprint name preview helper ──────────────────────────────────────────────
function previewSprintName(prefix, useYear, yearFmt, numFmt, startYear, startNum) {
    const p   = prefix || 'Sprint';
    const num = numFmt === 'xx' ? String(startNum || 1).padStart(2, '0') : String(startNum || 1);
    if (!useYear) return `${p} ${num}`;
    const y   = yearFmt === 'yy' ? String(startYear || new Date().getFullYear()).slice(2) : String(startYear || new Date().getFullYear());
    return `${p} ${y}.${num}`;
}

// ── Safe URL helper — strips anything that isn't http/https ───────────────────
function safeUrl(url) {
    if (!url) return null;
    try {
        const u = new URL(url);
        return (u.protocol === 'http:' || u.protocol === 'https:') ? url : null;
    } catch { return null; }
}

// ── Sprint end-date helper (mirrors mock-api / PHP logic) ─────────────────────
function sprintEndDateFromProject(project, sprint_number, sprint_year) {
    if (!project || !project.cadence_start_date || !project.sprint_duration_weeks) return null;
    const anchor   = new Date(project.cadence_start_date);
    const duration = project.sprint_duration_weeks;
    let offset;
    if (project.use_year && sprint_year != null) {
        const sprintsPerYear = Math.floor(52 / duration);
        offset = (sprint_year - anchor.getUTCFullYear()) * sprintsPerYear + (sprint_number - 1);
    } else {
        offset = sprint_number - 1;
    }
    const d = new Date(anchor);
    d.setUTCDate(d.getUTCDate() + offset * duration * 7 + duration * 7 - 1);
    return d.toISOString().split('T')[0];
}

// ── Tooltip icon ─────────────────────────────────────────────────────────────
function Tip({ text }) {
    return <span className="tip" data-tooltip={text}>ⓘ</span>;
}

// ── ThemeToggle (fixed bottom-right, always visible) ─────────────────────────
function ThemeToggle({ theme, onToggle }) {
    const Icon = theme === 'dark' ? Sun : Moon;
    return (
        <button onClick={onToggle} title="Toggle theme"
            style={{ position: 'fixed', bottom: 24, right: 24, zIndex: 500,
                background: 'var(--bg2)', border: '1px solid var(--border)',
                borderRadius: '50%', width: 36, height: 36, cursor: 'pointer',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                color: 'var(--text-muted)', transition: 'all 0.15s' }}>
            <Icon size={16} />
        </button>
    );
}

// ── LoginPage ───────────────────────────────────────────────────────────────
function LoginPage({ onLogin }) {
    const [username, setUsername] = useState(IS_DEMO ? 'demo' : '');
    const [password, setPassword] = useState(IS_DEMO ? 'demo' : '');
    const [error, setError]       = useState('');
    const [loading, setLoading]   = useState(false);

    async function handleSubmit(e) {
        e.preventDefault();
        setLoading(true); setError('');
        try {
            await api('login', { username, password }, 'POST');
            onLogin(username);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="login-wrap">
            <div className="login-box">
                <div className="login-logo">RAFT</div>
                <div className="login-sub">Range And Forecasting Tool</div>
                {error && <div className="login-error">{error}</div>}
                <form onSubmit={handleSubmit}>
                    <div className="form-group">
                        <label className="form-label">Username</label>
                        <input className="form-input" value={username} onChange={e => setUsername(e.target.value)} autoFocus />
                    </div>
                    <div className="form-group">
                        <label className="form-label">Password</label>
                        <input className="form-input" type="password" value={password} onChange={e => setPassword(e.target.value)} />
                    </div>
                    <button className="btn btn-primary" style={{width:'100%',marginTop:8}} disabled={loading}>
                        {loading ? 'Signing in…' : 'Sign in'}
                    </button>
                </form>
            </div>
        </div>
    );
}

// ── ProjectModal ─────────────────────────────────────────────────────────────
function ProjectModal({ project, onSave, onClose }) {
    const editing = !!project;
    const [form, setForm] = useState(project ? {
        ...project,
        pointed_sp:              Math.round(project.pointed_sp              || 0),
        estimated_additional_sp: Math.round(project.estimated_additional_sp || 0),
        buffer_pct:              Math.round(project.buffer_pct              || 0),
        avg_velocity:            Math.round(project.avg_velocity            || 0),
    } : {
        project_name: '', initiative_name: '', initiative_link: '',
        team_name: '', team_link: '', pointed_sp: '', estimated_additional_sp: '',
        buffer_pct: 25, avg_velocity: '', velocity_auto: false,
        current_weather_pct: 50, weather_auto: false,
        sprint_prefix: 'Sprint', use_year: true, year_format: 'yyyy', number_format: 'xx',
        sprint_duration_weeks: 2, cadence_start_date: '',
        initiative_start_year: new Date().getFullYear(), initiative_start_number: 1,
    });
    const [useDates, setUseDates] = useState(!!(project?.cadence_start_date));
    const [saving, setSaving]     = useState(false);
    const [error, setError]       = useState('');

    const set    = (k, v) => setForm(f => ({ ...f, [k]: v }));
    const setNum = (k, min, max) => e => {
        const v = e.target.value;
        if (v === '') { set(k, ''); return; }
        const n = parseInt(v);
        if (!isNaN(n)) set(k, Math.min(max, Math.max(min, n)));
    };

    // Build sprint name dropdown options
    const { sprintOptions, sprintOptionGroups } = useMemo(() => {
        const duration      = Math.max(1, parseInt(form.sprint_duration_weeks) || 2);
        const sprintsPerYear = Math.floor(52 / duration);
        if (!form.use_year) {
            const opts = Array.from({ length: 52 }, (_, i) => ({
                value: `0:${i + 1}`,
                label: previewSprintName(form.sprint_prefix, false, form.year_format, form.number_format, null, i + 1),
                year: null, num: i + 1,
            }));
            return { sprintOptions: opts, sprintOptionGroups: null };
        }
        const cur  = new Date().getFullYear();
        const years = [cur - 1, cur, cur + 1];
        const opts  = years.flatMap(year =>
            Array.from({ length: sprintsPerYear }, (_, i) => ({
                value: `${year}:${i + 1}`,
                label: previewSprintName(form.sprint_prefix, true, form.year_format, form.number_format, year, i + 1),
                year, num: i + 1,
            }))
        );
        const groups = {};
        opts.forEach(o => { (groups[o.year] = groups[o.year] || []).push(o); });
        return {
            sprintOptions: opts,
            sprintOptionGroups: Object.entries(groups).sort(([a], [b]) => Number(a) - Number(b)),
        };
    }, [form.sprint_prefix, form.use_year, form.year_format, form.number_format, form.sprint_duration_weeks]);

    const selectedSprintVal = form.use_year
        ? `${form.initiative_start_year || new Date().getFullYear()}:${form.initiative_start_number || 1}`
        : `0:${form.initiative_start_number || 1}`;

    function handleSprintSelect(val) {
        const [yearStr, numStr] = val.split(':');
        set('initiative_start_number', parseInt(numStr));
        if (form.use_year) set('initiative_start_year', parseInt(yearStr));
    }

    function handleUseDatesToggle(checked) {
        setUseDates(checked);
        if (!checked) set('cadence_start_date', '');
    }

    async function handleSave() {
        if (!form.project_name || !form.pointed_sp || !form.avg_velocity || !form.initiative_start_number) {
            setError('Please fill in all required fields.'); return;
        }
        setSaving(true); setError('');
        try {
            const payload = { ...form };
            if (!useDates) payload.cadence_start_date = '';
            const action = editing ? 'update_project' : 'create_project';
            const result = await api(action, { ...payload, id: project?.id }, 'POST');
            onSave(result.id);
        } catch (err) {
            setError(err.message);
        } finally {
            setSaving(false);
        }
    }

    // Preview: show first 2 sprints in the current year (format demonstration)
    const previewStartIdx = form.use_year ? sprintOptions.findIndex(o => o.year === new Date().getFullYear()) : 0;
    const previewLabels   = sprintOptions.slice(Math.max(0, previewStartIdx), Math.max(0, previewStartIdx) + 2).map(o => o.label);

    useEffect(() => {
        const onKey = e => { if (e.key === 'Escape') onClose(); };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, []);

    return (
        <div className="modal-overlay">
            <div className="modal">
                <div className="modal-header">
                    <div className="modal-title">{editing ? 'Edit Project' : 'New Project'}</div>
                    <button className="modal-close" onClick={onClose}>×</button>
                </div>
                <div className="modal-body">
                {error && <div className="error-msg" style={{marginBottom:16}}>{error}</div>}

                <div className="form-group" style={{marginTop:0}}>
                    <label className="form-label">Project Name <span className="required">*</span></label>
                    <input className="form-input" maxLength={100} value={form.project_name} onChange={e => set('project_name', e.target.value)} placeholder="e.g. Platform Modernization" />
                </div>
                <div className="form-row">
                    <div className="form-group">
                        <label className="form-label">Initiative Name</label>
                        <input className="form-input" maxLength={100} value={form.initiative_name || ''} onChange={e => set('initiative_name', e.target.value)} placeholder="Optional" />
                    </div>
                    <div className="form-group">
                        <label className="form-label">Initiative Link</label>
                        <input className="form-input" value={form.initiative_link || ''} onChange={e => set('initiative_link', e.target.value)} placeholder="JIRA epic URL" />
                    </div>
                </div>
                <div className="form-row">
                    <div className="form-group">
                        <label className="form-label">Team Name</label>
                        <input className="form-input" maxLength={100} value={form.team_name || ''} onChange={e => set('team_name', e.target.value)} placeholder="Optional" />
                    </div>
                    <div className="form-group">
                        <label className="form-label">Team Link</label>
                        <input className="form-input" value={form.team_link || ''} onChange={e => set('team_link', e.target.value)} placeholder="Backlog or Confluence URL" />
                    </div>
                </div>

                <div className="form-section-title">Points</div>
                <div className="form-row">
                    <div className="form-group">
                        <label className="form-label">Pointed Story Points <span className="required">*</span></label>
                        <input className="form-input" type="number" min="1" max="999" value={form.pointed_sp} onChange={setNum('pointed_sp', 1, 999)} placeholder="e.g. 120" />
                    </div>
                    <div className="form-group">
                        <label className="form-label">Est. Additional SP</label>
                        <input className="form-input" type="number" min="0" max="999" value={form.estimated_additional_sp || ''} onChange={setNum('estimated_additional_sp', 0, 999)} placeholder="Unpointed stories" />
                        <div className="form-hint">Stories not yet estimated</div>
                    </div>
                </div>
                <div className="form-row">
                    <div className="form-group">
                        <label className="form-label">Buffer %</label>
                        <input className="form-input" type="number" min="0" max="100" value={form.buffer_pct} onChange={setNum('buffer_pct', 0, 100)} />
                    </div>
                    <div className="form-group">
                        <label className="form-label">Avg Team Velocity <span className="required">*</span></label>
                        <input className="form-input" type="number" min="1" max="999" value={form.avg_velocity} onChange={setNum('avg_velocity', 1, 999)} placeholder="SP per sprint" />
                    </div>
                </div>

                <div className="form-section-title">Sprint Naming</div>
                <div className="form-row">
                    <div className="form-group">
                        <label className="form-label">Sprint Prefix</label>
                        <input className="form-input" maxLength={100} value={form.sprint_prefix} onChange={e => set('sprint_prefix', e.target.value)} placeholder="Sprint" />
                    </div>
                    <div className="form-group">
                        <label className="form-label">Number Format</label>
                        <select className="form-input" value={form.number_format} onChange={e => set('number_format', e.target.value)}>
                            <option value="xx">01, 02, 03 (leading zero)</option>
                            <option value="x">1, 2, 3 (no leading zero)</option>
                        </select>
                    </div>
                </div>
                <div className="form-row">
                    <div className="form-group">
                        <label className="form-checkbox">
                            <input type="checkbox" checked={!!form.use_year} onChange={e => set('use_year', e.target.checked)} />
                            Include year in sprint name
                        </label>
                    </div>
                    {form.use_year && (
                        <div className="form-group">
                            <label className="form-label">Year Format</label>
                            <select className="form-input" value={form.year_format || 'yyyy'} onChange={e => set('year_format', e.target.value)}>
                                <option value="yyyy">2026 (4 digits)</option>
                                <option value="yy">26 (2 digits)</option>
                            </select>
                        </div>
                    )}
                </div>
                <div className="sprint-preview">Preview: {previewLabels.join(', ')}, …</div>

                <div className="form-section-title">Initiative Start</div>
                <div className="form-group">
                    <label className="form-label">Starting Sprint <span className="required">*</span></label>
                    <select className="form-input" value={selectedSprintVal} onChange={e => handleSprintSelect(e.target.value)}>
                        {sprintOptionGroups
                            ? sprintOptionGroups.map(([year, opts]) => (
                                <optgroup key={year} label={String(year)}>
                                    {opts.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                                </optgroup>
                            ))
                            : sprintOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)
                        }
                    </select>
                </div>

                <div className="form-section-title">Sprint Dates</div>
                <div className="form-group" style={{marginBottom: useDates ? 16 : 0}}>
                    <label className="form-checkbox">
                        <input type="checkbox" checked={useDates} onChange={e => handleUseDatesToggle(e.target.checked)} />
                        Use sprint dates
                    </label>
                    <div className="form-hint">Show start/end dates in forecast tables and scenario cards</div>
                </div>
                {useDates && (
                    <div className="form-row">
                        <div className="form-group">
                            <label className="form-label">Team Cadence Start Date</label>
                            <input className="form-input" type="date" value={form.cadence_start_date || ''} onChange={e => set('cadence_start_date', e.target.value)} />
                            <div className="form-hint">Date of sprint #1 for this team</div>
                        </div>
                        <div className="form-group">
                            <label className="form-label">Sprint Duration (weeks)</label>
                            <input className="form-input" type="number" min="1" max="8" value={form.sprint_duration_weeks || ''} onChange={e => set('sprint_duration_weeks', e.target.value)} placeholder="e.g. 2" />
                        </div>
                    </div>
                )}

                </div>
                <div className="modal-footer">
                    <button className="btn btn-ghost" onClick={onClose}>Cancel</button>
                    <button className="btn btn-primary" onClick={handleSave} disabled={saving}>
                        {saving ? 'Saving…' : editing ? 'Save Changes' : 'Create Project'}
                    </button>
                </div>
            </div>
        </div>
    );
}

// ── DeleteConfirmModal ────────────────────────────────────────────────────────
function DeleteConfirmModal({ projectName, onConfirm, onClose }) {
    const [val, setVal] = useState('');
    useEffect(() => {
        const onKey = e => { if (e.key === 'Escape') onClose(); };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, []);
    return (
        <div className="modal-overlay">
            <div className="modal" style={{maxWidth:400}}>
                <div className="modal-header">
                    <div className="modal-title">Delete Project</div>
                    <button className="modal-close" onClick={onClose}>×</button>
                </div>
                <div className="modal-body">
                <p style={{color:'var(--text-dim)',marginBottom:16}}>This will permanently delete <strong>{projectName}</strong> and all its sprint data. Type <strong>DELETE</strong> to confirm.</p>
                <input className="form-input" value={val} onChange={e => setVal(e.target.value)} placeholder="DELETE" />
                <div className="modal-actions">
                    <button className="btn btn-ghost" onClick={onClose}>Cancel</button>
                    <button className="btn btn-danger" onClick={onConfirm} disabled={val !== 'DELETE'}>Delete</button>
                </div>
                </div>
            </div>
        </div>
    );
}

// ── CompleteSprintModal ────────────────────────────────────────────────────────
function CompleteSprintModal({ sprintName, sprintNumber, sprintYear, onSave, onClose }) {
    const [totalSP, setTotalSP]       = useState('');
    const [initDone, setInitDone]     = useState('');
    const [saving, setSaving]         = useState(false);
    const [error, setError]           = useState('');

    useEffect(() => {
        const onKey = e => { if (e.key === 'Escape') onClose(); };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, []);

    async function handleSave() {
        if (!totalSP || !initDone) { setError('Both fields are required.'); return; }
        if (parseInt(initDone) > parseInt(totalSP)) { setError('Initiative work done cannot exceed total sprint points.'); return; }
        setSaving(true);
        try {
            await onSave({ sprint_number: sprintNumber, sprint_year: sprintYear, total_sprint_sp: parseInt(totalSP), initiative_work_done: parseInt(initDone) });
        } catch (err) {
            setError(err.message); setSaving(false);
        }
    }

    return (
        <div className="modal-overlay">
            <div className="modal" style={{maxWidth:420}}>
                <div className="modal-header">
                    <div className="modal-title">Complete {sprintName}</div>
                    <button className="modal-close" onClick={onClose}>×</button>
                </div>
                <div className="modal-body">
                {error && <div className="error-msg" style={{marginBottom:16}}>{error}</div>}
                <div className="form-group">
                    <label className="form-label">Total Sprint Points (team velocity this sprint)</label>
                    <input className="form-input" type="number" min="0" value={totalSP} onChange={e => {
                        const v = e.target.value;
                        setTotalSP(v);
                        if (v !== '' && initDone !== '' && parseInt(initDone) > parseInt(v)) {
                            setInitDone(String(Math.max(0, parseInt(v))));
                        }
                    }} placeholder="e.g. 48" autoFocus />
                </div>
                <div className="form-group">
                    <label className="form-label">Initiative Work Done (points toward this initiative)</label>
                    <input className="form-input" type="number" min="0" max={totalSP || undefined} value={initDone}
                        onChange={e => {
                            const v = e.target.value;
                            if (v === '') { setInitDone(''); return; }
                            const raw = parseInt(v);
                            if (isNaN(raw)) return;
                            setInitDone(String(totalSP && raw > parseInt(totalSP) ? parseInt(totalSP) : Math.max(0, raw)));
                        }} placeholder="e.g. 28" />
                </div>
                <div style={{color:'var(--text-muted)',fontSize:12,marginBottom:8}}>
                    Focus: {totalSP && initDone ? `${Math.round((parseInt(initDone)/parseInt(totalSP))*100)}%` : '—'}
                </div>
                <div className="modal-actions">
                    <button className="btn btn-ghost" onClick={onClose}>Cancel</button>
                    <button className="btn btn-primary" onClick={handleSave} disabled={saving}>
                        {saving ? 'Saving…' : 'Complete Sprint'}
                    </button>
                </div>
                </div>
            </div>
        </div>
    );
}

// ── BurndownChart ─────────────────────────────────────────────────────────────
function BurndownChart({ doneTable, forecastTable, totalPoints, creepPoints, theme }) {
    const canvasRef = useRef(null);
    const chartRef  = useRef(null);

    useEffect(() => {
        if (!canvasRef.current) return;
        if (chartRef.current) chartRef.current.destroy();

        const isLight     = theme === 'light';
        const tickColor   = isLight ? '#475569' : '#64748b';
        const gridColor   = isLight ? '#e2e8f0' : '#1c2030';
        const borderColor = isLight ? '#cbd5e1' : '#252a38';
        const tooltipBg   = isLight ? '#ffffff' : '#1c2030';
        const titleColor  = isLight ? '#0f172a' : '#e2e8f0';
        const bodyColor   = isLight ? '#334155' : '#94a3b8';
        const font        = "ui-sans-serif, system-ui, sans-serif";

        const doneLabels     = doneTable.map(r => r.sprint_name);
        const forecastLabels = forecastTable.map(r => r.sprint_name);
        const allLabels      = [...doneLabels, ...forecastLabels];

        const adjustedTotal = totalPoints + (creepPoints || 0);

        // Done line: starts at total, one point per sprint + extends into forecast to connect
        const doneData = [adjustedTotal];
        doneTable.forEach(r => doneData.push(r.points_remaining));
        const donePadding = Array(forecastLabels.length).fill(null);

        // Forecast lines: share the last done value at the handoff point
        const forecastStart = doneData[doneData.length - 1];
        const badData       = [...Array(doneLabels.length).fill(null), forecastStart];
        const curData       = [...Array(doneLabels.length).fill(null), forecastStart];
        const goodData      = [...Array(doneLabels.length).fill(null), forecastStart];

        forecastTable.forEach(r => {
            badData.push(r.bad_remaining);
            curData.push(r.cur_remaining);
            goodData.push(r.good_remaining);
        });

        chartRef.current = new Chart(canvasRef.current, {
            type: 'line',
            data: {
                labels: allLabels,
                datasets: [
                    {
                        label: 'Actual',
                        data: [...doneData, ...donePadding],
                        borderColor: '#64748b',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        tension: 0.1,
                        fill: false,
                    },
                    {
                        label: 'Bad Weather (35%)',
                        data: badData,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239,68,68,0.06)',
                        borderWidth: 1.5,
                        borderDash: [5, 4],
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        tension: 0.1,
                        fill: false,
                    },
                    {
                        label: 'Current Weather',
                        data: curData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59,130,246,0.08)',
                        borderWidth: 2.5,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        tension: 0.1,
                        fill: true,
                    },
                    {
                        label: 'Good Weather (75%)',
                        data: goodData,
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34,197,94,0.06)',
                        borderWidth: 1.5,
                        borderDash: [5, 4],
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        tension: 0.1,
                        fill: false,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: tooltipBg,
                        borderColor: borderColor,
                        borderWidth: 1,
                        titleColor: titleColor,
                        bodyColor: bodyColor,
                        titleFont: { family: font, size: 11 },
                        bodyFont:  { family: font, size: 12 },
                        padding: 10,
                        callbacks: {
                            label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y !== null ? ctx.parsed.y + ' pts' : '—'}`,
                        },
                    },
                },
                scales: {
                    x: {
                        ticks: { color: tickColor, font: { family: font, size: 10 }, maxRotation: 45, minRotation: 45 },
                        grid:  { display: false },
                        border: { color: borderColor },
                    },
                    y: {
                        ticks: { color: tickColor, font: { family: font, size: 11 } },
                        grid:  { color: gridColor },
                        border: { color: borderColor },
                        min: 0,
                        title: { display: true, text: 'pts remaining', color: tickColor, font: { family: font, size: 10 } },
                    },
                },
            },
        });

        return () => { if (chartRef.current) chartRef.current.destroy(); };
    }, [doneTable, forecastTable, totalPoints, creepPoints, theme]);

    return (
        <div className="chart-card">
            <div className="chart-header">
                <span className="chart-title">Burndown Projection</span>
                <div className="chart-legend">
                    <span className="legend-item"><span className="legend-line legend-actual"></span>Actual</span>
                    <span className="legend-item"><span className="legend-line legend-bad"></span>Bad (35%)</span>
                    <span className="legend-item"><span className="legend-line legend-current"></span>Current</span>
                    <span className="legend-item"><span className="legend-line legend-good"></span>Good (75%)</span>
                </div>
            </div>
            <div className="chart-canvas-wrap">
                <canvas ref={canvasRef} />
            </div>
        </div>
    );
}

// ── QuickPlanPage ─────────────────────────────────────────────────────────────
function QuickPlanPage({ onBack, onLogout, theme }) {
    const [sp,       setSp]      = useState(200);
    const [velocity, setVel]     = useState(40);
    const [weather,  setWeather] = useState(55);

    const forecastTable = useMemo(() => {
        const v = Math.max(1, velocity);
        const badPct = 0.35, curPct = weather / 100, goodPct = 0.75;
        let bR = sp, cR = sp, gR = sp, num = 1;
        const rows = [];
        while ((bR > 0 || cR > 0 || gR > 0) && num <= 100) {
            const bD = Math.min(bR, Math.round(v * badPct));
            const cD = Math.min(cR, Math.round(v * curPct));
            const gD = Math.min(gR, Math.round(v * goodPct));
            bR = Math.max(0, bR - bD);
            cR = Math.max(0, cR - cD);
            gR = Math.max(0, gR - gD);
            rows.push({
                sprint_name: `Sprint ${String(num).padStart(2,'0')}`,
                sprint_number: num, sprint_year: null,
                bad_done: bD, bad_remaining: bR,
                cur_done: cD, cur_remaining: cR,
                good_done: gD, good_remaining: gR,
            });
            num++;
        }
        return rows;
    }, [sp, velocity, weather]);

    const countSprints = key => {
        const i = forecastTable.findIndex(r => r[key] === 0);
        return i >= 0 ? i + 1 : forecastTable.length;
    };

    return (
        <>
            <div className="top-bar">
                <div className="top-bar-left">
                    <div className="eyebrow">Range And Forecasting Tool</div>
                    <h1>RAFT</h1>
                </div>
                <div className="top-bar-right">
                    <button className="btn btn-ghost btn-sm" onClick={onBack} style={{display:'inline-flex',alignItems:'center',gap:4}}><ArrowLeft size={15} />Back</button>
                    {!IS_DEMO && <button className="btn btn-ghost btn-sm" onClick={onLogout} title="Sign out"><LogOut size={15} /></button>}
                </div>
            </div>
            <div className="main">
                <div className="page-header" style={{marginBottom:4}}>
                    <div className="page-title">Quick Plan</div>
                </div>
                <p style={{color:'var(--text-muted)',fontSize:12,marginBottom:24}}>Enter your numbers and see your delivery range instantly. Nothing is saved.</p>
                <div className="project-layout">
                    <div className="card">
                        <div className="card-title">Inputs</div>
                        <div className="settings-row">
                            <div className="settings-label">Total Story Points</div>
                            <input type="number" className="settings-input" min="1" value={sp}
                                onChange={e => setSp(Math.max(1, parseInt(e.target.value) || 1))} />
                        </div>
                        <div className="settings-row">
                            <div className="settings-label">Avg Velocity</div>
                            <input type="range" min="1" max="150" value={velocity} onChange={e => setVel(parseInt(e.target.value))} />
                            <span style={{fontSize:12,fontWeight:600,color:'var(--accent)'}}>{velocity} pts / sprint</span>
                        </div>
                        <div className="settings-row" style={{marginBottom:0}}>
                            <div className="settings-label">Current Weather %</div>
                            <input type="range" min="1" max="100" value={weather} onChange={e => setWeather(parseInt(e.target.value))} />
                            <span style={{fontSize:12,fontWeight:600,color:'var(--accent)'}}>{weather}%</span>
                        </div>
                    </div>
                    <div>
                        <div className="scenario-grid">
                            <div className="scenario-card">
                                <div className="scenario-label bad">Bad Weather<br />(Focus: 35% )</div>
                                <div className="scenario-sprint">{countSprints('bad_remaining')}</div>
                                <div className="scenario-detail">sprints</div>
                            </div>
                            <div className="scenario-card active">
                                <div className="scenario-label current">Current Weather<br />(Focus: {weather}%)</div>
                                <div className="scenario-sprint">{countSprints('cur_remaining')}</div>
                                <div className="scenario-detail">sprints</div>
                            </div>
                            <div className="scenario-card">
                                <div className="scenario-label good">Good Weather<br />(Focus: 75%)</div>
                                <div className="scenario-sprint">{countSprints('good_remaining')}</div>
                                <div className="scenario-detail">sprints</div>
                            </div>
                        </div>
                        <BurndownChart
                            doneTable={[]}
                            forecastTable={forecastTable}
                            totalPoints={sp}
                            creepPoints={0}
                            theme={theme}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}

// ── ProjectListPage ────────────────────────────────────────────────────────────
function ProjectListPage({ onOpen, onQuickPlan, onLogout }) {
    const [projects, setProjects] = useState([]);
    const [loading, setLoading]   = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState(null);

    useEffect(() => { loadProjects(); }, []);

    async function loadProjects() {
        try {
            const data = await api('get_projects');
            setProjects(data);
        } finally {
            setLoading(false);
        }
    }

    async function handleDelete(id) {
        try {
            await api('delete_project', { id }, 'POST');
            setDeleteTarget(null);
            loadProjects();
        } catch (err) {
            alert(err.message);
        }
    }

    return (
        <>
            <div className="top-bar">
                <div className="ntop-bar-left">
                    <div className="eyebrow">Range And Forecasting Tool</div>
                    <h1>RAFT</h1>
                </div>
                <div className="top-bar-right">
                    {!IS_DEMO && <button className="btn btn-ghost btn-sm" onClick={onLogout} title="Sign out"><LogOut size={15} /></button>}
                </div>
            </div>
            <div className="main">
                <div className="page-header">
                    <div className="page-title">Projects</div>
                    {!IS_DEMO && <button className="btn btn-primary" onClick={() => setShowModal(true)}>+ New Project</button>}
                </div>
                {loading ? (
                    <div className="loading"><div className="spinner" /> Loading…</div>
                ) : (
                    <>
                        <div className="project-grid">
                            <div className="project-card project-card-quickplan" onClick={onQuickPlan}>
                                <div className="project-card-name">Quick Plan</div>
                                <div className="project-card-meta">Enter points and velocity, see your delivery range instantly</div>
                                <div style={{marginTop:12,fontSize:12,color:'var(--accent)'}}>No setup required →</div>
                            </div>
                            {projects.map(p => (
                                <div className="project-card" key={p.id} onClick={() => onOpen(p.url_token)}>
                                    <div className="project-card-name">{p.project_name}</div>
                                    <div className="project-card-meta">{p.team_name || 'No team'} · {p.current_sprint_name}</div>
                                    <div className="project-card-stats">
                                        <div>
                                            <div className="project-stat-label">Remaining</div>
                                            <div className="project-stat-value">{Math.round(p.points_remaining)}</div>
                                        </div>
                                        <div>
                                            <div className="project-stat-label">Total</div>
                                            <div className="project-stat-value" style={{color:'var(--text-dim)'}}>{Math.round(p.total_points)}</div>
                                        </div>
                                    </div>
                                    {!IS_DEMO && (
                                        <div className="project-card-actions" onClick={e => e.stopPropagation()}>
                                            <button className="btn btn-danger btn-sm" onClick={() => setDeleteTarget(p)}>Delete</button>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                        {projects.length === 0 && !IS_DEMO && (
                            <p style={{color:'var(--text-muted)',fontSize:12,marginTop:16,textAlign:'center'}}>
                                No projects yet — <span style={{color:'var(--accent)',cursor:'pointer'}} onClick={() => setShowModal(true)}>create your first project →</span>
                            </p>
                        )}
                    </>
                )}
            </div>
            {showModal && <ProjectModal onSave={() => { setShowModal(false); loadProjects(); }} onClose={() => setShowModal(false)} />}
            {deleteTarget && <DeleteConfirmModal projectName={deleteTarget.project_name} onConfirm={() => handleDelete(deleteTarget.id)} onClose={() => setDeleteTarget(null)} />}
        </>
    );
}

// ── ProjectViewPage ───────────────────────────────────────────────────────────
// ── ShareModal ────────────────────────────────────────────────────────────────
function ShareModal({ url, onClose }) {
    const [copied, setCopied] = useState(false);
    function copy() {
        copyToClipboard(url).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    }
    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal" onClick={e => e.stopPropagation()} style={{maxWidth:480}}>
                <div className="modal-header">
                    <div className="modal-title">Share project</div>
                    <button className="modal-close" onClick={onClose}>×</button>
                </div>
                <div className="modal-body">
                    <p style={{fontSize:13,color:'var(--text-muted)',marginTop:0,marginBottom:12}}>
                        Anyone with this link can view this project in read-only mode.
                    </p>
                    <div style={{display:'flex',gap:8,alignItems:'center'}}>
                        <input
                            className="form-input"
                            readOnly
                            value={url}
                            onClick={e => e.target.select()}
                            style={{flex:1,fontSize:12,fontFamily:'monospace'}}
                        />
                        <button className="btn btn-primary btn-sm" onClick={copy} style={{whiteSpace:'nowrap',flexShrink:0}}>
                            {copied ? '✓ Copied' : 'Copy'}
                        </button>
                    </div>
                </div>
                <div className="modal-footer">
                    <button className="btn btn-ghost" onClick={onClose}>Done</button>
                </div>
            </div>
        </div>
    );
}

function ProjectViewPage({ urlToken, isLoggedIn, onBack, onSignIn, onLogout, theme }) {
    const [data, setData]               = useState(null);
    const [isOwner, setIsOwner]         = useState(false);
    const [shareModal, setShareModal]   = useState(false);
    const [loading, setLoading]         = useState(true);
    const [editModal, setEditModal]     = useState(false);
    const [completeModal, setCompleteModal] = useState(false);
    const [creepPoints, setCreepPoints] = useState('');
    const [initiativeVal, setInitiativeVal] = useState('');
    const [pointedSpVal, setPointedSpVal]   = useState('');
    const [bufferVal, setBufferVal]         = useState('');
    const [velocityVal, setVelocityVal]     = useState('');
    const [weatherVal, setWeatherVal]       = useState('');

    useEffect(() => { loadProject(); }, [urlToken]);

    async function loadProject(silent = false) {
        if (!silent) setLoading(true);
        try {
            const d = await api('get_project_public', { token: urlToken });
            setData(d);
            setIsOwner(!!d.is_owner);
            setInitiativeVal(d.project.initiative_name || '');
            setPointedSpVal(d.project.pointed_sp);
            setBufferVal(d.project.buffer_pct);
            setVelocityVal(d.project.avg_velocity);
            setWeatherVal(d.project.current_weather_pct);
        } finally {
            if (!silent) setLoading(false);
        }
    }

    async function handleCompleteSprint(vals) {
        await api('complete_sprint', { project_id: data.project.id, ...vals }, 'POST');
        setCompleteModal(false);
        loadProject();
    }

    async function updateSetting(field, value) {
        await api('update_project', { id: data.project.id, ...data.project, [field]: value }, 'POST');
        loadProject(true);
    }

    if (loading) return <div className="loading"><div className="spinner" /> Loading…</div>;
    if (!data) return null;

    const { project, total_points, done_table, forecast_table, current_sprint, points_remaining } = data;
    const creepNum          = parseInt(creepPoints) || 0;
    const adjustedRemaining = points_remaining + creepNum;

    // Recompute forecast from adjustedRemaining so cards update with scope creep
    const adjustedForecast = (() => {
        if (!forecast_table.length || creepNum === 0) return forecast_table;
        const velocity       = project.avg_velocity;
        const curPct         = project.current_weather_pct / 100;
        const duration       = Math.max(1, project.sprint_duration_weeks || 2);
        const sprintsPerYear = Math.floor(52 / duration);
        let bR = adjustedRemaining, cR = adjustedRemaining, gR = adjustedRemaining;
        let num = forecast_table[0].sprint_number;
        let yr  = forecast_table[0].sprint_year;
        const rows = [];
        while ((bR > 0 || cR > 0 || gR > 0) && rows.length < 200) {
            const bD = Math.min(bR, Math.round(velocity * 0.35));
            const cD = Math.min(cR, Math.round(velocity * curPct));
            const gD = Math.min(gR, Math.round(velocity * 0.75));
            bR = Math.max(0, bR - bD); cR = Math.max(0, cR - cD); gR = Math.max(0, gR - gD);
            const sprint_name = previewSprintName(project.sprint_prefix, project.use_year, project.year_format, project.number_format, yr, num);
            rows.push({ sprint_name, sprint_number: num, sprint_year: yr, bad_remaining: bR, cur_remaining: cR, good_remaining: gR });
            if (project.use_year && yr != null) { num++; if (num > sprintsPerYear) { num = 1; yr++; } }
            else { num++; }
        }
        return rows;
    })();

    // Scenario cards: find when each reaches 0
    const badEnd  = adjustedForecast.find(r => r.bad_remaining === 0);
    const curEnd  = adjustedForecast.find(r => r.cur_remaining === 0);
    const goodEnd = adjustedForecast.find(r => r.good_remaining === 0);

    const badEndDate  = badEnd  ? sprintEndDateFromProject(project, badEnd.sprint_number,  badEnd.sprint_year)  : null;
    const curEndDate  = curEnd  ? sprintEndDateFromProject(project, curEnd.sprint_number,  curEnd.sprint_year)  : null;
    const goodEndDate = goodEnd ? sprintEndDateFromProject(project, goodEnd.sprint_number, goodEnd.sprint_year) : null;
    const canAutoHistory = done_table.length >= 3;

    const firstForecast = forecast_table[0];

    return (
        <>
            <div className="top-bar">
                <div className="top-bar-left">
                    <div className="eyebrow">Range And Forecasting Tool</div>
                    <h1>RAFT</h1>
                </div>
                <div className="top-bar-right">
                    {isLoggedIn
                        ? <>
                            <button className="btn btn-ghost btn-sm" onClick={onBack} style={{display:'inline-flex',alignItems:'center',gap:4}}><ArrowLeft size={15} />Projects</button>
                            {!IS_DEMO && <button className="btn btn-ghost btn-sm" onClick={() => setShareModal(true)} title="Share project"><Share2 size={15} /></button>}
                            {!IS_DEMO && <button className="btn btn-ghost btn-sm" onClick={onLogout} title="Sign out"><LogOut size={15} /></button>}
                          </>
                        : <>
                            {!IS_DEMO && <button className="btn btn-ghost btn-sm" onClick={() => setShareModal(true)} title="Share project"><Share2 size={15} /></button>}
                            <button className="btn btn-ghost btn-sm" onClick={onSignIn}>Sign in</button>
                          </>
                    }
                </div>
            </div>
            {!isOwner && (
                <div style={{maxWidth:1200,margin:'0 auto',padding:'4px 28px 8px'}}>
                    <div style={{fontSize:12,color:'var(--text-muted)',background:'var(--bg2)',border:'1px solid var(--border)',borderRadius:'var(--radius)',padding:'7px 14px'}}>
                        {isLoggedIn
                            ? 'You\'re viewing a shared project.'
                            : <span>You're viewing a shared project — <button className="btn btn-ghost btn-sm" style={{padding:'0 4px',fontSize:12,border:'none',color:'var(--accent)',display:'inline'}} onClick={onSignIn}>sign in to edit</button>.</span>
                        }
                    </div>
                </div>
            )}
            <div className="main">

                <div style={{marginBottom:8}}>
                    <div className="page-title">{project.project_name}</div>
                    {project.team_name && (
                        <div style={{color:'var(--text-muted)',fontSize:14,marginTop:4}}>
                            {safeUrl(project.team_link)
                                ? <a href={safeUrl(project.team_link)} target="_blank" rel="noopener noreferrer" style={{color:'var(--accent)'}}>{project.team_name}</a>
                                : project.team_name}
                        </div>
                    )}
                </div>

                {/* Summary table */}
                <div className="summary-table" style={{marginTop:20}}>
                    <div className="summary-cell">
                        <div className="summary-cell-label">Initiative</div>
                        <div className="summary-cell-value" style={{fontSize:14}}>
                            {safeUrl(project.initiative_link)
                                ? <a href={safeUrl(project.initiative_link)} target="_blank" rel="noopener noreferrer" style={{color:'var(--accent)'}}>{project.initiative_name || project.project_name}</a>
                                : (project.initiative_name || project.project_name)}
                        </div>
                    </div>
                    <div className="summary-cell">
                        <div className="summary-cell-label">Pointed SP <Tip text="Total story points from already estimated stories" /></div>
                        <div className="summary-cell-value">{project.pointed_sp}</div>
                    </div>
                    {project.estimated_additional_sp > 0 && (
                        <div className="summary-cell">
                            <div className="summary-cell-label">Est. Additional <Tip text="Estimated points for stories not yet sized — known work, not yet pointed" /></div>
                            <div className="summary-cell-value">{project.estimated_additional_sp}</div>
                        </div>
                    )}
                    <div className="summary-cell">
                        <div className="summary-cell-label">Buffer % <Tip text="Safety margin applied to total points to account for unplanned discoveries during development" /></div>
                        <div className="summary-cell-value">{Math.round(project.buffer_pct)}%</div>
                    </div>
                    <div className="summary-cell">
                        <div className="summary-cell-label">Total Points <Tip text="Final initiative scope: (Pointed + Additional) × (1 + Buffer %)" /></div>
                        <div className="summary-cell-value">{Math.round(total_points)}</div>
                    </div>
                </div>

                {/* Scenario cards */}
                <div className="scenario-grid">
                    <div className="scenario-card">
                        <div className="scenario-label bad">Bad Weather<br />(Focus: 35%)</div>
                        <div className="scenario-sprint">{badEnd ? badEnd.sprint_name : '—'}</div>
                        <div className="scenario-detail">{badEnd ? `+${adjustedForecast.indexOf(badEnd)+1} sprints` : 'N/A'}</div>
                        {badEndDate && <div className="scenario-date">{badEndDate}</div>}
                    </div>
                    <div className="scenario-card active">
                        <div className="scenario-label current">Current Weather<br />(Focus: {Math.round(project.current_weather_pct)}%)</div>
                        <div className="scenario-sprint">{curEnd ? curEnd.sprint_name : '—'}</div>
                        <div className="scenario-detail">{curEnd ? `+${adjustedForecast.indexOf(curEnd)+1} sprints` : 'N/A'}</div>
                        {curEndDate && <div className="scenario-date">{curEndDate}</div>}
                    </div>
                    <div className="scenario-card">
                        <div className="scenario-label good">Good Weather<br />(Focus: 75%)</div>
                        <div className="scenario-sprint">{goodEnd ? goodEnd.sprint_name : '—'}</div>
                        <div className="scenario-detail">{goodEnd ? `+${adjustedForecast.indexOf(goodEnd)+1} sprints` : 'N/A'}</div>
                        {goodEndDate && <div className="scenario-date">{goodEndDate}</div>}
                    </div>
                </div>

                <div className="project-layout" style={!isOwner ? {gridTemplateColumns:'1fr'} : undefined}>
                    {/* Settings card — owner only */}
                    {isOwner && <div className="card">
                        <div className="card-title" style={{display:'flex',alignItems:'center',justifyContent:'space-between'}}>
                            Settings
                            <button className="btn btn-ghost btn-sm" style={{padding:'4px',lineHeight:0,border:'none'}} onClick={() => setEditModal(true)} title="Edit all settings"><Settings size={15} /></button>
                        </div>

                        <div className="settings-row">
                            <div className="settings-label">Current Sprint <Tip text="Next sprint to be completed — advances automatically when you complete a sprint" /></div>
                            <div className="settings-value readonly">{current_sprint}</div>
                        </div>
                        <div className="settings-row">
                            <div className="settings-label">Points Remaining</div>
                            <div className="settings-value readonly">{Math.round(adjustedRemaining)}</div>
                        </div>

                        <div className="settings-row">
                            <div style={{display:'flex',justifyContent:'space-between',alignItems:'baseline',marginBottom:4}}>
                                <div className="settings-label" style={{marginBottom:0}}>Avg Velocity <Tip text="Team's average story points completed per sprint" /></div>
                                <span style={{fontFamily:"'Courier New',Courier,monospace",fontSize:13,fontWeight:600,color:'var(--accent)'}}>{Math.round(velocityVal)} pts</span>
                            </div>
                            <input type="range" min="1" max="150"
                                value={velocityVal}
                                disabled={!!project.velocity_auto}
                                onChange={e => setVelocityVal(e.target.value)}
                                onPointerUp={e => updateSetting('avg_velocity', e.target.value)} />
                            <label className="auto-toggle"
                                title={!canAutoHistory ? 'At least 3 completed sprints needed for history' : ''}
                                style={!canAutoHistory ? {opacity:0.45,cursor:'not-allowed'} : {}}>
                                <input type="checkbox" checked={!!project.velocity_auto}
                                    disabled={!canAutoHistory}
                                    onChange={e => updateSetting('velocity_auto', e.target.checked ? 1 : 0)} />
                                Auto (from history)
                            </label>
                        </div>

                        <div className="settings-row">
                            <div style={{display:'flex',justifyContent:'space-between',alignItems:'baseline',marginBottom:4}}>
                                <div className="settings-label" style={{marginBottom:0}}>Current Weather % <Tip text="Average share of sprint work going toward this initiative" /></div>
                                <span style={{fontFamily:"'Courier New',Courier,monospace",fontSize:13,fontWeight:600,color:'var(--accent)'}}>{Math.round(weatherVal)}%</span>
                            </div>
                            <input type="range" min="1" max="100"
                                value={weatherVal}
                                disabled={!!project.weather_auto}
                                onChange={e => setWeatherVal(e.target.value)}
                                onPointerUp={e => updateSetting('current_weather_pct', e.target.value)} />
                            <label className="auto-toggle"
                                title={!canAutoHistory ? 'At least 3 completed sprints needed for history' : ''}
                                style={!canAutoHistory ? {opacity:0.45,cursor:'not-allowed'} : {}}>
                                <input type="checkbox" checked={!!project.weather_auto}
                                    disabled={!canAutoHistory}
                                    onChange={e => updateSetting('weather_auto', e.target.checked ? 1 : 0)} />
                                Auto (from history)
                            </label>
                        </div>

                        <div className="settings-row">
                            <div className="settings-label">Scope Creep +SP <Tip text="Temporarily add points to see delivery impact — not saved, resets on reload" /></div>
                            <div style={{display:'flex',gap:6,alignItems:'center'}}>
                                <input className="settings-input" type="number" min="0" max="999" style={{flex:1}}
                                    value={creepPoints}
                                    onChange={e => {
                                        const v = e.target.value;
                                        if (v === '') { setCreepPoints(''); return; }
                                        setCreepPoints(String(Math.min(999, Math.max(0, parseInt(v) || 0))));
                                    }} />
                                {creepNum > 0 && (
                                    <button className="btn btn-ghost btn-sm" style={{flexShrink:0,padding:'4px 8px'}} onClick={() => setCreepPoints('')}>×</button>
                                )}
                            </div>
                            <div className="creep-note">Not saved — for quick what-if checks</div>
                        </div>

                        {firstForecast && (
                            <button className="btn btn-primary btn-sm" style={{width:'100%',marginTop:8}} onClick={() => setCompleteModal(true)}>
                                Complete {current_sprint}
                            </button>
                        )}
                    </div>}

                    {/* Right column */}
                    <div>
                        <BurndownChart
                            doneTable={done_table}
                            forecastTable={forecast_table}
                            totalPoints={total_points}
                            creepPoints={creepNum}
                            theme={theme}
                        />

                        {/* Done table */}
                        {done_table.length > 0 && (
                            <div className="table-section">
                                <div className="table-section-title">Completed Sprints</div>
                                <div className="card" style={{padding:0}}>
                                    <table className="data-table">
                                        <thead>
                                            <tr>
                                                {done_table[0].start_date && <th>Dates</th>}
                                                <th>Sprint</th>
                                                <th>Total SP <Tip text="Total story points the team completed this sprint" /></th>
                                                <th>Initiative Done <Tip text="Story points completed specifically for this initiative this sprint" /></th>
                                                <th>Focus % <Tip text="Initiative Done ÷ Total SP — share of sprint work toward this initiative" /></th>
                                                <th>Remaining <Tip text="Initiative story points still to complete after this sprint" /></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {done_table.map((r, i) => (
                                                <tr key={i}>
                                                    {r.start_date && <td className="col-dim">{r.start_date}{r.end_date ? ` → ${r.end_date}` : ''}</td>}
                                                    <td>{r.sprint_name}</td>
                                                    <td>{r.total_sprint_sp}</td>
                                                    <td>{r.initiative_work_done}</td>
                                                    <td className="col-dim">{Math.round(r.focus_pct)}%</td>
                                                    <td className={r.points_remaining === 0 ? 'col-zero' : ''}>{Math.round(r.points_remaining)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        {/* Forecast table */}
                        {forecast_table.length > 0 && (
                            <div className="table-section">
                                <div className="table-section-title">Forecast <Tip text="Each scenario projects sprint-by-sprint delivery based on focus %. Done = Avg Velocity × focus % for that scenario. Remaining counts down sprint by sprint until ✓ — the projected completion sprint." /></div>
                                <div className="card" style={{padding:0}}>
                                    <table className="data-table">
                                        <thead>
                                            <tr>
                                                {forecast_table[0].start_date && <th rowSpan={2} style={{verticalAlign:'middle'}}>Dates</th>}
                                                <th rowSpan={2} style={{verticalAlign:'middle'}}>Sprint</th>
                                                <th colSpan={2} className="th-group th-group-bad">Bad weather<br/>(35% focus)</th>
                                                <th colSpan={2} className="th-group th-group-current">Current weather<br/>({Math.round(project.current_weather_pct)}% focus)</th>
                                                <th colSpan={2} className="th-group th-group-good">Good weather<br/>(75% focus)</th>
                                            </tr>
                                            <tr>
                                                <th>Done</th>
                                                <th>Remaining</th>
                                                <th>Done</th>
                                                <th>Remaining</th>
                                                <th>Done</th>
                                                <th>Remaining</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {forecast_table.map((r, i) => (
                                                <tr key={i}>
                                                    {r.start_date && <td className="col-dim">{r.start_date}{r.end_date ? ` → ${r.end_date}` : ''}</td>}
                                                    <td>{r.sprint_name}</td>
                                                    <td className="col-bad">{Math.round(r.bad_done)}</td>
                                                    <td className={r.bad_remaining === 0 ? 'col-zero' : 'col-bad'}>{r.bad_remaining === 0 ? '✓' : Math.round(r.bad_remaining)}</td>
                                                    <td className="col-current">{Math.round(r.cur_done)}</td>
                                                    <td className={r.cur_remaining === 0 ? 'col-zero' : 'col-current'}>{r.cur_remaining === 0 ? '✓' : Math.round(r.cur_remaining)}</td>
                                                    <td className="col-good">{Math.round(r.good_done)}</td>
                                                    <td className={r.good_remaining === 0 ? 'col-zero' : 'col-good'}>{r.good_remaining === 0 ? '✓' : Math.round(r.good_remaining)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {shareModal && (
                <ShareModal url={window.location.href} onClose={() => setShareModal(false)} />
            )}
            {isOwner && editModal && (
                <ProjectModal project={project} onSave={() => { setEditModal(false); loadProject(); }} onClose={() => setEditModal(false)} />
            )}
            {isOwner && completeModal && firstForecast && (
                <CompleteSprintModal
                    sprintName={current_sprint}
                    sprintNumber={firstForecast.sprint_number}
                    sprintYear={firstForecast.sprint_year}
                    onSave={handleCompleteSprint}
                    onClose={() => setCompleteModal(false)}
                />
            )}
        </>
    );
}

// ── Hash router ───────────────────────────────────────────────────────────────
function parseHash() {
    const hash = window.location.hash;
    if (!hash || hash === '#' || hash === '#/') return { view: 'list' };
    if (hash === '#/quickplan') return { view: 'quickplan' };
    const m = hash.match(/^#\/project\/([0-9a-f]{6})$/i);
    if (m) return { view: 'project', urlToken: m[1].toLowerCase() };
    return { view: 'list' };
}

function navigate(route) {
    if (route.view === 'list')           window.location.hash = '#/';
    else if (route.view === 'quickplan') window.location.hash = '#/quickplan';
    else if (route.view === 'project')   window.location.hash = `#/project/${route.urlToken}`;
}

// ── App ────────────────────────────────────────────────────────────────────────
function App() {
    const [authState, setAuthState] = useState('loading'); // loading | loggedIn | loggedOut
    const [route, setRoute]         = useState(parseHash);
    const [forceLogin, setForceLogin] = useState(false);
    const [theme, setTheme]         = useState(() => localStorage.getItem('raft_theme') || 'dark');

    useEffect(() => {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('raft_theme', theme);
    }, [theme]);

    useEffect(() => {
        api('session').then(s => {
            setAuthState(s.logged_in ? 'loggedIn' : 'loggedOut');
        }).catch(() => setAuthState('loggedOut'));
    }, []);

    // Browser back / forward
    useEffect(() => {
        function onHashChange() { setRoute(parseHash()); }
        window.addEventListener('hashchange', onHashChange);
        return () => window.removeEventListener('hashchange', onHashChange);
    }, []);

    function go(r) {
        navigate(r);
        setRoute(r);
    }

    async function handleLogout() {
        await api('logout', {}, 'POST');
        setAuthState('loggedOut');
        go({ view: 'list' });
    }

    const toggleTheme = () => setTheme(t => t === 'dark' ? 'light' : 'dark');
    const themeToggle = <ThemeToggle theme={theme} onToggle={toggleTheme} />;

    if (authState === 'loading') return <>{themeToggle}<div className="loading"><div className="spinner" /></div></>;

    let content;
    if (authState === 'loggedOut') {
        if (!forceLogin && route.view === 'project' && route.urlToken) {
            content = <ProjectViewPage
                urlToken={route.urlToken}
                isLoggedIn={false}
                onBack={null}
                onSignIn={() => setForceLogin(true)}
                theme={theme}
            />;
        } else {
            content = <LoginPage onLogin={() => { setAuthState('loggedIn'); setForceLogin(false); }} />;
        }
    } else if (route.view === 'quickplan') {
        content = <QuickPlanPage onBack={() => go({ view: 'list' })} onLogout={handleLogout} theme={theme} />;
    } else if (route.view === 'project' && route.urlToken) {
        content = <ProjectViewPage
            urlToken={route.urlToken}
            isLoggedIn={true}
            onBack={() => go({ view: 'list' })}
            onSignIn={null}
            onLogout={handleLogout}
            theme={theme}
        />;
    } else {
        content = <ProjectListPage
            onOpen={token => go({ view: 'project', urlToken: token })}
            onQuickPlan={() => go({ view: 'quickplan' })}
            onLogout={handleLogout}
        />;
    }

    return <>{content}{themeToggle}</>;
}

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
</script>
</body>
</html>
