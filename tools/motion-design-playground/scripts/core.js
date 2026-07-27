export const THEME_KEYS = ['primary', 'secondary', 'accent', 'background', 'border'];

export const TYPE_LABELS = {
  all: 'همه',
  motion: 'Motion',
  icon: 'Icon',
  object: 'Object',
  'visual-asset': 'Visual',
};

export const SECTION_TYPES = [
  { id: 'hero', label: 'Hero', eyebrow: 'راهکار حرفه‌ای کسب‌وکار', title: 'برند شما، آماده برای رشد دیجیتال', copy: 'دارایی‌های بصری هماهنگ با هویت برند، کیفیت قالب سایت را به‌صورت ملموس ارتقا می‌دهند.' },
  { id: 'features', label: 'Features', eyebrow: 'ویژگی‌های کلیدی', title: 'یک زبان بصری منسجم برای تمام صفحات', copy: 'آیکون‌ها و اشیای تم‌پذیر در کنار ساختار شفاف، تجربه‌ای حرفه‌ای و قابل اعتماد می‌سازند.' },
  { id: 'services', label: 'Services', eyebrow: 'خدمات ما', title: 'معرفی توانمندی‌ها با وضوح بیشتر', copy: 'دارایی انتخاب‌شده در این نمونه به‌عنوان نقطه تمرکز سکشن خدمات استفاده می‌شود.' },
  { id: 'product-card', label: 'Product Card', eyebrow: 'محصول منتخب', title: 'نمایش محصول با جزئیات حرفه‌ای', copy: 'پالت برند و تصویر برداری سبک، کارت محصول را برای هر قالب فروشگاهی آماده می‌کند.' },
  { id: 'about', label: 'About Section', eyebrow: 'درباره مجموعه', title: 'داستان یک کسب‌وکار ایرانی مدرن', copy: 'ترکیب محتوای فارسی، فضای سفید و تصویرسازی هم‌راستا با برند، اعتبار سازمان را تقویت می‌کند.' },
  { id: 'cta', label: 'CTA Section', eyebrow: 'شروع همکاری', title: 'برای تجربه‌ای بهتر آماده‌اید؟', copy: 'دارایی‌های کتابخانه می‌توانند دعوت به اقدام را زنده‌تر، دقیق‌تر و به‌یادماندنی‌تر کنند.' },
];

export function normalizeAsset(asset) {
  return {
    ...asset,
    id: asset.id || asset.library_id || '',
    name: asset.name_fa || asset.name || asset.name_en || asset.slug,
    name_fa: asset.name_fa || asset.name || '',
    name_en: asset.name_en || '',
    tags: Array.isArray(asset.tags) ? asset.tags : [],
    keywords: Array.isArray(asset.keywords) ? asset.keywords : [],
    synonyms: Array.isArray(asset.synonyms) ? asset.synonyms : [],
    industry: Array.isArray(asset.industry) ? asset.industry : asset.industry ? [asset.industry] : [],
    usage: Array.isArray(asset.usage) ? asset.usage : asset.usage ? [asset.usage] : [],
    quality_score: asset.quality_score || {},
    preview: derivePreviewPath(asset.path),
  };
}

export function normalizeAssets(index) {
  if (!index || !Array.isArray(index.assets)) throw new Error('library-index.json does not contain an assets array.');
  return index.assets.map(normalizeAsset);
}

export function derivePreviewPath(primaryPath) {
  if (typeof primaryPath !== 'string') return '';
  return primaryPath.replace(/(?:animation|icon|asset)\.svg$/i, 'preview.svg');
}

export function searchableText(asset) {
  return [asset.id, asset.slug, asset.name, asset.name_fa, asset.name_en, asset.category, asset.subcategory, ...asset.tags, ...asset.keywords, ...asset.synonyms, ...asset.industry, ...asset.usage]
    .filter(Boolean)
    .join(' ')
    .toLocaleLowerCase('fa');
}

export function filterAssets(assets, filters = {}) {
  const query = String(filters.query || '').trim().toLocaleLowerCase('fa');
  return assets.filter((asset) => {
    if (filters.type && filters.type !== 'all' && asset.type !== filters.type) return false;
    if (filters.category && asset.category !== filters.category) return false;
    if (filters.industry && !asset.industry.includes(filters.industry)) return false;
    if (filters.usage && !asset.usage.includes(filters.usage)) return false;
    if (filters.tag && !asset.tags.includes(filters.tag)) return false;
    return !query || searchableText(asset).includes(query);
  });
}

