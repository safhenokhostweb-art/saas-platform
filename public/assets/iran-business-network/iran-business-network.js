import { IRAN_MAP } from './map-data.js';
import { NETWORK_CONFIG } from './brand-config.js';

const NS = 'http://www.w3.org/2000/svg';
const el = (name, attrs = {}) => {
  const node = document.createElementNS(NS, name);
  Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, value));
  return node;
};

class IranBusinessNetwork extends HTMLElement {
  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
    this.config = structuredClone(NETWORK_CONFIG);
    this.observer = null;
  }

  connectedCallback() {
    this.render();
    this.observer = new IntersectionObserver(([entry]) => {
      this.shadowRoot.querySelector('.scene')?.classList.toggle('is-paused', !entry.isIntersecting);
    }, { threshold: 0.08 });
    this.observer.observe(this);
  }

  disconnectedCallback() {
    this.observer?.disconnect();
  }

  set animationConfig(value) {
    this.config = this.mergeConfig(NETWORK_CONFIG, value || {});
    if (this.isConnected) this.render();
  }

  mergeConfig(base, override) {
    return {
      ...base,
      ...override,
      brand: { ...base.brand, ...override.brand },
      timing: { ...base.timing, ...override.timing },
      colors: { ...base.colors, ...override.colors },
    };
  }

  pathFor(from, to, index) {
    const mx = (from.x + to.x) / 2;
    const my = (from.y + to.y) / 2;
    const dx = to.x - from.x;
    const dy = to.y - from.y;
    const curve = Math.min(34, Math.hypot(dx, dy) * 0.14) * (index % 2 ? -1 : 1);
    const length = Math.hypot(dx, dy) || 1;
    return `M ${from.x} ${from.y} Q ${mx - (dy / length) * curve} ${my + (dx / length) * curve} ${to.x} ${to.y}`;
  }

  async render() {
    const c = this.config;
    const origin = IRAN_MAP.cities[c.origin] || IRAN_MAP.cities.Qazvin;
    const destinations = c.destinations.filter(name => IRAN_MAP.cities[name]);
    const loopSeconds = c.timing.loopMs / 1000;
    const stepSeconds = c.timing.connectionStepMs / 1000;

    this.shadowRoot.innerHTML = `
      <link rel="stylesheet" href="${new URL('./styles.css', import.meta.url)}">
      <div class="scene" role="img" aria-label="${c.brand.message}"
        style="--primary:${c.colors.primary};--secondary:${c.colors.secondary};--accent:${c.colors.accent};--loop:${loopSeconds}s">
        <svg viewBox="${IRAN_MAP.viewBox}" aria-hidden="true">
          <defs>
            <linearGradient id="mapFill" x1="0" y1="0" x2="1" y2="1">
              <stop stop-color="${c.colors.secondary}" stop-opacity=".13"/>
              <stop offset="1" stop-color="${c.colors.primary}" stop-opacity=".035"/>
            </linearGradient>
            <linearGradient id="lineStroke" x1="0" y1="0" x2="1" y2="1">
              <stop stop-color="${c.colors.primary}"/><stop offset="1" stop-color="${c.colors.accent}"/>
            </linearGradient>
            <filter id="softGlow" x="-150%" y="-150%" width="400%" height="400%">
              <feGaussianBlur stdDeviation="5" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
            </filter>
          </defs>
          <g class="camera">
            <g class="map"><g class="provinces"></g></g>
            <g class="connections"></g>
            <g class="nodes"></g>
            <g class="origin">
              <circle class="origin-wave wave-a" cx="${origin.x}" cy="${origin.y}" r="9"/>
              <circle class="origin-wave wave-b" cx="${origin.x}" cy="${origin.y}" r="9"/>
              <circle class="origin-core" cx="${origin.x}" cy="${origin.y}" r="5"/>
              <text x="${origin.x - 13}" y="${origin.y - 16}">${origin.label}</text>
            </g>
            <g class="country-outline"></g>
            <g class="province-labels"></g>
          </g>
        </svg>
        <div class="brand" dir="rtl">
          <span class="brand-mark" aria-hidden="true"></span>
          <strong>${c.brand.name}</strong>
          <span class="tagline">${c.brand.tagline}</span>
          <span class="message">${c.brand.message}</span>
        </div>
      </div>`;

    await this.renderMap();

    const paths = this.shadowRoot.querySelector('.connections');
    const nodes = this.shadowRoot.querySelector('.nodes');
    destinations.forEach((name, index) => {
      const city = IRAN_MAP.cities[name];
      const d = this.pathFor(origin, city, index);
      const delay = 1.35 + index * stepSeconds;
      const path = el('path', { class: 'connection', d, pathLength: '1' });
      path.style.setProperty('--delay', `${delay}s`);
      paths.append(path);

      const particle = el('circle', { class: 'particle', r: index % 3 === 0 ? '2.7' : '2' });
      particle.style.setProperty('--delay', `${delay + 0.1}s`);
      const motion = el('animateMotion', {
        path: d, dur: `${1.65 + (index % 4) * 0.14}s`, begin: `${delay}s`,
        repeatCount: 'indefinite', keyTimes: '0;1', keySplines: '.25 .1 .25 1', calcMode: 'spline',
      });
      particle.append(motion);
      paths.append(particle);

      const arrow = el('path', {
        class: 'moving-arrow',
        d: 'M -7 -4 L 2 0 L -7 4 L -4 0 Z',
      });
      arrow.style.setProperty('--delay', `${delay + 0.08}s`);
      arrow.append(el('animateMotion', {
        path: d,
        dur: `${1.65 + (index % 4) * 0.14}s`,
        begin: `${delay + 0.08}s`,
        repeatCount: 'indefinite',
        rotate: 'auto',
        keyTimes: '0;1',
        keySplines: '.25 .1 .25 1',
        calcMode: 'spline',
      }));
      paths.append(arrow);

      const group = el('g', { class: 'node' });
      group.style.setProperty('--delay', `${delay + 0.65}s`);
      group.append(el('circle', { class: 'node-halo', cx: city.x, cy: city.y, r: '8' }));
      group.append(el('circle', { class: 'node-core', cx: city.x, cy: city.y, r: '3.5' }));
      nodes.append(group);
    });
  }

  async renderMap() {
    const provinceLayer = this.shadowRoot.querySelector('.provinces');
    const labelLayer = this.shadowRoot.querySelector('.province-labels');
    const outlineLayer = this.shadowRoot.querySelector('.country-outline');
    if (!provinceLayer || !labelLayer || !outlineLayer) return;

    try {
      const response = await fetch(IRAN_MAP.source);
      if (!response.ok) throw new Error(`Map request failed: ${response.status}`);
      const source = new DOMParser().parseFromString(await response.text(), 'image/svg+xml');

      source.querySelectorAll('#features path').forEach(sourcePath => {
        const provinceId = sourcePath.getAttribute('name') === 'North Khorasan'
          ? 'IR31'
          : sourcePath.id;
        const name = IRAN_MAP.provinces[provinceId];
        if (!name) return;

        const path = el('path', {
          class: `province province-${provinceId}`,
          d: sourcePath.getAttribute('d'),
          'data-province': provinceId,
          'aria-label': name,
        });
        const title = el('title');
        title.textContent = name;
        path.append(title);
        provinceLayer.append(path);

        const bounds = path.getBBox();
        const [offsetX, offsetY] = IRAN_MAP.labelOffsets[provinceId] || [0, 0];
        const label = el('text', {
          class: `${name.length > 14 ? 'province-label province-label-long' : 'province-label'} province-label-${provinceId}`,
          x: bounds.x + bounds.width / 2 + offsetX,
          y: bounds.y + bounds.height / 2 + offsetY,
        });
        label.textContent = name;
        labelLayer.append(label);
      });

      const outlineResponse = await fetch(IRAN_MAP.outlineSource);
      if (!outlineResponse.ok) throw new Error(`Outline request failed: ${outlineResponse.status}`);
      const outlineSource = new DOMParser().parseFromString(
        await outlineResponse.text(),
        'image/svg+xml',
      );
      const sourceOutline = outlineSource.querySelector('#features path');
      if (sourceOutline) {
        outlineLayer.append(el('path', {
          class: 'country-outline-path',
          d: sourceOutline.getAttribute('d'),
          pathLength: '1',
        }));
      }
    } catch (error) {
      this.dispatchEvent(new CustomEvent('maperror', { detail: error }));
    }
  }
}

customElements.define('iran-business-network', IranBusinessNetwork);
