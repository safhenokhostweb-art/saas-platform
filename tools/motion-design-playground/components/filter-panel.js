import { collectOptions, TYPE_LABELS } from '../scripts/core.js';

export function initializeFilters(assets, onChange) {
  const controls = {
    query: document.querySelector('#search-input'),
    category: document.querySelector('#category-filter'),
    industry: document.querySelector('#industry-filter'),
    usage: document.querySelector('#usage-filter'),
    tag: document.querySelector('#tag-filter'),
    type: 'all',
  };

  populate(controls.category, collectOptions(assets, 'category'));
  populate(controls.industry, collectOptions(assets, 'industry'));
  populate(controls.usage, collectOptions(assets, 'usage'));
  populate(controls.tag, collectOptions(assets, 'tags'));

  const tabs = document.querySelector('#type-tabs');
  Object.entries(TYPE_LABELS).forEach(([value, label]) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = label;
    button.dataset.value = value;
    button.classList.toggle('active', value === 'all');
    button.addEventListener('click', () => {
      controls.type = value;
      tabs.querySelectorAll('button').forEach((item) => item.classList.toggle('active', item === button));
      emit();
    });
    tabs.append(button);
  });

  let debounce;
  controls.query.addEventListener('input', () => {
    clearTimeout(debounce);
    debounce = setTimeout(emit, 120);
  });
  [controls.category, controls.industry, controls.usage, controls.tag].forEach((control) => control.addEventListener('change', emit));

  document.querySelector('#reset-filters').addEventListener('click', () => {
    controls.query.value = '';
    [controls.category, controls.industry, controls.usage, controls.tag].forEach((control) => { control.value = ''; });
    controls.type = 'all';
    tabs.querySelectorAll('button').forEach((item) => item.classList.toggle('active', item.dataset.value === 'all'));
    emit();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === '/' && !/input|textarea|select/i.test(document.activeElement?.tagName || '')) {
      event.preventDefault();
      controls.query.focus();
    }
  });

  function emit() {
    onChange({
      query: controls.query.value,
      category: controls.category.value,
      industry: controls.industry.value,
      usage: controls.usage.value,
      tag: controls.tag.value,
      type: controls.type,
    });
  }
  return controls;
}

function populate(select, values) {
  values.forEach((value) => {
    const option = document.createElement('option');
    option.value = value;
    option.textContent = value;
    select.append(option);
  });
}

export function renderActiveFilters(filters) {
  const container = document.querySelector('#active-filters');
  container.replaceChildren();
  const labels = [filters.query, filters.type !== 'all' ? filters.type : '', filters.category, filters.industry, filters.usage, filters.tag].filter(Boolean);
  labels.forEach((label) => {
    const chip = document.createElement('span');
    chip.textContent = label;
    container.append(chip);
  });
}
