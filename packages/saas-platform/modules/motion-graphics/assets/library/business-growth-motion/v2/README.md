# Business Growth Motion — موشن رشد کسب‌وکار

گزارش فروش نیم‌سال اول با ماه‌های شمسی از راست به چپ، خط روند در برابر هدف، رشد فصلی، سهم بازار و اعلان سفارش جدید برای لندینگ‌های SaaS، فروشگاه‌ها و خدمات مالی.

- Slug: `business-growth-motion`, library version `v2`, engine `svg-js` (CSS-animated SVG, no script).
- License tier: premium (also requires the motion-graphics-premium license).
- Canvas: 960×540 (`viewBox="0 0 960 540"`), scales to any width; RTL layout, Persian digits.
- Light and dark: `prefers-color-scheme` swaps the `--asset-*` tokens in `<style id="asset-theme">`.
- Motion: an intro that plays once, then ambient loops. Only `transform`, `opacity` and stroke-dash properties animate (no filters, no SMIL). `prefers-reduced-motion: reduce` stops every animation and leaves the complete final frame.
- Delivery: served by the platform at `/motion/business-growth-motion/v2/animation.svg` (MotionLibraryAssetController) straight from this directory; there is no other copy. Change the art only by adding a new version directory; never edit a published version in place.
- `preview.svg` is the same artwork with animation disabled.

## v2 (ADR-079)

- Runtime parameters (`metadata.json` → `parameters`): every text marked `data-p` in the SVG, the primary/secondary/accent colour tokens, and `animate`, `loop` and `speed`. `share_percent` also redraws the donut arc through `--p-share_percent`.
- Durations and delays are `calc(Ns*var(--k,1))`, so `speed` scales the whole timeline.
- Font: Vazirmatn is embedded by the platform at delivery (`assets/fonts/vazirmatn-motion.woff2`, OFL); the stack is `'Vazirmatn',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif`, with root `direction="rtl"`.
