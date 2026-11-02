import type { FullConfig, FullResult, Reporter, Suite, TestCase, TestResult } from '@playwright/test/reporter';
import fs from 'node:fs';
import path from 'node:path';
import { sentinelEnv } from './env';
import { REMEDIES, type SentinelFinding } from './findings';

/**
 * ADR-077 — merges the workers' findings and the failed checks into
 * report.json (schema sentinel.ui-report/v1) and report.md in
 * SENTINEL_OUTPUT_DIR. Findings with severity warning or blocker become
 * tasks, one per (code, widget type, remedy), listing every page and width
 * where they occur.
 */

const RANK = { blocker: 0, warning: 1, info: 2 } as const;

interface Task {
  id: string;
  severity: SentinelFinding['severity'];
  code: string;
  widget: string | null;
  title: string;
  change: string;
  where: string[];
  occurrences: number;
}

export default class SentinelReporter implements Reporter {
  private readonly env = sentinelEnv();
  private readonly started = new Date();
  private readonly results: Array<{ title: string; status: string; error: string }> = [];

  onBegin(_config: FullConfig, _suite: Suite): void {
    fs.rmSync(path.join(this.env.outputDir, 'findings'), { recursive: true, force: true });
    fs.mkdirSync(this.env.outputDir, { recursive: true });
  }

  onTestEnd(test: TestCase, result: TestResult): void {
    this.results.push({ title: test.title, status: result.status, error: (result.error?.message ?? '').replace(/\u001b\[[0-9;]*m/g, '').slice(0, 600) });
  }

  onEnd(result: FullResult): void {
    const findings = this.readFindings();
    for (const r of this.results.filter((r) => r.status === 'failed' || r.status === 'timedOut')) {
      const visual = r.title.startsWith('visual ');
      const missing = /missing|doesn't exist|writing actual/i.test(r.error);
      const code = visual ? (missing ? 'VISUAL_BASELINE_MISSING' : 'VISUAL_DIFF') : 'SENTINEL_CHECK_ERROR';
      findings.push({
        code, severity: visual && missing ? 'info' : 'warning', page: r.title, width: null, widget: null,
        message: `${r.title}: ${r.status}${r.error ? ' — ' + r.error.split('\n')[0] : ''}`, remedy: REMEDIES[code] ?? '',
      });
    }
    findings.sort((a, b) => RANK[a.severity] - RANK[b.severity] || a.code.localeCompare(b.code) || a.page.localeCompare(b.page) || (a.width ?? 0) - (b.width ?? 0));

    const counts = { blocker: 0, warning: 0, info: 0 };
    findings.forEach((f) => counts[f.severity]++);
    const verdict = counts.blocker > 0 ? 'BLOCKED' : counts.warning > 0 ? 'ATTENTION' : 'READY';
    const tests = {
      passed: this.results.filter((r) => r.status === 'passed').length,
      failed: this.results.filter((r) => r.status === 'failed' || r.status === 'timedOut').length,
      skipped: this.results.filter((r) => r.status === 'skipped').length,
    };
    const tasks = this.tasks(findings);
    const report = {
      schema: 'sentinel.ui-report/v1',
      base_url: this.env.baseUrl,
      project: this.env.project,
      environment: this.env.environment,
      started_at: this.started.toISOString(),
      finished_at: new Date().toISOString(),
      run_status: result.status,
      pages: this.env.pages,
      widths: this.env.widths,
      options: { visual: this.env.visual, editor: !!this.env.storageState && this.env.editorPaths.length > 0 },
      verdict,
      counts,
      tests,
      tasks,
      findings,
    };
    fs.writeFileSync(path.join(this.env.outputDir, 'report.json'), JSON.stringify(report, null, 2));
    fs.writeFileSync(path.join(this.env.outputDir, 'report.md'), this.markdown(report, tasks));
    console.log(`Sentinel: ${verdict} — ${counts.blocker} blocker(s), ${counts.warning} warning(s), ${counts.info} info; ${tasks.length} task(s). Report: ${path.join(this.env.outputDir, 'report.md')}`);
  }

  private readFindings(): SentinelFinding[] {
    const dir = path.join(this.env.outputDir, 'findings');
    if (!fs.existsSync(dir)) return [];
    const seen = new Set<string>();
    const out: SentinelFinding[] = [];
    for (const file of fs.readdirSync(dir).filter((f) => f.endsWith('.jsonl'))) {
      for (const line of fs.readFileSync(path.join(dir, file), 'utf8').split('\n')) {
        if (!line.trim()) continue;
        const f = JSON.parse(line) as SentinelFinding;
        const key = [f.code, f.page, f.width, f.widget, f.selector, f.message].join('|');
        if (!seen.has(key)) {
          seen.add(key);
          out.push(f);
        }
      }
    }
    return out;
  }

  private tasks(findings: SentinelFinding[]): Task[] {
    const groups = new Map<string, Task>();
    for (const f of findings.filter((f) => f.severity !== 'info')) {
      const widgetType = f.widget ? f.widget.split('#')[0] : null;
      const key = [f.code, widgetType, f.remedy].join('|');
      const where = `${f.page}${f.width ? ` @ ${f.width}px` : ''}`;
      const task = groups.get(key) ?? { id: '', severity: f.severity, code: f.code, widget: widgetType, title: f.message, change: f.remedy, where: [], occurrences: 0 };
      if (RANK[f.severity] < RANK[task.severity]) task.severity = f.severity;
      if (!task.where.includes(where)) task.where.push(where);
      task.occurrences++;
      groups.set(key, task);
    }
    return [...groups.values()]
      .sort((a, b) => RANK[a.severity] - RANK[b.severity] || b.occurrences - a.occurrences)
      .map((t, i) => ({ ...t, id: `T-${String(i + 1).padStart(3, '0')}` }));
  }

  private markdown(report: any, tasks: Task[]): string {
    const lines = [
      `# Sentinel UI report: ${report.verdict}`,
      '',
      `- Target: ${report.base_url} (${report.project}, ${report.environment})`,
      `- Run: ${report.started_at} → ${report.finished_at}`,
      `- Pages: ${report.pages.join(', ')}; widths: ${report.widths.join(', ')} px`,
      `- Findings: ${report.counts.blocker} blocker, ${report.counts.warning} warning, ${report.counts.info} info`,
      `- Checks: ${report.tests.passed} passed, ${report.tests.failed} failed, ${report.tests.skipped} skipped`,
      '',
      '## Tasks',
      '',
    ];
    if (tasks.length === 0) lines.push('No changes required.', '');
    for (const t of tasks) {
      lines.push(`### ${t.id} [${t.severity}] ${t.code}${t.widget ? ` — ${t.widget}` : ''}`, '', `- Problem: ${t.title}`, `- Change: ${t.change}`, `- Where (${t.occurrences}×): ${t.where.slice(0, 12).join('; ')}${t.where.length > 12 ? '; …' : ''}`, '');
    }
    const infos = report.findings.filter((f: SentinelFinding) => f.severity === 'info');
    if (infos.length > 0) {
      lines.push('## Notes', '');
      for (const f of infos.slice(0, 50)) lines.push(`- ${f.code}: ${f.message} (${f.page}${f.width ? ` @ ${f.width}px` : ''})`);
      lines.push('');
    }
    return lines.join('\n');
  }
}
