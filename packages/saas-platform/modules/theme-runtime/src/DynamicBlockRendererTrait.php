<?php

namespace SaaSPlatform\ThemeRuntime;

use SaaSPlatform\ThemeRuntime\DataBinding\DataFetcher;
use SaaSPlatform\ThemeRuntime\DataBinding\DataFetchResult;

/** Rendering extension for Phase 3C/M2 dynamic widgets. */
trait DynamicBlockRendererTrait
{
    private ?DataFetcher $dynamicDataFetcher = null;

    protected function renderDynamicProductGrid(array $props, string $inner = '', string $id = ''): string
    {
        $result = $this->dynamicResult($props, $id, ['static', 'internal']);
        $title = $this->escape($props['title'] ?? 'محصولات منتخب');
        $requestedColumns = (string) ($props['columns'] ?? '4');
        $columns = in_array($requestedColumns, ['2', '3', '4'], true) ? $requestedColumns : '4';
        $content = $this->renderDynamicState($result, $props, 'products');

        if ($result->status === DataFetchResult::SUCCESS) {
            $cards = '';
            foreach ($result->items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $itemTitle = $this->escape($item['title'] ?? $item['name'] ?? '');
                $price = $this->escape($item['price'] ?? '');
                $excerpt = $this->escape($item['excerpt'] ?? $item['description'] ?? '');
                $url = $this->escapeUrl((string) ($item['url'] ?? '#'));
                $image = $this->escapeUrl((string) ($item['image'] ?? ''), '');
                $imageHtml = $image !== ''
                    ? '<img class="tb-dynamic-card-image" src="'.$image.'" alt="'.$this->escapeAttr($item['image_alt'] ?? $itemTitle).'" loading="lazy">'
                    : '';
                $priceHtml = $price !== '' ? '<span class="tb-dynamic-card-meta">'.$price.'</span>' : '';
                $excerptHtml = $excerpt !== '' ? '<p class="tb-dynamic-card-excerpt">'.$excerpt.'</p>' : '';
                $cards .= '<article class="tb-dynamic-card"><a class="tb-dynamic-card-link" href="'.$url.'">'
                    .$imageHtml.'<h3 class="tb-dynamic-card-title">'.$itemTitle.'</h3>'.$priceHtml.$excerptHtml.'</a></article>';
            }
            $content = $cards !== ''
                ? '<div class="tb-dynamic-product-items" style="--tb-dynamic-columns:'.$columns.'">'.$cards.'</div>'
                : $this->renderDynamicState(DataFetchResult::empty(), $props, 'products');
        }

        return '<section class="tb-dynamic-widget tb-dynamic-product-grid" data-state="'.$result->status.'">'
            .($title !== '' ? '<h2 class="tb-dynamic-title">'.$title.'</h2>' : '').$content.'</section>';
    }

    protected function renderDynamicFeedSlider(array $props, string $inner = '', string $id = ''): string
    {
        $result = $this->dynamicResult($props, $id, ['static', 'api']);
        $title = $this->escape($props['title'] ?? 'تازه‌ترین مطالب');
        $content = $this->renderDynamicState($result, $props, 'feed');

        if ($result->status === DataFetchResult::SUCCESS) {
            $slides = '';
            foreach ($result->items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $itemTitle = $this->escape($item['title'] ?? $item['name'] ?? '');
                $excerpt = $this->escape($item['excerpt'] ?? $item['description'] ?? '');
                $url = $this->escapeUrl((string) ($item['url'] ?? '#'));
                $slides .= '<article class="tb-dynamic-feed-slide" tabindex="0"><a href="'.$url.'">'
                    .'<h3 class="tb-dynamic-card-title">'.$itemTitle.'</h3>'
                    .($excerpt !== '' ? '<p class="tb-dynamic-card-excerpt">'.$excerpt.'</p>' : '')
                    .'</a></article>';
            }
            if ($slides === '') {
                $content = $this->renderDynamicState(DataFetchResult::empty(), $props, 'feed');
            } else {
                $domId = 'tb-dynamic-feed-'.substr(hash('sha256', $id), 0, 16);
                $controls = '';
                if (($props['show_controls'] ?? true) !== false) {
                    $controls = '<button type="button" class="tb-dynamic-slider-control tb-dynamic-slider-prev" aria-label="قبلی">‹</button>'
                        .'<button type="button" class="tb-dynamic-slider-control tb-dynamic-slider-next" aria-label="بعدی">›</button>';
                }
                $content = '<div id="'.$domId.'" class="tb-dynamic-feed-frame">'.$controls
                    .'<div class="tb-dynamic-feed-track">'.$slides.'</div></div>'
                    .$this->dynamicSliderScript($domId);
            }
        }

        return '<section class="tb-dynamic-widget tb-dynamic-feed-slider" data-state="'.$result->status.'">'
            .($title !== '' ? '<h2 class="tb-dynamic-title">'.$title.'</h2>' : '').$content.'</section>';
    }

    private function dynamicResult(array $props, string $id, array $allowedTypes): DataFetchResult
    {
        $sourceType = is_string($props['source_type'] ?? null) ? $props['source_type'] : 'static';
        if (!in_array($sourceType, $allowedTypes, true)) {
            return DataFetchResult::error();
        }

        $fetcher = $this->context['data_fetcher'] ?? null;
        if (!$fetcher instanceof DataFetcher) {
            $fetcher = $this->dynamicDataFetcher ??= new DataFetcher;
        }
        return $fetcher->fetch($props, $id);
    }

    private function renderDynamicState(DataFetchResult $result, array $props, string $kind): string
    {
        if ($result->status === DataFetchResult::SUCCESS) {
            return '';
        }
        $property = match ($result->status) {
            DataFetchResult::LOADING => 'loading_text',
            DataFetchResult::EMPTY => 'empty_text',
            default => 'error_text',
        };
        $fallback = match ($result->status) {
            DataFetchResult::LOADING => 'در حال بارگذاری…',
            DataFetchResult::EMPTY => 'موردی یافت نشد.',
            default => 'دریافت اطلاعات ممکن نشد.',
        };
        $role = $result->status === DataFetchResult::ERROR ? 'alert' : 'status';

        return '<div class="tb-dynamic-state tb-dynamic-state-'.$result->status.'" role="'.$role.'" data-kind="'.$kind.'">'
            .$this->escape($props[$property] ?? $fallback).'</div>';
    }

    private function dynamicSliderScript(string $domId): string
    {
        $encodedId = json_encode($domId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        return '<script>(function(){var r=document.getElementById('.$encodedId.');if(!r)return;var t=r.querySelector(".tb-dynamic-feed-track"),s=Array.from(r.querySelectorAll(".tb-dynamic-feed-slide")),i=0;function g(n){if(!s.length)return;i=Math.max(0,Math.min(s.length-1,i+n));s[i].scrollIntoView({behavior:"smooth",block:"nearest",inline:"start"});}var p=r.querySelector(".tb-dynamic-slider-prev"),q=r.querySelector(".tb-dynamic-slider-next");if(p)p.addEventListener("click",function(){g(-1)});if(q)q.addEventListener("click",function(){g(1)});})();</script>';
    }
}
