import { scoreEntries } from '../scripts/core.js';

const FAMILY_LABELS = {
  motion: 'Motion Graphic',
  icon: 'Icon',
  object: 'Object',
  'visual-asset': 'Visual Asset',
};

export function createAssetCard(asset, { selected = false, onSelect }) {
  const template = document.querySelector('#asset-card-template');
  const card = template.content.firstElementChild.cloneNode(true);
  card.dataset.assetId = asset.id;
  card.classList.toggle('selected', selected);

  const visual = card.querySelector('.asset-visual');
  visual.setAttribute('aria-label', `انتخاب ${asset.name}`);
  visual.addEventListener('click', () => onSelect(asset));

  card.querySelector('.asset-family').textContent = FAMILY_LABELS[asset.type] || asset.type;
  card.querySelector('.asset-name').textContent = asset.name;
  card.querySelector('.asset-name').title = asset.name;
  card.querySelector('.asset-category').textContent = asset.category;

  const tags = card.querySelector('.asset-tags');
  asset.tags.slice(0, 3).forEach((tag) => {
    const chip = document.createElement('span');
    chip.textContent = tag;
    tags.append(chip);
  });

  const flags = card.querySelector('.asset-flags');
  flags.append(createFlag(asset.theme_support, 'Theme'), createFlag(asset.ai_editable, 'AI editable'));

  const scores = card.querySelector('.score-grid');
  scoreEntries(asset).forEach(([label, value]) => {
    const score = document.createElement('div');
    score.className = 'score';
    score.style.setProperty('--score', Math.max(0, Math.min(100, value)));
    score.innerHTML = `<span>${label}</span><b>${value}</b><i aria-hidden="true"></i>`;
    scores.append(score);
  });
  return card;
}

function createFlag(enabled, label) {
  const flag = document.createElement('span');
  flag.classList.toggle('off', !enabled);
  flag.textContent = `${enabled ? '●' : '○'} ${label}`;
  return flag;
}

export function showAssetError(card, message = 'پیش‌نمایش در دسترس نیست') {
  const visual = card.querySelector('.asset-visual');
  visual.classList.add('error');
  visual.replaceChildren(document.createTextNode(message));
}
