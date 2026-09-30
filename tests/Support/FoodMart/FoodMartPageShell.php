<?php

namespace Tests\Support\FoodMart;

/**
 * FoodMart Phase 2A — the true page-shell pieces (doctype/head/final
 * <script> includes) that are NOT modeled as foodmart_* blocks, exactly
 * mirroring how the real platform's own layout (customer-site's
 * layouts/canvas-theme.blade.php) separates document-level concerns from
 * block/content-area rendering. HEAD/SCRIPTS below are byte-exact extracts
 * from the Golden Reference's <head> and closing <script> tags (see the
 * Phase 2A report for the extraction method) — never re-read at runtime.
 *
 * Test-only support code (tests/Support), not application code — this
 * class exists purely to assemble the Phase 2A QA prototype page, it is
 * not part of any real rendering path.
 */
final class FoodMartPageShell
{
    public const DOCTYPE_OPEN = "<!DOCTYPE html>\n<html lang=\"en\">\n";
    public const BODY_OPEN = "<body>\n\n";
    public const BODY_CLOSE = "\n  </body>\n</html>\n";

    public const HEAD = <<<'__FOODMART_RAW_EOF__'
<head>
    <title>FoodMart - Free eCommerce Grocery Store HTML Website Template</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="author" content="">
    <meta name="keywords" content="">
    <meta name="description" content="">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-KK94CHFLLe+nY2dmCWGMq91rCGa5gtU4mk92HdvYe+M/SXH301p5ILy+dN9+nJOZ" crossorigin="anonymous">
    <link rel="stylesheet" type="text/css" href="css/vendor.css">
    <link rel="stylesheet" type="text/css" href="style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&family=Open+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">

  </head>
__FOODMART_RAW_EOF__;

    public const SCRIPTS = <<<'__FOODMART_RAW_EOF__'
<script src="js/jquery-1.11.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" crossorigin="anonymous"></script>
    <script src="js/plugins.js"></script>
    <script src="js/script.js"></script>
__FOODMART_RAW_EOF__;
}