export function collectOptions(assets, field) {
  const values = new Set();
  for (const asset of assets) {
    const current = asset[field];
    if (Array.isArray(current)) current.forEach((value) => value && values.add(value));
    else if (current) values.add(current);
  }
  return [...values].sort((left, right) => left.localeCompare(right, 'fa'));
}

export function isHexColor(value) {
  return /^#[0-9a-f]{6}$/i.test(String(value || ''));
}

export function normalizePalette(palette, fallback = {}) {
  const normalized = {};
  for (const key of THEME_KEYS) {
    const value = palette?.[key];
    normalized[key] = isHexColor(value) ? value.toLowerCase() : (fallback[key] || '#000000').toLowerCase();
  }
  return normalized;
}

export function paletteToCss(palette, prefix = '--asset-') {
  const safe = normalizePalette(palette);
  return THEME_KEYS.map((key) => `${prefix}${key}:${safe[key]}`).join(';');
}

function hexToRgb(hex) {
  const value = hex.replace('#', '');
  return [0, 2, 4].map((index) => Number.parseInt(value.slice(index, index + 2), 16));
}

function rgbToHex(red, green, blue) {
  return `#${[red, green, blue].map((value) => Math.max(0, Math.min(255, Math.round(value))).toString(16).padStart(2, '0')).join('')}`;
}

function luminance(hex) {
  const [red, green, blue] = hexToRgb(hex).map((value) => value / 255);
  return (red * .2126) + (green * .7152) + (blue * .0722);
}

function mix(first, second, weight) {
  const a = hexToRgb(first);
  const b = hexToRgb(second);
  return rgbToHex(...a.map((value, index) => value * (1 - weight) + b[index] * weight));
}

export function extractDominantColors(pixelData, maxColors = 5) {
  const buckets = new Map();
  for (let index = 0; index < pixelData.length; index += 4) {
    const alpha = pixelData[index + 3];
    if (alpha < 160) continue;
    const red = pixelData[index];
    const green = pixelData[index + 1];
    const blue = pixelData[index + 2];
    if (red > 245 && green > 245 && blue > 245) continue;
    const quantized = [red, green, blue].map((value) => Math.min(255, Math.round(value / 32) * 32));
    const key = quantized.join(',');
    buckets.set(key, (buckets.get(key) || 0) + 1);
  }
  return [...buckets.entries()]
    .sort((left, right) => right[1] - left[1])
    .slice(0, maxColors)
    .map(([key]) => rgbToHex(...key.split(',').map(Number)));
}

export function deriveBrandPalette(colors, fallback) {
  const base = normalizePalette(fallback || {
    primary: '#42c7b5', secondary: '#1b3554', accent: '#e4b94f', background: '#f3f7f7', border: '#17304d',
  });
  const clean = colors.filter(isHexColor);
  if (clean.length === 0) return base;
  const primary = clean[0];
  const secondary = clean[1] || mix(primary, '#0f172a', .62);
  const accent = clean[2] || mix(primary, '#f59e0b', .65);
  const darkLogo = luminance(primary) < .42;
  const background = darkLogo ? '#f8fafc' : '#0f172a';
  const border = darkLogo ? mix(primary, '#0f172a', .68) : mix(primary, '#ffffff', .58);
  return normalizePalette({ primary, secondary, accent, background, border }, base);
}

export function scoreEntries(asset) {
  const score = asset.quality_score || {};
  return [
    ['Tech', Number(score.technical) || 0],
    ['Visual', Number(score.visual) || 0],
    ['Reuse', Number(score.reusability) || 0],
    ['AI', Number(score.ai_readiness) || 0],
  ];
}

export function countReady(assets, field) {
  return assets.filter((asset) => asset[field] === true).length;
}

export function createAiContract(asset, provider, prompt, theme) {
  return {
    status: 'ui-placeholder-only',
    provider,
    asset_id: asset?.id || null,
    asset_version: asset?.version || null,
    instruction: prompt.trim(),
    brand_theme: normalizePalette(theme),
    publish: false,
  };
}
