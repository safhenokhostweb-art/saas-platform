/**
 * ADR-077 — the in-page audit. pageAudit() is serialised into the page by
 * page.evaluate(), so it must stay self-contained: no imports, no closures.
 *
 * Overflow uses the WU-D gate's rule (tests/Support/CssParity/overflow.cjs):
 * a culprit is an element crossing the viewport edge while no ancestor clips
 * it, and only the outermost culprit of a branch is listed.
 */
export interface AuditInput {
  profiles: Record<string, Array<{ selector: string; code: string; severity: string; message: string; remedy: string }>>;
  global: Array<{ forbid: string; code: string; severity: string; message: string; remedy: string }>;
  touch: boolean;
}

export interface AuditOutput {
  dir: string;
  lang: string;
  vazirmatn: boolean;
  overflow: number;
  culprits: Array<{ selector: string; widget: string | null; left: number; right: number }>;
  widgetOverflow: Array<{ selector: string; widget: string; by: number }>;
  widgets: string[];
  brokenImages: Array<{ src: string; widget: string | null }>;
  clipped: Array<{ selector: string; widget: string | null; text: string }>;
  smallTargets: Array<{ selector: string; widget: string | null; width: number; height: number }>;
  profileMisses: Array<{ widget: string; code: string; severity: string; message: string; remedy: string; selector: string }>;
  forbidden: Array<{ widget: string; code: string; severity: string; message: string; remedy: string; selector: string; count: number }>;
}

