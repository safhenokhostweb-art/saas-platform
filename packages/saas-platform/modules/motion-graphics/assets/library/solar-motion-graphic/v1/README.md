# Solar Power Plant Motion — موشن نیروگاه خورشیدی

تابش خورشید روی آرایه پنل‌ها، جریان انرژی از اینورتر به باتری و ساختمان، و کارت‌های تولید امروز، صرفه‌جویی ماهانه و کاهش کربن برای سایت‌های صنعت برق و انرژی خورشیدی.

- Slug: `solar-motion-graphic`, library version `v1` (release `1.0.0`), engine `svg-js` (CSS-animated SVG, no script).
- License tier: premium. Visibility: **private** (ADR-086). It is delivered only to sites granted it in the central panel (Admin → Motion Graphics → catalog), never to every site, and the `motion-graphics-premium` licence alone does not unlock it.
- Delivery: only through site-scoped signed URLs (`/motion/private/solar-motion-graphic/v1/{site}/{expires}/{signature}/animation.svg`). The public route `/motion/solar-motion-graphic/v1/animation.svg` answers 404.
- Canvas: 960×540 (`viewBox="0 0 960 540"`), RTL layout, Persian digits, light and dark through the `--asset-*` tokens in `<style id="asset-theme">`.
- Motion: an intro that plays once, then ambient loops (sun rays, light beams, energy flow, panel sweep). Only `transform`, `opacity` and stroke-dash properties animate; `prefers-reduced-motion: reduce` leaves the complete final frame.
- Runtime parameters (`metadata.json` → `parameters`): every `data-p` text, the primary/secondary/accent/success colours, and `animate`, `loop`, `speed`. Durations are `calc(Ns*var(--k,1))`.
- Font: Vazirmatn is embedded by the platform at delivery; the stack is `'Vazirmatn',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif`.
- `preview.svg` is the same artwork with animation disabled. Never edit a published version in place: add a new version directory.
