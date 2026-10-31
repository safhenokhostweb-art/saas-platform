<?php

namespace Tests\Unit\ThemeBuilder;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SaaSPlatform\ThemeRuntime\BlockRenderer;

/**
 * WU-C (commercial tokens) ratchet. Counts raw colour literals — hex,
 * rgb()/rgba(), hsl()/hsla() — that sit outside any `var(...)` in the
 * code (comments excluded) of each WU-C target. A budget may only go
 * down: tokenising another literal means lowering its number here, and a
 * new hardcoded colour in these places fails the suite.
 *
 * Every remaining literal has no token it can route through without a
 * visible change for an existing Theme (the zero-diff rule), so it stays
 * until a token for it is approved.
 */
class CommercialTokenRatchetTest extends TestCase
{
    private const SHEET = __DIR__ . '/../../../packages/saas-platform/modules/theme-runtime/resources/css/theme-runtime-blocks.css';

    private const RAW_COLOR = '/#[0-9a-fA-F]{3,8}\b|\brgba?\(|\bhsla?\(/';

    /** @return array<string, array{string, int}> */
    public static function rendererBudgets(): array
    {
        return [
            'renderModal' => ['renderModal', 0],
            'renderStatsRow' => ['renderStatsRow', 0],
            'renderCategorySlider' => ['renderCategorySlider', 0],
            'renderContactSplit' => ['renderContactSplit', 0],
            // Icon tint #a5b4fc on the --tb-media-placeholder surface.
            'imageWithFallback' => ['imageWithFallback', 2],
            // Glass secondary button on the brand gradient (white .15/.4).
            'renderHeroGradient' => ['renderHeroGradient', 2],
            // Item bg/text defaults (#f8fafc/#0f172a, no equal token
            // default), glow default, and the pulse keyframes.
            'renderBentoGrid' => ['renderBentoGrid', 7],
            // Hex defaults feed readableTextColorOn() contrast math and are
            // emitted as the block's own --tb-header-* variables.
            'renderHeaderModernV1' => ['renderHeaderModernV1', 4],
            // Dark footer defaults; --tb-text/--tb-border would invert on a
            // dark Theme.
            'renderNavFooter' => ['renderNavFooter', 2],
        ];
    }

    #[DataProvider('rendererBudgets')]
    public function test_renderer_target_stays_within_its_raw_colour_budget(string $method, int $budget): void
    {
        $found = $this->rawColors($this->methodCode($method));

        $this->assertSame(
            $budget,
            count($found),
            "{$method}: expected {$budget} raw colour(s) outside var(), found " . count($found) . ' (' . implode(' ', $found) . ').'
            . ' Route new colours through var(--tb-<token>, <literal>); if you removed one, lower the budget.'
        );
    }

    /** @return array<string, array{string, string}> */
    public static function sheetLiterals(): array
    {
        return [
            'star amber' => ['#f59e0b', '--tb-star'],
            'strong border gray' => ['#d1d5db', '--tb-border-strong'],
        ];
    }

    #[DataProvider('sheetLiterals')]
    public function test_shared_sheet_uses_this_literal_only_as_a_token_fallback(string $literal, string $token): void
    {
        $css = (string) file_get_contents(self::SHEET);

        $this->assertStringNotContainsString($literal, $this->stripVar($css), "{$literal} must only appear as var({$token}, {$literal}).");
        $this->assertStringContainsString("var({$token}, {$literal})", $css);
    }

    /** @return array<string, array{string, string}> */
    public static function backdrops(): array
    {
        // The same slate also tints two box-shadows; only backdrops are scoped.
        return [
            'search overlay' => ['.tb-search-overlay__backdrop', 'rgba(15, 23, 42, 0.55)'],
            'cart drawer' => ['.tb-cart-drawer__backdrop', 'rgba(15, 23, 42, 0.5)'],
        ];
    }

    #[DataProvider('backdrops')]
    public function test_backdrop_paints_through_the_token_with_its_own_legacy_fallback(string $selector, string $literal): void
    {
        $css = (string) file_get_contents(self::SHEET);

        $this->assertMatchesRegularExpression(
            '/^' . preg_quote($selector, '/') . ' \{[^}]*background: ' . preg_quote("var(--tb-backdrop, {$literal});", '/') . '/m',
            $css
        );
    }

    public function test_media_placeholder_fallback_is_the_legacy_gradient(): void
    {
        $this->assertStringContainsString(
            'var(--tb-media-placeholder, linear-gradient(135deg,#eef2ff,#f5f3ff))',
            $this->methodCode('imageWithFallback')
        );
    }

    public function test_the_counter_sees_through_var_fallbacks_and_comments(): void
    {
        $this->assertSame([], $this->rawColors($this->code("// #fff\n\$a = 'color:var(--tb-x, rgba(0,0,0,.5))'; /* rgb( */")));
        $this->assertSame(['#fff', 'rgba('], $this->rawColors($this->code("\$a = 'color:#fff;background:rgba(0,0,0,.5)';")));
    }

    private function methodCode(string $method): string
    {
        $ref = new ReflectionMethod(BlockRenderer::class, $method);
        $lines = file($ref->getFileName());

        return $this->code(implode('', array_slice($lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1)));
    }

    /** PHP source with comments blanked out. */
    private function code(string $source): string
    {
        $code = '';
        foreach (token_get_all("<?php\n" . $source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG], true)) {
                $code .= ' ';
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }

    /** @return list<string> */
    private function rawColors(string $code): array
    {
        preg_match_all(self::RAW_COLOR, $this->stripVar($code), $m);

        return $m[0];
    }

    /** Removes every balanced `var(...)`, fallbacks included. */
    private function stripVar(string $s): string
    {
        while (($start = strpos($s, 'var(')) !== false) {
            $depth = 0;
            $end = strlen($s) - 1;
            for ($i = $start + 3, $n = strlen($s); $i < $n; $i++) {
                if ($s[$i] === '(') {
                    $depth++;
                } elseif ($s[$i] === ')' && --$depth === 0) {
                    $end = $i;
                    break;
                }
            }
            $s = substr($s, 0, $start) . substr($s, $end + 1);
        }

        return $s;
    }
}