export function pageAudit(input: AuditInput): AuditOutput {
  const describe = (el: Element): string => {
    const tag = el.tagName.toLowerCase();
    if (el.id) return `${tag}#${el.id}`;
    const cls = typeof (el as HTMLElement).className === 'string' ? (el as HTMLElement).className.trim().split(/\s+/).filter(Boolean).slice(0, 3) : [];
    return tag + (cls.length ? '.' + cls.join('.') : '');
  };
  const widgetOf = (el: Element): string | null => {
    const w = el.closest('[data-widget-type]');
    if (!w) return null;
    const id = w.getAttribute('data-tb-block-id');
    return w.getAttribute('data-widget-type') + (id ? `#${id}` : '');
  };
  const visible = (el: Element): boolean => {
    const cs = getComputedStyle(el);
    if (cs.display === 'none' || cs.visibility === 'hidden' || Number(cs.opacity) === 0) return false;
    // Screen-reader-only content (.visually-hidden / .sr-only) is not seen.
    if (cs.clip && cs.clip !== 'auto') return false;
    const r = el.getBoundingClientRect();
    return r.width > 2 && r.height > 2;
  };
  const clips = (el: Element): boolean => ['hidden', 'clip', 'auto', 'scroll'].includes(getComputedStyle(el).overflowX);

  const vw = document.documentElement.clientWidth;
  const overflow = Math.max(0, document.documentElement.scrollWidth - vw);
  const culprits: AuditOutput['culprits'] = [];
  if (overflow > 1) {
    const walk = (el: Element, clipped: boolean): void => {
      for (const child of Array.from(el.children)) {
        const cs = getComputedStyle(child);
        if (cs.display === 'none' || cs.position === 'fixed') continue;
        const r = child.getBoundingClientRect();
        if (!clipped && r.width > 0 && (r.right > vw + 1 || r.left < -1)) {
          culprits.push({ selector: describe(child), widget: widgetOf(child), left: Math.round(r.left), right: Math.round(r.right) });
          continue;
        }
        walk(child, clipped || clips(child));
      }
    };
    walk(document.body, false);
  }

  const roots = Array.from(document.querySelectorAll('[data-widget-type]'));
  const widgetOverflow: AuditOutput['widgetOverflow'] = [];
  for (const root of roots) {
    if (!visible(root)) continue;
    const box = root.getBoundingClientRect();
    const walk = (el: Element, depth: number): void => {
      if (depth > 12) return;
      for (const child of Array.from(el.children)) {
        if (child.matches('[data-widget-type]')) continue; // nested widgets are audited on their own
        const cs = getComputedStyle(child);
        if (cs.display === 'none' || cs.position === 'absolute' || cs.position === 'fixed') continue;
        const r = child.getBoundingClientRect();
        const by = Math.max(r.right - box.right, box.left - r.left);
        if (r.width > 0 && by > 2) {
          widgetOverflow.push({ selector: describe(child), widget: widgetOf(root) as string, by: Math.round(by) });
          continue;
        }
        if (!clips(child)) walk(child, depth + 1);
      }
    };
    if (!clips(root)) walk(root, 0);
  }

  const brokenImages = Array.from(document.images)
    .filter((img) => img.complete && img.naturalWidth === 0 && (img.currentSrc || img.src) && visible(img))
    .map((img) => ({ src: (img.currentSrc || img.src).slice(0, 300), widget: widgetOf(img) }));

  const clipped: AuditOutput['clipped'] = [];
  for (const el of Array.from(document.querySelectorAll('body *'))) {
    if (clipped.length >= 20) break;
    const cs = getComputedStyle(el);
    if (cs.whiteSpace !== 'nowrap' || cs.overflowX !== 'hidden' || cs.textOverflow === 'ellipsis') continue;
    const text = Array.from(el.childNodes).filter((n) => n.nodeType === 3).map((n) => n.textContent || '').join('').trim();
    const html = el as HTMLElement;
    if (text && html.scrollWidth > html.clientWidth + 1 && visible(el) && !el.getAttribute('title')) {
      clipped.push({ selector: describe(el), widget: widgetOf(el), text: text.slice(0, 80) });
    }
  }

  // WCAG 2.2 SC 2.5.8 (target size, minimum): a target under 24×24 px still
  // passes when a 24 px circle centred on it intersects no other target and
  // no other undersized target's circle (the spacing exception). Inline
  // links in running text are exempt.
  const smallTargets: AuditOutput['smallTargets'] = [];
  if (input.touch) {
    const targets = Array.from(document.querySelectorAll('a[href], button, input:not([type="hidden"]), select, [role="button"]'))
      .filter((el) => visible(el) && !el.closest('p, li > p, [data-tb-inline-text]'))
      .map((el) => ({ el, r: el.getBoundingClientRect() }));
    const centre = (r: DOMRect) => ({ x: r.left + r.width / 2, y: r.top + r.height / 2 });
    const circleHitsRect = (c: { x: number; y: number }, r: DOMRect) => {
      const dx = Math.max(r.left - c.x, 0, c.x - r.right);
      const dy = Math.max(r.top - c.y, 0, c.y - r.bottom);
      return dx * dx + dy * dy < 12 * 12;
    };
    const undersized = targets.filter((t) => t.r.width < 24 || t.r.height < 24);
    for (const t of undersized) {
      if (smallTargets.length >= 30) break;
      const c = centre(t.r);
      const crowded = targets.some((o) => o.el !== t.el && !o.el.contains(t.el) && !t.el.contains(o.el) && (
        circleHitsRect(c, o.r)
        || ((o.r.width < 24 || o.r.height < 24) && Math.hypot(centre(o.r).x - c.x, centre(o.r).y - c.y) < 24)
      ));
      if (crowded) {
        smallTargets.push({ selector: describe(t.el), widget: widgetOf(t.el), width: Math.round(t.r.width), height: Math.round(t.r.height) });
      }
    }
  }

  const profileMisses: AuditOutput['profileMisses'] = [];
  const forbidden: AuditOutput['forbidden'] = [];
  const seen = new Set<string>();
  for (const root of roots) {
    const type = root.getAttribute('data-widget-type') || '';
    if (!visible(root) || seen.has(type)) continue;
    seen.add(type);
    for (const rule of input.profiles[type] || []) {
      if (!root.querySelector(rule.selector)) {
        profileMisses.push({ widget: widgetOf(root) as string, ...rule });
      }
    }
    for (const rule of input.global) {
      const hits = Array.from(root.querySelectorAll(rule.forbid)).filter(visible);
      if (hits.length > 0) {
        forbidden.push({ widget: widgetOf(root) as string, code: rule.code, severity: rule.severity, message: rule.message, remedy: rule.remedy, selector: describe(hits[0]), count: hits.length });
      }
    }
  }

  const vazirmatn = Array.from((document as any).fonts || []).some((f: any) => /vazir/i.test(f.family) && f.status === 'loaded');

  return {
    dir: (document.documentElement.getAttribute('dir') || getComputedStyle(document.documentElement).direction || '').toLowerCase(),
    lang: (document.documentElement.getAttribute('lang') || '').toLowerCase(),
    vazirmatn,
    overflow,
    culprits: culprits.slice(0, 8),
    widgetOverflow: widgetOverflow.slice(0, 12),
    widgets: Array.from(new Set(roots.map((r) => r.getAttribute('data-widget-type') || ''))),
    brokenImages: brokenImages.slice(0, 20),
    clipped,
    smallTargets,
    profileMisses,
    forbidden,
  };
}
