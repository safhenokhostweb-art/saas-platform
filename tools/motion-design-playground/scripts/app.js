import { normalizeAssets, filterAssets, countReady, THEME_KEYS, normalizePalette, extractDominantColors, deriveBrandPalette } from './core.js';
import { createAssetCard, showAssetError } from '../components/asset-card.js';
import { initializeFilters, renderActiveFilters } from '../components/filter-panel.js';
import { initializeSectionSelector, renderTemplateShell } from '../components/template-preview.js';
import { initializeAiModal } from '../components/ai-modal.js';

const PAGE_SIZE = 24;
const svgCache = new Map();
const state = {
  assets: [],
  filtered: [],
  filters: { query: '', type: 'all', category: '', industry: '', usage: '', tag: '' },
  visible: PAGE_SIZE,
  mode: 'theme',
  selectedAsset: null,
  section: 'hero',
  theme: { primary: '#42c7b5', secondary: '#1b3554', accent: '#e4b94f', background: '#f3f7f7', border: '#17304d' },
  brandTheme: { primary: '#42c7b5', secondary: '#1b3554', accent: '#e4b94f', background: '#f3f7f7', border: '#17304d' },
  sources: null,
  tags: null,
  relations: null,
};

const gallery = document.querySelector('#asset-gallery');
const emptyState = document.querySelector('#empty-state');
const loadMore = document.querySelector('#load-more');

bootstrap().catch((error) => {
  document.querySelector('#load-state').innerHTML = `<span class="status-dot"></span>خطا در بارگذاری`;
  notify(error.message, true);
  console.error(error);
});

async function bootstrap() {
  const sourceResponse = await fetch('./data/data-sources.json', { cache: 'no-store' });
  if (!sourceResponse.ok) throw new Error('تعریف منابع داده بارگذاری نشد. Playground را از static server اجرا کنید.');
  state.sources = await sourceResponse.json();

  const [libraryIndex, tags, relations, themes] = await Promise.all([
    fetchJson(state.sources.library_index),
    fetchJson(state.sources.tags),
    fetchJson(state.sources.relations),
    fetchJson('./themes/presets.json'),
  ]);
  state.assets = normalizeAssets(libraryIndex);
  state.filtered = state.assets;
  state.selectedAsset = state.assets[0] || null;
  state.tags = tags;
  state.relations = relations;

  initializeFilters(state.assets, (filters) => {
    state.filters = filters;
    state.filtered = filterAssets(state.assets, filters);
    state.visible = PAGE_SIZE;
    renderActiveFilters(filters);
    renderGallery();
    updateMetrics();
  });
  initializeThemeEditor(themes.presets || []);
  initializePreviewModes();
  initializeLogoAnalyzer();
  initializeSectionSelector((section) => { state.section = section; renderTemplate(); });
  initializeAiModal({ getAsset: () => state.selectedAsset, getTheme: currentPalette, notify });
  loadMore.addEventListener('click', () => { state.visible += PAGE_SIZE; renderGallery(); });

  renderGallery();
  renderTemplate();
  updateMetrics();
  applyThemeToDocument(state.theme);
  const relationCount = Array.isArray(relations.relationships) ? relations.relationships.length : 0;
  const tagCount = Array.isArray(tags.tags) ? tags.tags.length : 0;
  const loadState = document.querySelector('#load-state');
  loadState.classList.add('ready');
  loadState.innerHTML = `<span class="status-dot"></span>${state.assets.length.toLocaleString('fa-IR')} دارایی آماده`;
  loadState.title = `${tagCount} tags · ${relationCount} relation groups`;
}

async function fetchJson(relative) {
  const response = await fetch(new URL(relative, document.baseURI), { cache: 'no-store' });
  if (!response.ok) throw new Error(`منبع داده در دسترس نیست: ${relative}`);
  return response.json();
}

function initializeThemeEditor(presets) {
  const presetSelect = document.querySelector('#theme-preset');
  const fields = document.querySelector('#theme-colors');
  presets.forEach((preset) => {
    const option = document.createElement('option');
    option.value = preset.id;
    option.textContent = preset.name;
    presetSelect.append(option);
  });
  const labels = { primary: 'Primary', secondary: 'Secondary', accent: 'Accent', background: 'Background', border: 'Border' };
  THEME_KEYS.forEach((key) => {
    const label = document.createElement('label');
    label.className = 'color-field';
    label.innerHTML = `<input type="color" data-theme-key="${key}" value="${state.theme[key]}" aria-label="${labels[key]}"><span>${labels[key]}<br><code>${state.theme[key]}</code></span>`;
    label.querySelector('input').addEventListener('input', (event) => {
      state.theme[key] = event.target.value;
      label.querySelector('code').textContent = event.target.value;
      presetSelect.value = '';
      themeChanged();
    });
    fields.append(label);
  });
  presetSelect.addEventListener('change', () => {
    const preset = presets.find((item) => item.id === presetSelect.value);
    if (!preset) return;
    state.theme = normalizePalette(preset, state.theme);
    syncThemeInputs();
    themeChanged();
  });
  renderPalette(document.querySelector('#token-preview'), state.theme);
}

