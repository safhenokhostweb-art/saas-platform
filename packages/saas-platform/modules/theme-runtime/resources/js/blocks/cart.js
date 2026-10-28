/*
 * Phase 1/2 (ADR-067) — the live cart behaviour shared by the header cart
 * icon (mini-cart drawer) and product cards (instant add-to-cart). Inlined
 * once per render by BlockRenderer::cartAssets(), only when the host gave
 * cart endpoints; the Builder preview never loads it.
 *
 * Host contract (customer-site ShopController, all JSON with Accept: json):
 *   GET  mini   → { count, subtotal_html, money_suffix_html?, unit?,
 *                   items: [{ key, title, image, quantity, total_html, unit_amount? }] }
 *   POST add    product_id, quantity[, product_variant_id] → { ok, message, cart } | 422 { ok:false, message[, url] }
 *   POST update line_key, quantity → { ok, message, cart } | 422 { ok:false, message }   (Phase 3)
 *   POST remove line_key → { ok, message, cart }
 * Endpoints come from the nearest/first [data-tb-cart] element.
 *
 * Phase 3 (ADR-068): a quantity stepper per line. A step repaints the line
 * total, subtotal and badge at once from `unit_amount` (the host's display
 * amount, the number its own formatter prints), then the last quantity per
 * line is sent after a short pause and the host's answer replaces the
 * estimate. Removing — the trash button, or «−» at one — asks first, inline.
 *
 * Pure helpers are exposed on window.TbCart for the Node tests.
 */
