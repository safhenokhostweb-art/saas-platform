import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

// Phase 0.5 (ADR-064): the Canvas colour control's value model.
const source = fs.readFileSync(new URL('../../packages/saas-platform/modules/theme-runtime/resources/js/canvas-color.js', import.meta.url), 'utf8');
const context = vm.createContext({ window: {}, Math, Number, String });
vm.runInContext(source, context);
const C = context.window.CanvasColor;

const theme = C.palette({ primary_color: '#e11d48', secondary_color: '#0d9488' });

test('the palette resolves theme colours and falls back to the token defaults', () => {
  const byToken = Object.fromEntries(theme.map((t) => [t.token, t.value]));
  assert.equal(byToken.primary, '#e11d48');
  assert.equal(byToken.secondary, '#0d9488');
  assert.equal(byToken.background, '#ffffff');
  assert.equal(byToken['text-muted'], '#4b5563');
  assert.equal(theme.find((t) => t.token === 'primary').css, 'var(--tb-primary, #4f46e5)');
});

test('a swatch follows the theme and keeps its alpha as color-mix', () => {
  assert.equal(C.swatch('primary', 100), 'var(--tb-primary, #4f46e5)');
  assert.equal(C.swatch('primary', 40), 'color-mix(in srgb, var(--tb-primary, #4f46e5) 40%, transparent)');
  assert.equal(C.swatch('unknown', 100), '');
});

test('a fixed colour becomes rgba() below full opacity', () => {
  assert.equal(C.fixed('#0EA5E9', 100), '#0ea5e9');
  assert.equal(C.fixed('#0ea5e9', 25), 'rgba(14, 165, 233, 0.25)');
  assert.equal(C.fixed('#abc', 50), 'rgba(170, 187, 204, 0.5)');
  assert.equal(C.fixed('red', 50), '');
});

test('parse reads every stored shape', () => {
  const t = C.parse('color-mix(in srgb, var(--tb-primary, #4f46e5) 40%, transparent)', theme);
  assert.deepEqual({ ...t }, { kind: 'token', token: 'primary', hex: '#e11d48', alpha: 40 });
  assert.deepEqual({ ...C.parse('var(--tb-secondary)', theme) }, { kind: 'token', token: 'secondary', hex: '#0d9488', alpha: 100 });
  assert.deepEqual({ ...C.parse('rgba(14, 165, 233, 0.25)', theme) }, { kind: 'rgba', token: null, hex: '#0ea5e9', alpha: 25 });
  assert.deepEqual({ ...C.parse('#0ea5e980', theme) }, { kind: 'hex', token: null, hex: '#0ea5e9', alpha: 50 });
  assert.equal(C.parse('transparent', theme).kind, 'transparent');
  assert.equal(C.parse('', theme).kind, 'empty');
  assert.equal(C.parse('primary', theme).kind, 'other');
});

test('withAlpha keeps the kind of value', () => {
  assert.equal(C.withAlpha('var(--tb-primary, #4f46e5)', 60, theme), 'color-mix(in srgb, var(--tb-primary, #4f46e5) 60%, transparent)');
  assert.equal(C.withAlpha('color-mix(in srgb, var(--tb-primary, #4f46e5) 60%, transparent)', 100, theme), 'var(--tb-primary, #4f46e5)');
  assert.equal(C.withAlpha('#0ea5e9', 30, theme), 'rgba(14, 165, 233, 0.3)');
  assert.equal(C.withAlpha('rgba(14, 165, 233, 0.3)', 100, theme), '#0ea5e9');
  assert.equal(C.withAlpha('transparent', 50, theme), 'transparent');
});

test('preview and input values resolve swatches against the theme', () => {
  assert.equal(C.previewCss('color-mix(in srgb, var(--tb-primary, #4f46e5) 50%, transparent)', theme), 'rgba(225, 29, 72, 0.5)');
  assert.equal(C.previewCss('transparent', theme), 'transparent');
  assert.equal(C.inputHex('var(--tb-primary, #4f46e5)', theme), '#e11d48');
  assert.equal(C.inputHex('primary', theme, '#123456'), '#123456');
  assert.equal(C.inputHex('', theme), '#000000');
});
