<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Report — {{ $data['project_name'] }}</title>
    <style>
        :root {
            --c-bg:        #0f1117;
            --c-surface:   #1a1d27;
            --c-surface2:  #242736;
            --c-border:    #2e3349;
            --c-text:      #e2e8f0;
            --c-muted:     #8892a4;
            --c-primary:   #6366f1;
            --c-pass:      #22c55e;
            --c-warn:      #f59e0b;
            --c-fail:      #ef4444;
            --c-pass-bg:   rgba(34,197,94,0.12);
            --c-warn-bg:   rgba(245,158,11,0.12);
            --c-fail-bg:   rgba(239,68,68,0.12);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--c-bg);
            color: var(--c-text);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            padding: 40px;
        }
        .page { max-width: 960px; margin: 0 auto; }

        /* Header */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--c-primary);
            padding-bottom: 24px;
            margin-bottom: 32px;
        }
        .report-title { font-size: 26px; font-weight: 700; color: var(--c-text); }
        .report-subtitle { font-size: 13px; color: var(--c-muted); margin-top: 4px; }
        .report-meta { text-align: right; font-size: 12px; color: var(--c-muted); line-height: 1.8; }
        .report-id { font-family: monospace; font-size: 11px; color: var(--c-primary); }

        /* Scores */
        .scores-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        .score-card {
            background: var(--c-surface);
            border: 1px solid var(--c-border);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .score-value {
            font-size: 40px;
            font-weight: 800;
            line-height: 1;
            margin: 8px 0;
        }
        .score-label { font-size: 12px; color: var(--c-muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .score-grade { font-size: 18px; font-weight: 700; margin-top: 4px; }

        /* Sections */
        .section { margin-bottom: 28px; }
        .section-title {
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--c-primary);
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 1px solid var(--c-border);
        }

        /* Table */
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 10px 14px;
            text-align: left;
            border-bottom: 1px solid var(--c-border);
            font-size: 13px;
        }
        th { color: var(--c-muted); font-weight: 500; width: 240px; background: var(--c-surface); }
        td { color: var(--c-text); background: var(--c-surface2); font-family: monospace; }
        tr:last-child th, tr:last-child td { border-bottom: none; }
        table { border-radius: 8px; overflow: hidden; border: 1px solid var(--c-border); }

        /* Status badges */
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .badge-pass   { background: var(--c-pass-bg); color: var(--c-pass); }
        .badge-warning{ background: var(--c-warn-bg); color: var(--c-warn); }
        .badge-failed { background: var(--c-fail-bg); color: var(--c-fail); }

        /* Check rows */
        .check-table th { font-family: sans-serif; }
        .check-table td { font-family: sans-serif; }
        .check-value { color: var(--c-muted); font-size: 12px; font-family: monospace; }

        /* Summary bar */
        .summary-bar {
            display: flex;
            gap: 20px;
            background: var(--c-surface);
            border: 1px solid var(--c-border);
            border-radius: 12px;
            padding: 16px 24px;
            margin-bottom: 28px;
            align-items: center;
        }
        .summary-item { display: flex; align-items: center; gap: 8px; }
        .summary-dot { width: 10px; height: 10px; border-radius: 50%; }
        .dot-pass    { background: var(--c-pass); }
        .dot-warn    { background: var(--c-warn); }
        .dot-fail    { background: var(--c-fail); }
        .summary-count { font-size: 18px; font-weight: 700; }
        .summary-label { font-size: 12px; color: var(--c-muted); }

        /* Modules */
        .module-list { display: flex; flex-wrap: wrap; gap: 8px; }
        .module-chip {
            background: var(--c-surface2);
            border: 1px solid var(--c-border);
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 12px;
            font-family: monospace;
        }

        /* Signature */
        .signature {
            background: var(--c-surface);
            border: 1px solid var(--c-border);
            border-top: 2px solid var(--c-primary);
            border-radius: 12px;
            padding: 24px;
            margin-top: 32px;
        }
        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 16px;
        }
        .sig-row { font-size: 12px; }
        .sig-key { color: var(--c-muted); margin-bottom: 2px; }
        .sig-val { font-family: monospace; color: var(--c-text); word-break: break-all; }
        .checksum { font-size: 11px; word-break: break-all; color: var(--c-primary); }

        /* Print */
        @media print {
            body { background: #fff; color: #1a1a1a; padding: 20px; }
            :root {
                --c-bg: #fff; --c-surface: #f8f8f8; --c-surface2: #f0f0f0;
                --c-border: #ddd; --c-text: #1a1a1a; --c-muted: #666;
                --c-primary: #4f46e5;
            }
        }
    </style>
</head>
<body>
<div class="page">

    {{-- Header --}}
    <div class="report-header">
        <div>
            <div class="report-title">&#x1F4CB; Installation Report</div>
            <div class="report-subtitle">{{ $data['project_name'] }} &bull; {{ strtoupper($data['environment']) }}</div>
            <div class="report-subtitle" style="margin-top:6px;">
                Installed: {{ $data['install_date_jalali'] }} at {{ $data['install_time'] }}
            </div>
        </div>
        <div class="report-meta">
            <div>Domain: <strong>{{ $data['domain'] }}</strong></div>
            <div>Server IP: {{ $data['server_ip'] }}</div>
            <div>App URL: {{ $data['app_url'] }}</div>
            <div class="report-id">ID: {{ $data['installation_id'] }}</div>
        </div>
    </div>

    {{-- Health Scores --}}
    @php
        $hs  = $data['health_score'] ?? 0;
        $dr  = $data['dr_score'] ?? 0;
        $hsColor = $hs >= 80 ? '#22c55e' : ($hs >= 50 ? '#f59e0b' : '#ef4444');
        $drColor = $dr >= 80 ? '#22c55e' : ($dr >= 50 ? '#f59e0b' : '#ef4444');
        $allChecks = [
            $data['redis_status'], $data['queue_status'], $data['scheduler_status'],
            $data['supervisor_status'], $data['reverb_status'], $data['storage_status'],
            $data['mail_status'], $data['arvan_status'], $data['license_status'], $data['backup_status']
        ];
        $pass    = count(array_filter($allChecks, fn($c) => ($c['status'] ?? '') === 'pass'));
        $warning = count(array_filter($allChecks, fn($c) => ($c['status'] ?? '') === 'warning'));
        $failed  = count(array_filter($allChecks, fn($c) => ($c['status'] ?? '') === 'failed'));
    @endphp

    <div class="scores-grid">
        <div class="score-card">
            <div class="score-label">Overall Health</div>
            <div class="score-value" style="color:{{ $hsColor }}">{{ $hs }}</div>
            <div class="score-label">out of 100</div>
        </div>
        <div class="score-card">
            <div class="score-label">DR Readiness</div>
            <div class="score-value" style="color:{{ $drColor }}">{{ $dr }}</div>
            <div class="score-grade" style="color:{{ $drColor }}">Grade: {{ $data['dr_result']['dr_grade'] ?? '—' }}</div>
        </div>
        <div class="score-card">
            <div class="score-label">Checks Passed</div>
            <div class="score-value" style="color:#22c55e">{{ $pass }}</div>
            <div class="score-label">of {{ count($allChecks) }} checks</div>
        </div>
        <div class="score-card">
            <div class="score-label">Warnings</div>
            <div class="score-value" style="color:#f59e0b">{{ $warning }}</div>
            <div class="score-label">{{ $failed }} failed</div>
        </div>
    </div>

    {{-- Summary bar --}}
    <div class="summary-bar">
        <div class="summary-item">
            <div class="summary-dot dot-pass"></div>
            <div><div class="summary-count" style="color:#22c55e">{{ $pass }}</div><div class="summary-label">PASS</div></div>
        </div>
        <div class="summary-item">
            <div class="summary-dot dot-warn"></div>
            <div><div class="summary-count" style="color:#f59e0b">{{ $warning }}</div><div class="summary-label">WARNING</div></div>
        </div>
        <div class="summary-item">
            <div class="summary-dot dot-fail"></div>
            <div><div class="summary-count" style="color:#ef4444">{{ $failed }}</div><div class="summary-label">FAILED</div></div>
        </div>
        <div style="margin-right:auto; font-size:12px; color:#8892a4;">
            Installer Version: 1.0 &bull; Platform: {{ $data['project_name'] }} v{{ $data['project_version'] }}
        </div>
    </div>

    {{-- Project Information --}}
    <div class="section">
        <div class="section-title">Project Information</div>
        <table>
            <tr><th>Project Name</th><td>{{ $data['project_name'] }}</td></tr>
            <tr><th>Project Version</th><td>{{ $data['project_version'] }}</td></tr>
            <tr><th>Git Commit</th><td>{{ $data['git_commit'] }}</td></tr>
            <tr><th>Environment</th><td>{{ strtoupper($data['environment']) }}</td></tr>
            <tr><th>Domain</th><td>{{ $data['domain'] }}</td></tr>
            <tr><th>Server IP</th><td>{{ $data['server_ip'] }}</td></tr>
            <tr><th>App URL</th><td>{{ $data['app_url'] }}</td></tr>
            <tr><th>Installation Date (Jalali)</th><td>{{ $data['install_date_jalali'] }}</td></tr>
            <tr><th>Installation Time</th><td>{{ $data['install_time'] }}</td></tr>
            <tr><th>Application Timezone</th><td>{{ $data['app_timezone'] }}</td></tr>
            <tr><th>Server Timezone</th><td>{{ $data['server_timezone'] }}</td></tr>
        </table>
    </div>

    {{-- System Versions --}}
    <div class="section">
        <div class="section-title">System Versions</div>
        <table>
            <tr><th>PHP Version</th><td>{{ $data['php_version'] }}</td></tr>
            <tr><th>Laravel Version</th><td>{{ $data['laravel_version'] }}</td></tr>
            <tr><th>Composer Version</th><td>{{ $data['composer_version'] }}</td></tr>
            <tr><th>Node.js Version</th><td>{{ $data['node_version'] }}</td></tr>
            <tr><th>Database Engine</th><td>{{ $data['db_engine'] }}</td></tr>
            <tr><th>Database Version</th><td>{{ $data['db_version'] }}</td></tr>
        </table>
    </div>

    {{-- Administrator --}}
    <div class="section">
        <div class="section-title">Super Administrator</div>
        <table>
            <tr><th>Name</th><td>{{ $data['super_admin']['name'] }}</td></tr>
            <tr><th>Mobile / Contact</th><td>{{ $data['super_admin']['mobile'] }}</td></tr>
        </table>
    </div>

    {{-- Infrastructure Status --}}
    <div class="section">
        <div class="section-title">Infrastructure Status</div>
        <table class="check-table">
            <thead>
                <tr>
                    <th>Component</th>
                    <th>Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                @foreach([
                    $data['redis_status'],
                    $data['queue_status'],
                    $data['scheduler_status'],
                    $data['supervisor_status'],
                    $data['reverb_status'],
                    $data['storage_status'],
                    $data['mail_status'],
                    $data['arvan_status'],
                    $data['license_status'],
                    $data['backup_status'],
                ] as $chk)
                <tr>
                    <th>{{ $chk['label'] }}</th>
                    <td>
                        <span class="badge badge-{{ $chk['status'] === 'pass' ? 'pass' : ($chk['status'] === 'warning' ? 'warning' : 'failed') }}">
                            {{ strtoupper($chk['status']) }}
                        </span>
                    </td>
                    <td class="check-value">{{ $chk['value'] ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Installed Modules --}}
    @if(!empty($data['installed_modules']))
    <div class="section">
        <div class="section-title">Installed Modules</div>
        <div class="module-list" style="padding:16px; background:#1a1d27; border:1px solid var(--c-border); border-radius:8px;">
            @foreach($data['installed_modules'] as $mod)
                <div class="module-chip">
                    {{ $mod['name'] }} <span style="color:#6366f1;">v{{ $mod['version'] }}</span>
                    <span class="badge badge-pass" style="margin-right:4px;">{{ strtoupper($mod['status']) }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- DR Components --}}
    @if(!empty($data['dr_result']['dr_components']))
    <div class="section">
        <div class="section-title">Disaster Recovery Components</div>
        <table class="check-table">
            <thead>
                <tr><th>Component</th><th>Status</th><th>Score</th></tr>
            </thead>
            <tbody>
                @foreach($data['dr_result']['dr_components'] as $comp)
                <tr>
                    <th>{{ $comp['label'] ?? $loop->index }}</th>
                    <td>
                        <span class="badge badge-{{ ($comp['score'] ?? 0) >= 7 ? 'pass' : (($comp['score'] ?? 0) >= 4 ? 'warning' : 'failed') }}">
                            {{ ($comp['score'] ?? 0) >= 7 ? 'PASS' : (($comp['score'] ?? 0) >= 4 ? 'WARN' : 'FAIL') }}
                        </span>
                    </td>
                    <td class="check-value">{{ $comp['score'] ?? 0 }} / 10</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Baseline Snapshot --}}
    @php
        $snapStatus = $data['snapshot_status'] ?? 'PENDING';
        $snapColor  = $snapStatus === 'PASS' ? '#22c55e' : ($snapStatus === 'CREATED' ? '#22c55e' : ($snapStatus === 'PENDING' ? '#f59e0b' : '#ef4444'));
        $snapClass  = $snapStatus === 'PASS' || $snapStatus === 'CREATED' ? 'pass' : ($snapStatus === 'PENDING' ? 'warning' : 'failed');
    @endphp
    <div class="section">
        <div class="section-title">&#x1F4BE; Baseline Snapshot &amp; Integrity</div>
        <table>
            <tr>
                <th>Baseline Snapshot Location</th>
                <td>{{ $data['baseline_path'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Baseline Label</th>
                <td>{{ $data['baseline_label'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Manifest Checksum (SHA-256)</th>
                <td style="word-break:break-all; font-size:11px;">{{ $data['manifest_checksum'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Snapshot Status</th>
                <td><span class="badge badge-{{ $snapClass }}">{{ $snapStatus }}</span></td>
            </tr>
            @if(!empty($data['snapshot_verification']))
            @php $sv = $data['snapshot_verification']; @endphp
            <tr>
                <th>Snapshot Verification Result</th>
                <td>
                    <span class="badge badge-{{ ($sv['pass'] ?? false) ? 'pass' : 'failed' }}">
                        {{ $sv['status'] ?? 'N/A' }}
                    </span>
                    @if(!empty($sv['passed']))
                        <span style="font-size:11px; color:#22c55e; margin-right:8px;">&#10003; {{ count($sv['passed']) }} checks passed</span>
                    @endif
                    @if(!empty($sv['failures']))
                        <span style="font-size:11px; color:#ef4444;">&#10007; {{ count($sv['failures']) }} failures</span>
                    @endif
                </td>
            </tr>
            @if(!empty($sv['failures']))
            <tr>
                <th>Verification Failures</th>
                <td style="color:#ef4444;">
                    @foreach($sv['failures'] as $f)
                        <div style="font-size:11px;">&#x2715; {{ $f }}</div>
                    @endforeach
                </td>
            </tr>
            @endif
            @endif
            <tr>
                <th>Recovery Readiness Score</th>
                <td>
                    @php $rs = $data['recovery_readiness_score'] ?? 0; @endphp
                    <span style="color:{{ $rs >= 80 ? '#22c55e' : ($rs >= 50 ? '#f59e0b' : '#ef4444') }}; font-weight:700;">{{ $rs }}/100</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- Overall Status & Certification --}}
    @php
        $os      = $data['overall_status'] ?? 'N/A';
        $cert    = $data['installation_certification'] ?? 'N/A';
        $osColor = $os === 'PASS' ? '#22c55e' : ($os === 'WARNING' ? '#f59e0b' : '#ef4444');
        $osBg    = $os === 'PASS' ? 'rgba(34,197,94,0.08)' : ($os === 'WARNING' ? 'rgba(245,158,11,0.08)' : 'rgba(239,68,68,0.08)');
        $osClass = strtolower($os);
    @endphp
    <div style="background:{{ $osBg }}; border:2px solid {{ $osColor }}; border-radius:12px; padding:24px; margin-bottom:28px;">
        <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
            <div>
                <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.1em; color:#8892a4; margin-bottom:4px;">Overall Status</div>
                <div style="font-size:32px; font-weight:900; color:{{ $osColor }};">{{ $os }}</div>
            </div>
            <div style="flex:1; min-width:200px;">
                <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.1em; color:#8892a4; margin-bottom:4px;">Installation Certification</div>
                <div style="font-size:14px; font-weight:700; color:{{ $osColor }}; font-family:monospace;">{{ $cert }}</div>
            </div>
            <div>
                <div style="font-size:11px; color:#8892a4; margin-bottom:4px;">Health Score</div>
                <div style="font-size:22px; font-weight:800; color:{{ ($data['health_score'] ?? 0) >= 80 ? '#22c55e' : '#f59e0b' }};">{{ $data['health_score'] ?? 0 }}/100</div>
            </div>
        </div>
    </div>

    {{-- Signature --}}
    <div class="signature">
        <div class="section-title" style="border:none; padding:0; margin:0 0 16px;">&#x1F512; Digital Signature &amp; Certification</div>
        <div class="signature-grid">
            <div class="sig-row">
                <div class="sig-key">Installation UUID</div>
                <div class="sig-val">{{ $data['installation_id'] }}</div>
            </div>
            <div class="sig-row">
                <div class="sig-key">Generated By</div>
                <div class="sig-val">{{ $data['generated_by'] }}</div>
            </div>
            <div class="sig-row">
                <div class="sig-key">Generated At (Jalali)</div>
                <div class="sig-val">{{ $data['install_date_jalali'] }} {{ $data['install_time'] }}</div>
            </div>
            <div class="sig-row">
                <div class="sig-key">Generated At (UTC)</div>
                <div class="sig-val">{{ $data['generated_at']->toISOString() }}</div>
            </div>
        </div>
        <div class="sig-row" style="margin-top:16px;">
            <div class="sig-key">Report SHA-256 Checksum</div>
            <div class="checksum">{{ $data['checksum'] }}</div>
        </div>
        @if(!empty($data['manifest_checksum']))
        <div class="sig-row" style="margin-top:10px;">
            <div class="sig-key">Manifest SHA-256 Checksum</div>
            <div class="checksum">{{ $data['manifest_checksum'] }}</div>
        </div>
        @endif
        <div style="margin-top:16px; font-size:11px; color:#8892a4;">
            This report is auto-generated and permanently stored at
            <code>storage/app/installation-reports/</code>.
            Baseline snapshot stored at <code>storage/upgrade_snapshots/{{ $data['baseline_label'] ?? 'INITIAL_INSTALLATION_BASELINE' }}/</code>.
            Do not expose passwords, API keys, or private keys through this document.
        </div>
    </div>

</div>
</body>
</html>