(function (root) {
  'use strict';
  if (root.TbCart && root.TbCart.version === 2) return;

  const SAFE_URL = /^(https?:\/\/|\/)/i;

  /* «۱٬۲۵۰٬۰۰۰» — the same digits and ٬ separator as the host's PersianNumber::format(). */
  function faNumber(n) {
    return Number(n || 0).toLocaleString('fa-IR');
  }

  /** «۳ کالا» / «سبد خالی است». */
  function countLabel(count) {
    const n = Math.max(0, Math.floor(Number(count) || 0));
    return n > 0 ? faNumber(n) + ' کالا' : 'سبد خالی است';
  }

  /*
   * The mini-cart payload made safe to render: titles stay text, images
   * must be http(s)/root-relative, and the money HTML may only carry digits,
   * Persian separators, the Toman word and the host's faded-zeros span.
   */
  const MONEY_HTML = /^[\s۰-۹0-9٬٫,.٬٫تومانریال]*(<span class="currency-faded-zeros"[^<>]*>[۰-۹0-9]*<\/span>)?[\s۰-۹0-9٬٫,.٬٫تومانریال]*$/;
  function money(html) {
    const s = typeof html === 'string' ? html : '';
    return MONEY_HTML.test(s) ? s : '';
  }
  function amount(v) {
    const n = typeof v === 'number' ? v : (typeof v === 'string' && v.trim() !== '' ? Number(v) : NaN);
    return Number.isFinite(n) && n >= 0 ? n : null;
  }
  function normalizeMini(data) {
    const d = data && typeof data === 'object' ? data : {};
    const items = (Array.isArray(d.items) ? d.items : []).filter((it) => it && typeof it.key === 'string' && typeof it.title === 'string').map((it) => ({
      key: it.key,
      title: it.title,
      image: typeof it.image === 'string' && SAFE_URL.test(it.image) ? it.image : '',
      quantity: Math.max(1, Math.floor(Number(it.quantity) || 1)),
      totalHtml: money(it.total_html),
      unitAmount: amount(it.unit_amount),
    }));
    const suffix = typeof d.money_suffix_html === 'string' && /^<span class="currency-faded-zeros"[^<>]*>[۰-۹0-9]*<\/span>$/.test(d.money_suffix_html) ? d.money_suffix_html : '';
    const unit = d.unit === 'تومان' || d.unit === 'ریال' ? d.unit : 'تومان';
    return { count: Math.max(0, Math.floor(Number(d.count) || 0)), subtotalHtml: money(d.subtotal_html), suffix, unit, items };
  }

  /* «۲٬۵۰۰٬۰۰۰ تومان» for a display amount — rounded like the host's formatter. */
  function formatMoney(value, suffix, unit) {
    return faNumber(Math.round(Number(value) || 0)) + (suffix || '') + ' ' + (unit || 'تومان');
  }

  /*
   * Recomputes the line totals, subtotal and count of a normalized cart
   * after a local quantity change. A line without unit_amount keeps the
   * host's figure, and then the subtotal is left as it is too.
   */
  function recalc(cart) {
    let count = 0;
    let sum = 0;
    let exact = true;
    cart.items.forEach((it) => {
      count += it.quantity;
      if (it.unitAmount === null) { exact = false; return; }
      sum += it.unitAmount * it.quantity;
      it.totalHtml = formatMoney(it.unitAmount * it.quantity, cart.suffix, cart.unit);
    });
    cart.count = count;
    if (exact) cart.subtotalHtml = cart.items.length ? formatMoney(sum, cart.suffix, cart.unit) : '';
    return cart;
  }

  const api = { version: 2, countLabel, normalizeMini, money, faNumber, formatMoney, recalc };
  root.TbCart = api;
  if (typeof module !== 'undefined' && module.exports) module.exports = api;

  const document = root.document;
  if (!document || typeof document.addEventListener !== 'function') return;

  function config(from) {
    const el = (from && from.closest && from.closest('[data-tb-cart]')) || document.querySelector('[data-tb-cart]');
    try {
      const c = JSON.parse(el ? el.getAttribute('data-tb-cart') : '{}');
      return c && typeof c === 'object' ? c : {};
    } catch (_) {
      return {};
    }
  }

  function csrfHeaders() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta && meta.content ? { 'X-CSRF-TOKEN': meta.content } : {};
  }

  function request(url, body) {
    return fetch(url, {
      method: body ? 'POST' : 'GET',
      body: body || undefined,
      credentials: 'same-origin',
      headers: Object.assign({ Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body ? csrfHeaders() : {}),
    }).then((res) => res.json().catch(() => ({})).then((json) => ({ status: res.status, json })));
  }

  function toast(text, kind) {
    let region = document.querySelector('.tb-toast-region');
    if (!region) {
      region = document.createElement('div');
      region.className = 'tb-toast-region';
      region.setAttribute('role', 'status');
      region.setAttribute('aria-live', 'polite');
      document.body.appendChild(region);
    }
    const el = document.createElement('div');
    el.className = 'tb-toast tb-toast--' + (kind === 'error' ? 'error' : 'success');
    el.textContent = text;
    region.appendChild(el);
    requestAnimationFrame(() => el.classList.add('is-visible'));
    setTimeout(() => { el.classList.remove('is-visible'); setTimeout(() => el.remove(), 300); }, 4000);
  }

  function setBadges(count) {
    document.querySelectorAll('[data-tb-cart-count]').forEach((b) => {
      b.textContent = faNumber(count);
      b.hidden = count <= 0;
    });
    document.querySelectorAll('[data-tb-cart-open]').forEach((t) => t.setAttribute('aria-label', 'سبد خرید، ' + countLabel(count)));
  }

  /* ── Drawer ──────────────────────────────────────────────────────── */
  const SVG_OPEN = '<svg class="tb-icon-svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">';
  const X_SVG = SVG_OPEN + '<path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';
  const TRASH_SVG = SVG_OPEN + '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>';
  const PLUS_SVG = SVG_OPEN + '<path d="M5 12h14"/><path d="M12 5v14"/></svg>';
  const MINUS_SVG = SVG_OPEN + '<path d="M5 12h14"/></svg>';
  const STEP_DELAY = 350;
  let drawer = null;
  let opener = null;
  let cfg = {};
  let model = null; // the normalized cart the drawer shows
  const pending = new Map(); // line key → quantity not sent yet
  let inflight = null; // line key being saved
  let flushTimer = null;
  let confirmingKey = null;

  /*
   * One cart change at a time: the host keeps the cart in the session, and
   * two overlapping writes would let the later one drop the earlier.
   */
  let chain = Promise.resolve();
  function serial(task) {
    const run = chain.then(task, task);
    chain = run.catch(() => {});
    return run;
  }

  function build() {
    const el = document.createElement('div');
    el.className = 'tb-cart-drawer';
    el.hidden = true;
    el.innerHTML = '<div class="tb-cart-drawer__backdrop" data-tb-cart-close></div>'
      + '<aside class="tb-cart-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="tb-cart-drawer-title">'
      + '<div class="tb-cart-drawer__head"><h2 id="tb-cart-drawer-title">سبد خرید</h2><span class="tb-cart-drawer__count"></span>'
      + '<button type="button" class="tb-cart-drawer__close" data-tb-cart-close aria-label="بستن سبد خرید">' + X_SVG + '</button></div>'
      + '<div class="tb-cart-drawer__body" aria-live="polite"></div>'
      + '<div class="tb-cart-drawer__foot" hidden><div class="tb-cart-drawer__subtotal"><span>جمع کل</span><strong></strong></div>'
      + '<a class="tb-btn tb-btn-primary tb-cart-drawer__checkout">مشاهدهٔ سبد و تکمیل خرید</a>'
      + '<button type="button" class="tb-cart-drawer__continue" data-tb-cart-close>ادامهٔ خرید</button></div>'
      + '</aside>';
    document.body.appendChild(el);
    el.addEventListener('click', (e) => {
      if (e.target.closest('[data-tb-cart-close]')) { close(); return; }
      const checkout = e.target.closest('.tb-cart-drawer__checkout');
      if (checkout && (pending.size || inflight)) {
        // Save the last steps before leaving for the cart page.
        e.preventDefault();
        flush().then(() => location.assign(checkout.href));
        return;
      }
      const stepper = e.target.closest('[data-tb-cart-step]');
      if (stepper) { step(lineKey(stepper), Number(stepper.getAttribute('data-tb-cart-step'))); return; }
      if (e.target.closest('[data-tb-cart-remove]')) { askRemove(lineKey(e.target), true); return; }
      if (e.target.closest('[data-tb-cart-confirm]')) { remove(lineKey(e.target)); return; }
      if (e.target.closest('[data-tb-cart-cancel]')) cancelRemove(true);
    });
    el.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        if (confirmingKey !== null) cancelRemove(true); else close();
        return;
      }
      if (e.key !== 'Tab') return;
      const f = Array.from(el.querySelectorAll('button, a[href]')).filter((n) => n.offsetParent !== null);
      if (!f.length) return;
      if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
    });
    return el;
  }

  function lineKey(node) {
    const li = node && node.closest && node.closest('.tb-cart-drawer__item');
    return li ? li.getAttribute('data-key') : null;
  }
  function findLine(key) {
    return drawer ? Array.from(drawer.querySelectorAll('.tb-cart-drawer__item')).find((li) => li.getAttribute('data-key') === key) || null : null;
  }

  function status(text) {
    drawer.querySelector('.tb-cart-drawer__body').innerHTML = '<p class="tb-cart-drawer__status"></p>';
    drawer.querySelector('.tb-cart-drawer__status').textContent = text;
  }

  function button(className, label, svg, attr, value) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = className;
    b.setAttribute('aria-label', label);
    b.setAttribute(attr, value);
    b.innerHTML = svg;
    return b;
  }

  function lineEl(it) {
    const li = document.createElement('li');
    li.className = 'tb-cart-drawer__item' + (pending.has(it.key) || inflight === it.key ? ' is-saving' : '');
    li.setAttribute('data-key', it.key);
    const media = document.createElement('span');
    media.className = 'tb-cart-drawer__media';
    if (it.image) {
      const img = document.createElement('img');
      img.src = it.image;
      img.alt = '';
      img.loading = 'lazy';
      media.appendChild(img);
    }
    const text = document.createElement('span');
    text.className = 'tb-cart-drawer__text';
    const title = document.createElement('span');
    title.className = 'tb-cart-drawer__title';
    title.textContent = it.title;
    const row = document.createElement('span');
    row.className = 'tb-cart-drawer__row';
    if (cfg.update) {
      const qty = document.createElement('span');
      qty.className = 'tb-qty';
      qty.setAttribute('role', 'group');
      qty.setAttribute('aria-label', 'تعداد «' + it.title + '»');
      const value = document.createElement('span');
      value.className = 'tb-qty__value';
      value.textContent = faNumber(it.quantity);
      const less = it.quantity > 1
        ? button('tb-qty__btn', 'کاهش تعداد', MINUS_SVG, 'data-tb-cart-step', '-1')
        : button('tb-qty__btn tb-qty__btn--remove', 'حذف «' + it.title + '» از سبد', TRASH_SVG, 'data-tb-cart-step', '-1');
      qty.append(button('tb-qty__btn', 'افزایش تعداد', PLUS_SVG, 'data-tb-cart-step', '1'), value, less);
      row.appendChild(qty);
    } else {
      const meta = document.createElement('span');
      meta.className = 'tb-cart-drawer__meta';
      meta.textContent = 'تعداد: ' + faNumber(it.quantity);
      row.appendChild(meta);
    }
    const price = document.createElement('span');
    price.className = 'tb-cart-drawer__price';
    price.innerHTML = it.totalHtml; // money() allowlisted
    row.appendChild(price);
    text.append(title, row);
    li.append(media, text, button('tb-cart-drawer__remove', 'حذف «' + it.title + '» از سبد', TRASH_SVG, 'data-tb-cart-remove', it.key));
    return li;
  }

  /* The focused control inside a line, so a repaint can put focus back. */
  const FOCUS_ATTRS = ['data-tb-cart-step', 'data-tb-cart-remove', 'data-tb-cart-confirm', 'data-tb-cart-cancel'];
  function focusMemo() {
    const a = document.activeElement;
    if (!a || !drawer.contains(a)) return null;
    const attr = FOCUS_ATTRS.find((n) => a.hasAttribute(n));
    const key = lineKey(a);
    return attr && key !== null ? { key, attr, value: a.getAttribute(attr) } : null;
  }
  function restoreFocus(memo) {
    const li = memo && findLine(memo.key);
    const target = li && Array.from(li.querySelectorAll('[' + memo.attr + ']')).find((n) => n.getAttribute(memo.attr) === memo.value);
    if (target) target.focus({ preventScroll: true });
  }

  function paint() {
    if (!drawer || !model) return;
    drawer.querySelector('.tb-cart-drawer__count').textContent = countLabel(model.count);
    const foot = drawer.querySelector('.tb-cart-drawer__foot');
    const body = drawer.querySelector('.tb-cart-drawer__body');
    if (!model.items.length) {
      confirmingKey = null;
      foot.hidden = true;
      body.innerHTML = '<div class="tb-cart-drawer__empty"><p>سبد خرید شما خالی است.</p></div>';
      return;
    }
    const memo = focusMemo();
    const list = document.createElement('ul');
    list.className = 'tb-cart-drawer__items';
    model.items.forEach((it) => list.appendChild(lineEl(it)));
    body.innerHTML = '';
    body.appendChild(list);
    foot.hidden = false;
    foot.querySelector('.tb-cart-drawer__subtotal strong').innerHTML = model.subtotalHtml;
    foot.querySelector('.tb-cart-drawer__checkout').href = cfg.page || '#';
    if (confirmingKey !== null) askRemove(confirmingKey, false);
    restoreFocus(memo);
  }

  function render(raw) {
    model = normalizeMini(raw);
    if (pending.size) {
      // Steps not sent yet still win over an older host answer.
      model.items.forEach((it) => { if (pending.has(it.key)) it.quantity = pending.get(it.key); });
      recalc(model);
    }
    setBadges(model.count);
    paint();
  }

  function markSaving() {
    if (!drawer) return;
    drawer.querySelectorAll('.tb-cart-drawer__item').forEach((li) => {
      const k = li.getAttribute('data-key');
      li.classList.toggle('is-saving', pending.has(k) || inflight === k);
    });
  }

  function refresh() {
    if (!cfg.mini) return Promise.resolve();
    return request(cfg.mini).then(({ status: s, json }) => {
      if (s >= 200 && s < 300) render(json);
      else if (drawer) status('دریافت سبد خرید ممکن نشد؛ دوباره تلاش کنید.');
    }).catch(() => drawer && status('ارتباط با سرور برقرار نشد.'));
  }

  function open(from) {
    cfg = config(from);
    if (!cfg.mini) return false;
    drawer = drawer || build();
    opener = document.activeElement;
    drawer.hidden = false;
    document.documentElement.classList.add('tb-cart-open');
    requestAnimationFrame(() => drawer.classList.add('is-open'));
    if (model) paint(); else status('در حال بارگذاری سبد…');
    drawer.querySelector('.tb-cart-drawer__close').focus();
    // Behind the queue, so an add-to-cart that opened the drawer is read back.
    serial(refresh);
    return true;
  }

  function close() {
    if (!drawer || drawer.hidden) return;
    confirmingKey = null;
    if (flushTimer) flush();
    drawer.classList.remove('is-open');
    drawer.hidden = true;
    document.documentElement.classList.remove('tb-cart-open');
    if (opener && typeof opener.focus === 'function' && document.contains(opener)) opener.focus({ preventScroll: true });
  }

  /* ── Quantity stepper ────────────────────────────────────────────── */
  function step(key, delta) {
    const it = model && model.items.find((i) => i.key === key);
    if (!it || !cfg.update || !Number.isFinite(delta)) return;
    const next = it.quantity + delta;
    if (next < 1) { askRemove(key, true); return; }
    it.quantity = next;
    recalc(model);
    pending.set(key, next);
    setBadges(model.count);
    paint();
    if (flushTimer) clearTimeout(flushTimer);
    flushTimer = setTimeout(flush, STEP_DELAY);
  }

  function flush() {
    if (flushTimer) { clearTimeout(flushTimer); flushTimer = null; }
    return serial(drain);
  }

  function drain() {
    if (!pending.size) { inflight = null; markSaving(); return undefined; }
    const [key, qty] = pending.entries().next().value;
    pending.delete(key);
    inflight = key;
    markSaving();
    const fd = new FormData();
    fd.set('line_key', key);
    fd.set('quantity', String(qty));
    const fail = (message) => {
      pending.clear();
      inflight = null;
      toast(message, 'error');
      return refresh(); // the host's cart replaces the estimate
    };
    return request(cfg.update, fd).then(({ status: s, json }) => {
      if (s >= 200 && s < 300 && json && json.ok !== false && json.cart) {
        if (!pending.size) render(json.cart);
        return drain();
      }
      return fail((json && json.message) || 'به‌روزرسانی سبد ممکن نشد.');
    }, () => fail('ارتباط با سرور برقرار نشد.'));
  }

  /* ── Removal, confirmed inline ───────────────────────────────────── */
  function askRemove(key, focus) {
    const li = findLine(key);
    if (!li) return;
    if (confirmingKey !== null && confirmingKey !== key) cancelRemove(false);
    confirmingKey = key;
    let box = li.querySelector('.tb-cart-drawer__confirm');
    if (!box) {
      const title = (model && (model.items.find((i) => i.key === key) || {}).title) || '';
      li.classList.add('is-confirming');
      box = document.createElement('div');
      box.className = 'tb-cart-drawer__confirm';
      box.setAttribute('role', 'group');
      box.setAttribute('aria-label', 'تأیید حذف');
      const question = document.createElement('span');
      question.textContent = '«' + title + '» از سبد حذف شود؟';
      const yes = document.createElement('button');
      yes.type = 'button';
      yes.className = 'tb-cart-drawer__confirm-yes';
      yes.setAttribute('data-tb-cart-confirm', '');
      yes.textContent = 'حذف';
      const no = document.createElement('button');
      no.type = 'button';
      no.className = 'tb-cart-drawer__confirm-no';
      no.setAttribute('data-tb-cart-cancel', '');
      no.textContent = 'انصراف';
      box.append(question, yes, no);
      li.appendChild(box);
    }
    // The least destructive choice takes focus.
    if (focus) box.querySelector('[data-tb-cart-cancel]').focus();
  }

  function cancelRemove(focus) {
    const li = confirmingKey !== null ? findLine(confirmingKey) : null;
    confirmingKey = null;
    if (!li) return;
    li.classList.remove('is-confirming');
    const box = li.querySelector('.tb-cart-drawer__confirm');
    if (box) box.remove();
    if (focus) li.querySelector('[data-tb-cart-remove]').focus();
  }

  function remove(key) {
    if (!cfg.remove || !model) return;
    const index = model.items.findIndex((i) => i.key === key);
    if (index < 0) return;
    confirmingKey = null;
    pending.delete(key);
    model.items.splice(index, 1);
    recalc(model);
    setBadges(model.count);
    paint();
    const lines = drawer.querySelectorAll('.tb-cart-drawer__item');
    const next = lines[Math.min(index, lines.length - 1)];
    (next ? next.querySelector('[data-tb-cart-remove]') : drawer.querySelector('.tb-cart-drawer__close')).focus();

    const fd = new FormData();
    fd.set('line_key', key);
    serial(() => request(cfg.remove, fd).then(({ status: s, json }) => {
      if (s >= 200 && s < 300 && json.cart) {
        if (!pending.size) render(json.cart);
        toast(json.message || 'محصول از سبد حذف شد.', 'success');
        return undefined;
      }
      toast((json && json.message) || 'حذف از سبد ممکن نشد.', 'error');
      return refresh();
    }, () => { toast('ارتباط با سرور برقرار نشد.', 'error'); return refresh(); }));
  }

  /* ── Add to cart ─────────────────────────────────────────────────── */
  function add(button) {
    const c = config(button);
    if (!c.add || button.getAttribute('aria-busy') === 'true') return;
    const fd = new FormData();
    fd.set('product_id', button.getAttribute('data-product-id') || '');
    fd.set('quantity', button.getAttribute('data-quantity') || '1');
    const variant = button.getAttribute('data-variant-id');
    if (variant) fd.set('product_variant_id', variant);
    const label = button.innerHTML;
    button.setAttribute('aria-busy', 'true');
    button.disabled = true;
    button.classList.add('is-busy');
    serial(() => request(c.add, fd)).then(({ status: s, json }) => {
      if (s >= 200 && s < 300 && json.ok !== false) {
        if (json.cart) render(json.cart);
        button.classList.add('is-added');
        toast(json.message || 'به سبد اضافه شد.', 'success');
        if (button.hasAttribute('data-open-cart')) open(button);
      } else if (json && json.url && SAFE_URL.test(json.url)) {
        location.assign(json.url); // e.g. a variant must be chosen on the product page
      } else {
        toast((json && json.message) || (s === 429 ? 'تعداد درخواست‌ها زیاد است؛ کمی بعد تلاش کنید.' : 'افزودن به سبد ممکن نشد.'), 'error');
      }
    }).catch(() => toast('ارتباط با سرور برقرار نشد.', 'error')).finally(() => {
      button.removeAttribute('aria-busy');
      button.disabled = false;
      button.classList.remove('is-busy');
      setTimeout(() => { button.classList.remove('is-added'); button.innerHTML = label; }, 1800);
    });
  }

  document.addEventListener('click', (e) => {
    const opener_ = e.target.closest && e.target.closest('[data-tb-cart-open]');
    if (opener_ && open(opener_)) { e.preventDefault(); return; }
    const adder = e.target.closest && e.target.closest('[data-tb-add-to-cart]');
    if (adder) { e.preventDefault(); add(adder); }
  });

  Object.assign(api, { open, close, refresh, add, step, flush });
})(typeof window !== 'undefined' ? window : globalThis);
