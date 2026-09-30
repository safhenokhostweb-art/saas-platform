<?php

namespace Tests\Support\FoodMart;

/**
 * Phase 2B — byte-exact copy (read-only) of customer-site's
 * partials/vazirmatn-font-faces-inline.blade.php. customer-site was only
 * read to produce this, never modified.
 */
final class FoodMartPhase2bFontFaces
{
    public const CSS = <<<'__FOODMART_P2B_FONTFACES_EOF__'
@font-face{
    font-family: 'Vazirmatn';
    src: local('Vazirmatn'), local('Vazirmatn Regular'),
         url('/assets/fonts/vazirmatn/Vazirmatn-Regular.woff2') format('woff2');
    font-weight: 400;
    font-style: normal;
    font-display: swap;
}
@font-face{
    font-family: 'Vazirmatn';
    src: local('Vazirmatn Medium'),
         url('/assets/fonts/vazirmatn/Vazirmatn-Medium.woff2') format('woff2');
    font-weight: 500;
    font-style: normal;
    font-display: swap;
}
@font-face{
    font-family: 'Vazirmatn';
    src: local('Vazirmatn SemiBold'),
         url('/assets/fonts/vazirmatn/Vazirmatn-SemiBold.woff2') format('woff2');
    font-weight: 600;
    font-style: normal;
    font-display: swap;
}
@font-face{
    font-family: 'Vazirmatn';
    src: local('Vazirmatn Bold'),
         url('/assets/fonts/vazirmatn/Vazirmatn-Bold.woff2') format('woff2');
    font-weight: 700;
    font-style: normal;
    font-display: swap;
}
@font-face{
    font-family: 'Vazirmatn';
    src: local('Vazirmatn ExtraBold'),
         url('/assets/fonts/vazirmatn/Vazirmatn-ExtraBold.woff2') format('woff2');
    font-weight: 800;
    font-style: normal;
    font-display: swap;
}

__FOODMART_P2B_FONTFACES_EOF__;
}
