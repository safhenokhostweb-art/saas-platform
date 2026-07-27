import { SECTION_TYPES } from '../scripts/core.js';

export function initializeSectionSelector(onChange) {
  const select = document.querySelector('#section-select');
  SECTION_TYPES.forEach((section) => {
    const option = document.createElement('option');
    option.value = section.id;
    option.textContent = section.label;
    select.append(option);
  });
  select.addEventListener('change', () => onChange(select.value));
  return select;
}

export function renderTemplateShell(sectionId, asset) {
  const section = SECTION_TYPES.find((item) => item.id === sectionId) || SECTION_TYPES[0];
  const root = document.createElement('article');
  root.className = `site-section ${section.id}`;
  const copy = document.createElement('div');
  copy.className = 'section-copy';
  copy.innerHTML = `<small>${section.eyebrow}</small><h3>${section.title}</h3><p>${section.copy}</p><button type="button">مشاهده جزئیات</button>`;
  const visual = document.createElement('div');
  visual.className = 'section-asset';
  visual.dataset.assetId = asset?.id || '';
  visual.innerHTML = asset ? '<span class="asset-loader"></span>' : '<p>یک دارایی را از کتابخانه انتخاب کنید.</p>';
  root.append(copy, visual);
  return { root, visual };
}