function syncThemeInputs() {
  document.querySelectorAll('[data-theme-key]').forEach((input) => {
    input.value = state.theme[input.dataset.themeKey];
    input.closest('.color-field').querySelector('code').textContent = input.value;
  });
}

function themeChanged() {
  applyThemeToDocument(state.theme);
  renderPalette(document.querySelector('#token-preview'), state.theme);
  if (state.mode === 'theme') refreshVisibleSvgThemes();
  renderTemplate();
}

function initializePreviewModes() {
  document.querySelectorAll('.preview-modes button').forEach((button) => {
    button.addEventListener('click', () => {
      state.mode = button.dataset.mode;
      document.querySelectorAll('.preview-modes button').forEach((item) => item.classList.toggle('active', item === button));
      renderGallery();
      renderTemplate();
    });
  });
}

function initializeLogoAnalyzer() {
  const input = document.querySelector('#logo-input');
  const drop = document.querySelector('#logo-drop');
  input.addEventListener('change', () => input.files[0] && analyzeLogo(input.files[0]));
  ['dragenter', 'dragover'].forEach((name) => drop.addEventListener(name, (event) => { event.preventDefault(); drop.classList.add('dragging'); }));
  ['dragleave', 'drop'].forEach((name) => drop.addEventListener(name, (event) => { event.preventDefault(); drop.classList.remove('dragging'); }));
  drop.addEventListener('drop', (event) => {
    const file = event.dataTransfer.files[0];
    if (file) analyzeLogo(file);
  });
}

async function analyzeLogo(file) {
  if (!file.type.startsWith('image/')) return notify('فرمت فایل باید تصویر باشد.', true);
  if (file.size > 5 * 1024 * 1024) return notify('حجم لوگو باید کمتر از ۵ مگابایت باشد.', true);
  const objectUrl = URL.createObjectURL(file);
  try {
    const image = await loadImage(objectUrl);
    const canvas = document.createElement('canvas');
    canvas.width = 96;
    canvas.height = 96;
    const context = canvas.getContext('2d', { willReadFrequently: true });
    context.clearRect(0, 0, 96, 96);
    const ratio = Math.min(96 / image.naturalWidth, 96 / image.naturalHeight);
    const width = image.naturalWidth * ratio;
    const height = image.naturalHeight * ratio;
    context.drawImage(image, (96 - width) / 2, (96 - height) / 2, width, height);
    const colors = extractDominantColors(context.getImageData(0, 0, 96, 96).data);
    state.brandTheme = deriveBrandPalette(colors, state.theme);
    document.querySelector('#logo-preview').src = canvas.toDataURL('image/png');
    document.querySelector('#brand-result').hidden = false;
    renderPalette(document.querySelector('#brand-token-preview'), state.brandTheme);
    if (state.mode === 'brand') { refreshVisibleSvgThemes(); renderTemplate(); }
    notify('پالت برند به‌صورت محلی استخراج شد.');
  } catch (error) {
    notify(`تحلیل لوگو ممکن نشد: ${error.message}`, true);
  } finally {
    URL.revokeObjectURL(objectUrl);
  }
}

function loadImage(source) {
  return new Promise((resolve, reject) => {
    const image = new Image();
    image.onload = () => resolve(image);
    image.onerror = () => reject(new Error('تصویر قابل خواندن نیست'));
    image.src = source;
  });
}

function renderGallery() {
  gallery.replaceChildren();
  const visibleAssets = state.filtered.slice(0, state.visible);
  visibleAssets.forEach((asset) => {
    const card = createAssetCard(asset, {
      selected: state.selectedAsset?.id === asset.id,
      onSelect: selectAsset,
    });
    gallery.append(card);
    renderSvgInto(card.querySelector('.asset-visual'), asset, true).catch(() => showAssetError(card));
  });
  emptyState.hidden = state.filtered.length !== 0;
  loadMore.hidden = state.visible >= state.filtered.length;
}

function selectAsset(asset) {
  state.selectedAsset = asset;
  gallery.querySelectorAll('.asset-card').forEach((card) => card.classList.toggle('selected', card.dataset.assetId === asset.id));
  renderTemplate();
  notify(`${asset.name} برای پیش‌نمایش قالب انتخاب شد.`);
}

