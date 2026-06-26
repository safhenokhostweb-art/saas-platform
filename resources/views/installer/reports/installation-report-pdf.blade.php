<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Installation Report</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #1a1a2e;
            background: #fff;
            margin: 0;
            padding: 20px;
        }
        .header {
            border-bottom: 3px solid #4f46e5;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }
        .header-title {
            font-size: 20px;
            font-weight: bold;
            color: #1a1a2e;
        }
        .header-sub {
            font-size: 11px;
            color: #666;
            margin-top: 4px;
        }
        .header-id {
            font-size: 9px;
            color: #4f46e5;
            margin-top: 4px;
        }

        /* Score boxes */
        .scores-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .scores-table td {
            width: 25%;
            text-align: center;
            padding: 12px 8px;
            border: 1px solid #dde;
            background: #f5f5ff;
            vertical-align: middle;
        }
        .score-val  { font-size: 26px; font-weight: bold; line-height: 1.1; }
        .score-lbl  { font-size: 9px; color: #666; text-transform: uppercase; margin-top: 2px; }
        .color-pass { color: #16a34a; }
        .color-warn { color: #d97706; }
        .color-fail { color: #dc2626; }

        /* Section heading */
        .section-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #4f46e5;
            background: #f0f0ff;
            padding: 6px 10px;
            margin-top: 16px;
            margin-bottom: 0;
            border-left: 4px solid #4f46e5;
        }

        /* Info table */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .info-table th {
            width: 38%;
            text-align: left;
            padding: 6px 10px;
            background: #fafafa;
            border: 1px solid #e0e0e0;
            font-weight: 500;
            color: #555;
            font-size: 10px;
        }
        .info-table td {
            padding: 6px 10px;
            background: #fff;
            border: 1px solid #e0e0e0;
            font-family: 'Courier New', monospace;
            font-size: 10px;
            color: #222;
        }

        /* Status badge */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-pass    { background: #dcfce7; color: #16a34a; border: 1px solid #86efac; }
        .badge-warning { background: #fef3c7; color: #d97706; border: 1px solid #fcd34d; }
        .badge-failed  { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }

        /* Check table */
        .check-table { width: 100%; border-collapse: collapse; }
        .check-table th {
            padding: 6px 10px;
            background: #f0f0f0;
            border: 1px solid #ddd;
            font-size: 9px;
            text-align: left;
            color: #555;
            text-transform: uppercase;
        }
        .check-table td {
            padding: 6px 10px;
            border: 1px solid #e0e0e0;
            font-size: 10px;
            vertical-align: middle;
        }
        .check-table tr:nth-child(even) td { background: #fafafa; }

        /* Modules */
        .mod-table { width: 100%; border-collapse: collapse; }
        .mod-table td {
            padding: 5px 10px;
            border: 1px solid #e0e0e0;
            font-family: 'Courier New', monospace;
            font-size: 10px;
        }
        .mod-table tr:nth-child(even) td { background: #f9f9f9; }

        /* Signature */
        .signature {
            margin-top: 20px;
            border: 1px solid #c7c7e0;
            border-top: 3px solid #4f46e5;
            padding: 14px;
            background: #fafaff;
        }
        .sig-title {
            font-size: 10px;
            font-weight: bold;
            color: #4f46e5;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .sig-table { width: 100%; border-collapse: collapse; }
        .sig-table td { padding: 4px 8px; font-size: 9px; vertical-align: top; }
        .sig-label { color: #888; width: 35%; }
        .sig-value { font-family: 'Courier New', monospace; color: #222; }
        .checksum  { font-size: 8px; word-break: break-all; color: #4f46e5; }

        .page-break { page-break-before: always; }
        .footer {
            margin-top: 24px;
            border-top: 1px solid #ddd;
            padding-top: 8px;
            font-size: 9px;
            color: #999;
            text-align: center;
        }
    </style>
</head>
<body>

{{-- Header --}}
<div class="header">
    <div class="header-title">Installation Report &mdash; {{ $data['project_name'] }}</div>
    <div class="header-sub">
        Environment: {{ strtoupper($data['environment']) }} &bull;
        Domain: {{ $data['domain'] }} &bull;
        Server IP: {{ $data['server_ip'] }}
    </div>
    <div class="header-sub">
        Installed: {{ $data['install_date_jalali'] }} at {{ $data['install_time'] }}
    </div>
    <div class="header-id">Installation ID: {{ $data['installation_id'] }}</div>
</div>

{{-- Scores --}}
@php
    $hs = $data['health_score'] ?? 0;
    $dr = $data['dr_score'] ?? 0;
    $hsClass = $hs >= 80 ? 'color-pass' : ($hs >= 50 ? 'color-warn' : 'color-fail');
    $drClass = $dr >= 80 ? 'color-pass' : ($dr >= 50 ? 'color-warn' : 'color-fail');
    $allChecks = [
        $data['redis_status'], $data['queue_status'], $data['scheduler_status'],
        $data['supervisor_status'], $data['reverb_status'], $data['storage_status'],
        $data['mail_status'], $data['arvan_status'], $data['license_status'], $data['backup_status']
    ];
    $pass    = count(array_filter($allChecks, fn($c) => ($c['status'] ?? '') === 'pass'));
    $warning = count(array_filter($allChecks, fn($c) => ($c['status'] ?? '') === 'warning'));
    $failed  = count(array_filter($allChecks, fn($c) => ($c['status'] ?? '') === 'failed'));
@endphp
<table class="scores-table">
    <tr>
        <td>
            <div class="score-val {{ $hsClass }}">{{ $hs }}</div>
            <div class="score-lbl">Overall Health Score</div>
        </td>
        <td>
            <div class="score-val {{ $drClass }}">{{ $dr }}</div>
            <div class="score-lbl">DR Readiness Score</div>
        </td>
        <td>
            <div class="score-val color-pass">{{ $pass }}</div>
            <div class="score-lbl">PASS (of {{ count($allChecks) }})</div>
        </td>
        <td>
            <div class="score-val {{ $warning > 0 ? 'color-warn' : 'color-pass' }}">{{ $warning }}</div>
            <div class="score-lbl">WARNINGS / {{ $failed }} FAILED</div>
        </td>
    </tr>
</table>

{{-- Project Information --}}
<div class="section-title">Project Information</div>
<table class="info-table">
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

{{-- System Versions --}}
<div class="section-title">System Versions</div>
<table class="info-table">
    <tr><th>PHP Version</th><td>{{ $data['php_version'] }}</td></tr>
    <tr><th>Laravel Version</th><td>{{ $data['laravel_version'] }}</td></tr>
    <tr><th>Composer Version</th><td>{{ $data['composer_version'] }}</td></tr>
    <tr><th>Node.js Version</th><td>{{ $data['node_version'] }}</td></tr>
    <tr><th>Database Engine</th><td>{{ $data['db_engine'] }}</td></tr>
    <tr><th>Database Version</th><td>{{ $data['db_version'] }}</td></tr>
</table>

{{-- Super Admin (no passwords) --}}
<div class="section-title">Super Administrator</div>
<table class="info-table">
    <tr><th>Name</th><td>{{ $data['super_admin']['name'] }}</td></tr>
    <tr><th>Mobile / Contact</th><td>{{ $data['super_admin']['mobile'] }}</td></tr>
</table>

{{-- Infrastructure Status --}}
<div class="section-title">Infrastructure Status</div>
<table class="check-table">
    <thead>
        <tr>
            <th style="width:30%">Component</th>
            <th style="width:20%">Status</th>
            <th>Details</th>
        </tr>
    </thead>
    <tbody>
        @foreach([
            $data['redis_status'],      $data['queue_status'],
            $data['scheduler_status'],  $data['supervisor_status'],
            $data['reverb_status'],     $data['storage_status'],
            $data['mail_status'],       $data['arvan_status'],
            $data['license_status'],    $data['backup_status'],
        ] as $chk)
        <tr>
            <td><strong>{{ $chk['label'] ?? '' }}</strong></td>
            <td>
                <span class="badge badge-{{ $chk['status'] === 'pass' ? 'pass' : ($chk['status'] === 'warning' ? 'warning' : 'failed') }}">
                    {{ strtoupper($chk['status'] ?? 'N/A') }}
                </span>
            </td>
            <td>{{ $chk['value'] ?? '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- Installed Modules --}}
@if(!empty($data['installed_modules']))
<div class="section-title">Installed Modules</div>
<table class="mod-table">
    <tr style="background:#f0f0f0;">
        <th style="padding:5px 10px; border:1px solid #ddd; text-align:left; font-size:9px;">Module</th>
        <th style="padding:5px 10px; border:1px solid #ddd; text-align:left; font-size:9px;">Version</th>
        <th style="padding:5px 10px; border:1px solid #ddd; text-align:left; font-size:9px;">Status</th>
    </tr>
    @foreach($data['installed_modules'] as $mod)
    <tr>
        <td>{{ $mod['name'] }}</td>
        <td>{{ $mod['version'] }}</td>
        <td><span class="badge badge-pass">{{ strtoupper($mod['status']) }}</span></td>
    </tr>
    @endforeach
</table>
@endif

{{-- Signature --}}
<div class="signature">
    <div class="sig-title">Digital Signature &amp; Certification</div>
    <table class="sig-table">
        <tr>
            <td class="sig-label">Installation ID</td>
            <td class="sig-value">{{ $data['installation_id'] }}</td>
        </tr>
        <tr>
            <td class="sig-label">Generated By</td>
            <td class="sig-value">{{ $data['generated_by'] }}</td>
        </tr>
        <tr>
            <td class="sig-label">Generated At (Jalali)</td>
            <td class="sig-value">{{ $data['install_date_jalali'] }} {{ $data['install_time'] }}</td>
        </tr>
        <tr>
            <td class="sig-label">Generated At (UTC)</td>
            <td class="sig-value">{{ $data['generated_at']->toISOString() }}</td>
        </tr>
        <tr>
            <td class="sig-label">SHA-256 Checksum</td>
            <td class="sig-value checksum">{{ $data['checksum'] }}</td>
        </tr>
    </table>
</div>

<div class="footer">
    CONFIDENTIAL &bull; Do not expose passwords, API keys, or private keys through this document.
    &bull; Stored at: storage/app/installation-reports/
</div>

</body>
</html>
