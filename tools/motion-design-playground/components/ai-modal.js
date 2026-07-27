import { createAiContract } from '../scripts/core.js';

export function initializeAiModal({ getAsset, getTheme, notify }) {
  const dialog = document.querySelector('#ai-modal');
  const provider = document.querySelector('#ai-provider');
  const prompt = document.querySelector('#ai-prompt');
  const preview = document.querySelector('#ai-contract-preview');

  const refresh = () => {
    preview.textContent = JSON.stringify(createAiContract(getAsset(), provider.value, prompt.value, getTheme()), null, 2);
  };
  document.querySelector('#ai-edit-button').addEventListener('click', () => {
    refresh();
    dialog.showModal();
  });
  provider.addEventListener('change', refresh);
  prompt.addEventListener('input', refresh);
  document.querySelector('#mock-proposal').addEventListener('click', (event) => {
    event.preventDefault();
    refresh();
    notify('پیشنهاد آزمایشی ساخته شد؛ هیچ API فراخوانی نشد.');
    dialog.close();
  });
}