async function renderSvgInto(container, asset, usePreview = false) {
  const relative = usePreview ? asset.preview : asset.path;
  let raw;
  try {
    raw = await getSvg(relative);
  } catch (error) {
    if (!usePreview) throw error;
    raw = await getSvg(asset.path);
  }
  const svg = sanitizeSvg(raw);
  if (!svg) throw new Error('Invalid SVG');
  const palette = currentPalette();
  if (state.mode !== 'master') applyPalette(svg, palette);
  container.style.backgroundColor = state.mode === 'master' ? '' : palette.background;
  container.replaceChildren(svg);
}

async function getSvg(relative) {
  const url = new URL(`${state.sources.asset_root}${relative}`, document.baseURI).href;
  if (!svgCache.has(url)) {
    svgCache.set(url, fetch(url).then((response) => {
      if (!response.ok) throw new Error(`SVG ${response.status}`);
      return response.text();
    }));
  }
  return svgCache.get(url);
}

function sanitizeSvg(raw) {
  const parsed = new DOMParser().parseFromString(raw, 'image/svg+xml');
  if (parsed.querySelector('parsererror')) return null;
  const svg = parsed.documentElement;
  if (svg.localName !== 'svg') return null;
  parsed.querySelectorAll('script,foreignObject,image,iframe,object,embed,audio,video,canvas').forEach((node) => node.remove());
  parsed.querySelectorAll('*').forEach((node) => {
    [...node.attributes].forEach((attribute) => {
      const name = attribute.name.toLowerCase();
      const value = attribute.value.trim().toLowerCase();
      if (name.startsWith('on')) node.removeAttribute(attribute.name);
      if ((name === 'href' || name.endsWith(':href')) && !value.startsWith('#')) node.removeAttribute(attribute.name);
      if (['javascript:', 'file:', 'data:', 'http:', 'https:', '//'].some((scheme) => value.startsWith(scheme)) && !name.startsWith('xmlns')) node.removeAttribute(attribute.name);
    });
  });
  parsed.querySelectorAll('style').forEach((style) => {
    if (/(?:javascript:|@import|url\(\s*["']?(?:https?:|\/\/|data:))/i.test(style.textContent)) style.remove();
  });
  svg.removeAttribute('width');
  svg.removeAttribute('height');
  return document.importNode(svg, true);
}

function applyPalette(svg, palette) {
  THEME_KEYS.forEach((key) => svg.style.setProperty(`--asset-${key}`, palette[key], 'important'));
  svg.style.color = palette.border;
}

function applyThemeToDocument(palette) {
  THEME_KEYS.forEach((key) => document.documentElement.style.setProperty(`--asset-${key}`, palette[key]));
}

function refreshVisibleSvgThemes() {
  const palette = currentPalette();
  document.querySelectorAll('.asset-visual').forEach((visual) => { visual.style.backgroundColor = palette.background; });
  document.querySelectorAll('.asset-visual svg').forEach((svg) => applyPalette(svg, palette));
}

function currentPalette() {
  return state.mode === 'brand' ? state.brandTheme : state.theme;
}

function renderTemplate() {
  const container = document.querySelector('#template-preview');
  const { root, visual } = renderTemplateShell(state.section, state.selectedAsset);
  const palette = currentPalette();
  THEME_KEYS.forEach((key) => root.style.setProperty(`--asset-${key}`, palette[key]));
  container.replaceChildren(root);
  if (state.selectedAsset) renderSvgInto(visual, state.selectedAsset, false).catch(() => { visual.textContent = 'پیش‌نمایش در دسترس نیست'; });
}

function renderPalette(container, palette) {
  container.replaceChildren();
  THEME_KEYS.forEach((key) => {
    const color = document.createElement('i');
    color.style.background = palette[key];
    color.title = `${key}: ${palette[key]}`;
    container.append(color);
  });
}

function updateMetrics() {
  document.querySelector('#metric-assets').textContent = state.assets.length.toLocaleString('fa-IR');
  document.querySelector('#metric-results').textContent = state.filtered.length.toLocaleString('fa-IR');
  document.querySelector('#metric-theme').textContent = `${Math.round((countReady(state.assets, 'theme_support') / Math.max(1, state.assets.length)) * 100).toLocaleString('fa-IR')}٪`;
  document.querySelector('#metric-ai').textContent = `${Math.round((countReady(state.assets, 'ai_editable') / Math.max(1, state.assets.length)) * 100).toLocaleString('fa-IR')}٪`;
}

function notify(message, error = false) {
  const toast = document.createElement('div');
  toast.className = `toast${error ? ' error' : ''}`;
  toast.setAttribute('role', 'status');
  toast.textContent = message;
  document.body.append(toast);
  setTimeout(() => { toast.classList.add('toast-out'); setTimeout(() => toast.remove(), 250); }, 2600);
}
