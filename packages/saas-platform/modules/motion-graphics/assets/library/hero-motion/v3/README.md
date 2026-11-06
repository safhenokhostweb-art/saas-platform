# Corporate Hero Motion — موشن قهرمان سازمانی

داشبورد هوشمند سازمان با نمودار رشد، کارت امنیت و اعتماد، پایداری سرویس و پشتیبانی شبانه‌روزی برای صفحه نخست سایت‌های سازمانی و شرکتی.

- Slug: `hero-motion`, library version `v3`, engine `svg-js` (CSS-animated SVG, no script).
- License tier: free (included with the motion-graphics module license).
- Canvas: 960×540 (`viewBox="0 0 960 540"`), scales to any width; RTL layout, Persian digits.
- Light and dark: `prefers-color-scheme` swaps the `--asset-*` tokens in `<style id="asset-theme">`.
- Motion: an intro that plays once, then ambient loops. Only `transform`, `opacity` and stroke-dash properties animate (no filters, no SMIL). `prefers-reduced-motion: reduce` stops every animation and leaves the complete final frame.
- Delivery: served by the platform at `/motion/hero-motion/v3/animation.svg` (MotionLibraryAssetController) straight from this directory; there is no other copy. Change the art only by adding a new version directory; never edit a published version in place.
- `preview.svg` is the same artwork with animation disabled.

## v2 (ADR-079)

- Runtime parameters (`metadata.json` → `parameters`): every text marked `data-p` in the SVG, the primary/secondary/accent colour tokens, and `animate`, `loop` and `speed`.
- Durations and delays are `calc(Ns*var(--k,1))`, so `speed` scales the whole timeline.
- Font: Vazirmatn is embedded by the platform at delivery (`assets/fonts/vazirmatn-motion.woff2`, OFL); the stack is `'Vazirmatn',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif`, with root `direction="rtl"`.

## v3 (ADR-080)

- Font: the complete, unmodified Vazirmatn variable font (every glyph, weights 100–900, `assets/fonts/vazirmatn-variable.woff2`, OFL) is embedded at delivery; metadata `typography.embedded_font` = `vazirmatn-variable`. v2 keeps the ADR-079 subset.
- Every visible text is a `data-p` hook and a `text` parameter.
- Every `--asset-*` token the artwork uses is a `color` parameter, including the new `--asset-oncolor` (text on a coloured fill).
- `scheme` (`auto`/`light`/`dark`) follows the device or pins one palette.
- Sections: each optional part is wrapped in (or marked with) `data-s="show_*"`; a `show` boolean of the same key hides it.