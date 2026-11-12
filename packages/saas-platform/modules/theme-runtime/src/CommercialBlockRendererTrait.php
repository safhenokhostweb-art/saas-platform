<?php

namespace SaaSPlatform\ThemeRuntime;

use SaaSPlatform\ThemeRuntime\DataBinding\DataFetcher;
use SaaSPlatform\ThemeRuntime\DataBinding\DataFetchResult;

/**
 * PLATFORM-THEME-SPEC-v1 (ADR-083): renderers for CommercialBlockTypes.
 *
 * Every colour, radius and font is a `--tb-*` token read by the `.tb-cx-*`
 * rules in theme-runtime-blocks.css; nothing here writes a colour. Props
 * are untrusted: each value is read through a typed helper, so empty,
 * mistyped or hostile props degrade to the widget's own defaults or to an
 * empty state, never to an exception (CommercialWidgetResilienceTest).
 */
trait CommercialBlockRendererTrait
{
    private ?DataFetcher $commercialDataFetcher = null;

    protected function renderHeroWithStats(array $props, string $inner = '', string $id = ''): string
    {
        $layout = $this->cxChoice($props['layout'] ?? null, ['split', 'centered'], 'split');
        $title = $this->cxText($props['title'] ?? '');
        $highlight = $this->cxText($props['highlight'] ?? '');
        $titleHtml = $title === '' && $highlight === '' ? '' : '<h1 class="tb-cx-hero-title">' . $this->escape($title)
            . ($highlight !== '' ? ' <span class="tb-cx-hl">' . $this->escape($highlight) . '</span>' : '') . '</h1>';

        $actions = '';
        foreach ([['primary', 'tb-btn tb-btn-primary'], ['secondary', 'tb-btn tb-cx-btn-ghost']] as [$key, $class]) {
            $label = $this->cxText($props["{$key}_label"] ?? '');
            if ($label !== '') {
                $actions .= '<a class="' . $class . '" href="' . $this->cxUrl($props["{$key}_url"] ?? '#') . '">' . $this->escape($label) . '</a>';
            }
        }

        $stats = '';
        foreach ($this->cxList($props['stats'] ?? []) as $stat) {
            $value = $this->cxText($stat['value'] ?? '');
            $label = $this->cxText($stat['label'] ?? '');
            if ($value !== '' || $label !== '') {
                $stats .= '<li class="tb-cx-stat"><strong class="tb-cx-stat-value">' . $this->escape($this->displayNumber($value)) . '</strong>'
                    . '<span class="tb-cx-stat-label">' . $this->escape($label) . '</span></li>';
            }
        }
        $statsHtml = $stats !== '' ? '<ul class="tb-cx-stats" role="list">' . $stats . '</ul>' : '';

        $image = $this->mediaSrc($props['image'] ?? '');
        $media = '';
        if ($image !== '') {
            $media = '<div class="tb-cx-hero-media"><img src="' . $this->escapeUrl($image, '') . '" alt="'
                . $this->escapeAttr($this->cxText($props['image_alt'] ?? '') ?: $title) . '" loading="eager" decoding="async"></div>';
        } elseif ($layout === 'split' && $statsHtml !== '') {
            // Without an image the figures take the visual column.
            $media = '<div class="tb-cx-hero-media tb-cx-hero-media--stats">' . $statsHtml . '</div>';
            $statsHtml = '';
        }

        $copy = '<div class="tb-cx-hero-copy">'
            . $this->cxOptional('p', 'tb-cx-eyebrow', $props['eyebrow'] ?? '')
            . $titleHtml
            . $this->cxOptional('p', 'tb-cx-tagline', $props['tagline'] ?? '')
            . $this->cxOptional('p', 'tb-cx-lead', $props['subtitle'] ?? '')
            . ($actions !== '' ? '<div class="tb-cx-actions">' . $actions . '</div>' : '')
            . '</div>';

        return $this->cxSection('hero', $props, '<div class="tb-cx-hero tb-cx-hero--' . $layout . ($media === '' ? ' tb-cx-hero--solo' : '') . '">'
            . $copy . $media . '</div>' . $statsHtml);
    }

    protected function renderZigzagRows(array $props, string $inner = '', string $id = ''): string
    {
        $variant = $this->cxChoice($props['variant'] ?? null, ['zigzag', 'steps'], 'zigzag');
        $startEnd = $this->cxChoice($props['start_side'] ?? null, ['start', 'end'], 'start') === 'end';
        $rows = '';
        foreach ($this->cxList($props['rows'] ?? []) as $i => $row) {
            $title = $this->cxText($row['title'] ?? '');
            $text = $this->cxText($row['text'] ?? '');
            if ($title === '' && $text === '') {
                continue;
            }
            $number = $this->cxText($row['number'] ?? '');
            $icon = $this->cxText($row['icon'] ?? '');
            $badge = ($number !== '' ? '<span class="tb-cx-step-num">' . $this->escape($this->displayNumber($number)) . '</span>' : '')
                . ($icon !== '' ? '<span class="tb-cx-icon" aria-hidden="true">' . $this->iconHtml($icon) . '</span>' : '');
            $linkLabel = $this->cxText($row['link_label'] ?? '');
            $copy = ($badge !== '' ? '<div class="tb-cx-row-badges">' . $badge . '</div>' : '')
                . $this->cxOptional('h3', 'tb-cx-row-title', $title)
                . $this->cxOptional('p', 'tb-cx-row-text', $text)
                . ($linkLabel !== '' ? '<a class="tb-cx-link" href="' . $this->cxUrl($row['link_url'] ?? '#') . '">' . $this->escape($linkLabel) . '</a>' : '');

            if ($variant === 'steps') {
                $rows .= '<li class="tb-cx-step">' . $copy . '</li>';
                continue;
            }
            $image = $this->mediaSrc($row['image'] ?? '');
            $media = $image !== ''
                ? '<div class="tb-cx-row-media"><img src="' . $this->escapeUrl($image, '') . '" alt="' . $this->escapeAttr($this->cxText($row['image_alt'] ?? '') ?: $title) . '" loading="lazy" decoding="async"></div>'
                : '';
            $flip = (($i % 2) === 1) !== $startEnd;
            $rows .= '<li class="tb-cx-row' . ($flip ? ' tb-cx-row--flip' : '') . ($media === '' ? ' tb-cx-row--text' : '') . '">'
                . $media . '<div class="tb-cx-row-copy">' . $copy . '</div></li>';
        }

        $body = $rows === '' ? '' : ($variant === 'steps'
            ? '<ol class="tb-cx-steps" role="list">' . $rows . '</ol>'
            : '<ul class="tb-cx-rows" role="list">' . $rows . '</ul>');

        return $this->cxSection('zigzag', $props, $this->cxHead($props) . $body);
    }

    protected function renderTargetAudienceGrid(array $props, string $inner = '', string $id = ''): string
    {
        $columns = $this->cxChoice((string) $this->cxText($props['columns'] ?? ''), ['2', '3', '4'], '3');
        $align = $this->cxChoice($props['card_align'] ?? null, ['center', 'start'], 'center');
        $cards = '';
        foreach ($this->cxList($props['items'] ?? []) as $item) {
            $title = $this->cxText($item['title'] ?? '');
            $text = $this->cxText($item['text'] ?? '');
            if ($title === '' && $text === '') {
                continue;
            }
            $icon = $this->cxText($item['icon'] ?? '');
            $label = $this->cxText($item['link_label'] ?? '');
            $cards .= '<li class="tb-cx-card">'
                . ($icon !== '' ? '<span class="tb-cx-card-icon" aria-hidden="true">' . $this->iconHtml($icon) . '</span>' : '')
                . $this->cxOptional('h3', 'tb-cx-card-title', $title)
                . $this->cxOptional('p', 'tb-cx-card-text', $text)
                . ($label !== '' ? '<a class="tb-cx-btn-outline" href="' . $this->cxUrl($item['link_url'] ?? '#') . '">' . $this->escape($label) . '</a>' : '')
                . '</li>';
        }

        return $this->cxSection('audience', $props, $this->cxHead($props)
            . ($cards !== '' ? '<ul class="tb-cx-cards tb-cx-cards--' . $align . '" style="--tb-cx-cols:' . $columns . '" role="list">' . $cards . '</ul>' : ''));
    }

    protected function renderEventCalendar(array $props, string $inner = '', string $id = ''): string
    {
        $events = [];
        foreach ($this->cxSourceItems($props, $id) as $event) {
            $title = $this->cxText($event['title'] ?? '');
            if ($title === '') {
                continue;
            }
            $events[] = [
                'title' => $title,
                'day' => $this->cxInt($event['day'] ?? null, 1, 31),
                'start' => $this->cxText($event['date_label'] ?? ''),
                'end' => $this->cxText($event['end_label'] ?? ''),
                'hours' => $this->cxText($event['hours'] ?? ''),
                'status' => $this->cxChoice($event['status'] ?? null, array_keys(CommercialBlockTypes::EVENT_STATUSES), 'registering'),
                'url' => $event['url'] ?? '#',
            ];
        }

        $days = $this->cxInt($props['days_in_month'] ?? null, 29, 31) ?? 31;
        $offset = array_search($this->cxChoice($props['first_weekday'] ?? null, array_keys(CommercialBlockTypes::WEEKDAYS), 'sat'), array_keys(CommercialBlockTypes::WEEKDAYS), true);
        $byDay = [];
        foreach ($events as $event) {
            if ($event['day'] !== null && $event['day'] <= $days) {
                $byDay[$event['day']][] = $event;
            }
        }

        $grid = '';
        foreach (CommercialBlockTypes::WEEKDAYS as $weekday) {
            $grid .= '<span class="tb-cx-cal-wd" aria-hidden="true">' . $this->escape(mb_substr($weekday, 0, 1)) . '</span>';
        }
        $grid .= str_repeat('<span class="tb-cx-cal-day tb-cx-cal-day--pad" aria-hidden="true"></span>', $offset);
        for ($day = 1; $day <= $days; $day++) {
            $label = $this->escape($this->displayNumber((string) $day));
            if (empty($byDay[$day])) {
                $grid .= '<span class="tb-cx-cal-day">' . $label . '</span>';
                continue;
            }
            $first = $byDay[$day][0];
            $names = implode('، ', array_column($byDay[$day], 'title'));
            $grid .= '<a class="tb-cx-cal-day tb-cx-cal-day--event tb-cx-status--' . $first['status'] . '" href="' . $this->cxUrl($first['url']) . '"'
                . ' aria-label="' . $this->escapeAttr($this->displayNumber((string) $day) . ' — ' . $names) . '">' . $label . '</a>';
        }

        $legend = '';
        if ($this->cxBool($props['show_legend'] ?? true)) {
            $counts = array_count_values(array_column($events, 'status'));
            foreach (CommercialBlockTypes::EVENT_STATUSES as $status => $statusLabel) {
                $legend .= '<li class="tb-cx-legend-item tb-cx-status--' . $status . '"><span class="tb-cx-dot" aria-hidden="true"></span>'
                    . $this->escape($statusLabel) . '<b>' . $this->escape($this->displayNumber((string) ($counts[$status] ?? 0))) . '</b></li>';
            }
            $legend = '<ul class="tb-cx-legend" role="list">' . $legend . '</ul>';
        }

        $list = '';
        foreach ($events as $event) {
            $range = $event['start'] . ($event['end'] !== '' && $event['end'] !== $event['start'] ? ' تا ' . $event['end'] : '');
            $list .= '<li class="tb-cx-event"><span class="tb-cx-pill tb-cx-status--' . $event['status'] . '">'
                . $this->escape(CommercialBlockTypes::EVENT_STATUSES[$event['status']]) . '</span>'
                . '<a class="tb-cx-event-title" href="' . $this->cxUrl($event['url']) . '">' . $this->escape($event['title']) . '</a>'
                . '<span class="tb-cx-meta">' . $this->escape($this->displayNumber($range)) . ($event['hours'] !== '' ? ' · ' . $this->escape($this->displayNumber($event['hours'])) : '') . '</span></li>';
        }
        $list = $list !== '' ? '<ol class="tb-cx-events" role="list">' . $list . '</ol>' : $this->cxEmpty($props, 'رویدادی ثبت نشده است.');

        $month = $this->cxText($props['month_label'] ?? '');
        $calendar = '<div class="tb-cx-cal">' . ($month !== '' ? '<p class="tb-cx-cal-month">' . $this->escape($this->displayNumber($month)) . '</p>' : '')
            . '<div class="tb-cx-cal-grid">' . $grid . '</div>' . $legend . '</div>';

        return $this->cxSection('calendar', $props, $this->cxHead($props) . '<div class="tb-cx-cal-layout">' . $calendar . '<div class="tb-cx-cal-list">' . $list . '</div></div>');
    }

    protected function renderDualSaleProductGrid(array $props, string $inner = '', string $id = ''): string
    {
        $columns = $this->cxChoice((string) $this->cxText($props['columns'] ?? ''), ['2', '3', '4'], '2');
        $buyLabel = $this->cxText($props['buy_label'] ?? '') ?: 'خرید';
        $detailsLabel = $this->cxText($props['details_label'] ?? '') ?: 'جزئیات';
        $cards = '';
        foreach ($this->cxSourceItems($props, $id) as $item) {
            $title = $this->cxText($item['title'] ?? $item['name'] ?? '');
            if ($title === '') {
                continue;
            }
            $url = $this->cxUrl($item['url'] ?? '#');
            $image = $this->mediaSrc($item['image'] ?? '');
            $media = $image !== ''
                ? '<img src="' . $this->escapeUrl($image, '') . '" alt="' . $this->escapeAttr($this->cxText($item['image_alt'] ?? '') ?: $title) . '" loading="lazy" decoding="async">'
                : '<span class="tb-cx-media-icon" aria-hidden="true">' . $this->iconHtml('package') . '</span>';
            $badge = $this->cxText($item['badge'] ?? '');
            $price = $this->cxText($item['price'] ?? '');
            $oldPrice = $this->cxText($item['old_price'] ?? '');
            $productId = $this->cxText($item['product_id'] ?? '');
            $buy = preg_match('/^\d{1,20}$/', $productId) === 1 && $this->cartEndpoints() !== null
                ? $this->productActionHtml(['product_id' => $productId], null, $url, $title)
                : '<a class="tb-btn tb-btn-primary" href="' . $url . '">' . $this->escape($buyLabel) . '</a>';

            $cards .= '<li class="tb-cx-product">'
                . '<a class="tb-cx-product-media' . ($image === '' ? ' tb-cx-media--empty' : '') . '" href="' . $url . '" tabindex="-1" aria-hidden="true">' . $media . '</a>'
                . ($badge !== '' ? '<span class="tb-cx-badge">' . $this->escape($badge) . '</span>' : '')
                . '<div class="tb-cx-product-body"><h3 class="tb-cx-product-title"><a href="' . $url . '">' . $this->escape($title) . '</a></h3>'
                . $this->cxOptional('p', 'tb-cx-meta', $item['meta'] ?? '')
                . ($price !== '' ? '<p class="tb-cx-price">' . ($oldPrice !== '' ? '<del>' . $this->escape($this->displayNumber($oldPrice)) . '</del>' : '')
                    . '<strong>' . $this->escape($this->displayNumber($price)) . '</strong></p>' : '')
                . '<div class="tb-cx-product-actions">' . $buy . '<a class="tb-cx-btn-outline" href="' . $url . '">' . $this->escape($detailsLabel) . '</a></div>'
                . '</div></li>';
        }

        $viewAll = $this->cxText($props['view_all_label'] ?? '');
        $body = $cards !== ''
            ? '<ul class="tb-cx-products" style="--tb-cx-cols:' . $columns . '" role="list">' . $cards . '</ul>'
            : $this->cxEmpty($props, 'محصولی برای نمایش وجود ندارد.');

        return $this->cxSection('products', $props, $this->cxHead($props) . $body
            . ($viewAll !== '' ? '<div class="tb-cx-more"><a class="tb-btn tb-btn-primary" href="' . $this->cxUrl($props['view_all_url'] ?? '/shop') . '">' . $this->escape($viewAll) . '</a></div>' : ''));
    }

    protected function renderCourseVideoFilter(array $props, string $inner = '', string $id = ''): string
    {
        $columns = $this->cxChoice((string) $this->cxText($props['columns'] ?? ''), ['2', '3'], '3');
        $cta = $this->cxText($props['cta_label'] ?? '') ?: 'مشاهدهٔ صفحهٔ آموزش';
        $cards = '';
        foreach (array_values($this->cxSourceItems($props, $id)) as $i => $item) {
            $title = $this->cxText($item['title'] ?? '');
            if ($title === '') {
                continue;
            }
            $access = $this->cxChoice($item['access'] ?? null, ['free', 'paid'], 'free');
            $url = $this->cxUrl($item['url'] ?? '#');
            $image = $this->mediaSrc($item['image'] ?? '');
            $media = $image !== ''
                ? '<img src="' . $this->escapeUrl($image, '') . '" alt="' . $this->escapeAttr($this->cxText($item['image_alt'] ?? '') ?: $title) . '" loading="lazy" decoding="async">'
                : '<span class="tb-cx-media-icon" aria-hidden="true">' . $this->iconHtml('circle-play') . '</span>';
            $dates = array_filter([$this->cxText($item['start_label'] ?? ''), $this->cxText($item['end_label'] ?? '')], fn ($v) => $v !== '');
            $meta = implode(' تا ', $dates);
            $duration = $this->cxText($item['duration'] ?? '');
            $published = preg_replace('/[^0-9\-]/', '', $this->cxText($item['published'] ?? '')) ?? '';

            $cards .= '<li class="tb-cx-course" data-access="' . $access . '" data-index="' . $i . '"'
                . ' data-popularity="' . ($this->cxInt($item['popularity'] ?? null, 0, PHP_INT_MAX) ?? 0) . '" data-published="' . $this->escapeAttr($published) . '">'
                . '<a class="tb-cx-course-media' . ($image === '' ? ' tb-cx-media--empty' : '') . '" href="' . $url . '" tabindex="-1" aria-hidden="true">' . $media . '</a>'
                . '<span class="tb-cx-badge tb-cx-badge--' . $access . '">' . ($access === 'free' ? 'رایگان' : 'هزینه‌دار') . '</span>'
                . '<div class="tb-cx-course-body"><h3 class="tb-cx-course-title"><a href="' . $url . '">' . $this->escape($title) . '</a></h3>'
                . ($meta !== '' || $duration !== '' ? '<p class="tb-cx-meta">' . $this->escape($this->displayNumber($meta))
                    . ($meta !== '' && $duration !== '' ? ' · ' : '') . $this->escape($this->displayNumber($duration)) . '</p>' : '')
                . '<a class="tb-cx-link" href="' . $url . '">' . $this->escape($cta) . '</a></div></li>';
        }

        if ($cards === '') {
            return $this->cxSection('courses', $props, $this->cxHead($props) . $this->cxEmpty($props, 'آموزشی وجود ندارد.'));
        }

        $domId = 'tb-cx-courses-' . substr(hash('sha256', $id !== '' ? $id : $cards), 0, 12);
        $toolbar = '';
        if ($this->cxBool($props['show_filters'] ?? true)) {
            $toolbar .= '<div class="tb-cx-filters" role="group" aria-label="فیلتر نوع دسترسی">';
            foreach (['all' => 'همه', 'free' => 'رایگان', 'paid' => 'هزینه‌دار'] as $value => $label) {
                $toolbar .= '<button type="button" class="tb-cx-filter" data-filter="' . $value . '" aria-pressed="' . ($value === 'all' ? 'true' : 'false') . '">' . $label . '</button>';
            }
            $toolbar .= '</div>';
        }
        if ($this->cxBool($props['show_sort'] ?? true)) {
            $toolbar .= '<label class="tb-cx-sort"><span class="tb-visually-hidden">مرتب‌سازی</span><select data-sort aria-label="مرتب‌سازی آموزش‌ها">'
                . '<option value="default">ترتیب پیش‌فرض</option><option value="newest">جدیدترین</option><option value="oldest">قدیمی‌ترین</option><option value="popular">محبوب‌ترین</option></select></label>';
        }

        return $this->cxSection('courses', $props, $this->cxHead($props)
            . '<div id="' . $domId . '" class="tb-cx-course-filter">'
            . ($toolbar !== '' ? '<div class="tb-cx-toolbar">' . $toolbar . '</div>' : '')
            . '<ul class="tb-cx-courses" style="--tb-cx-cols:' . $columns . '" role="list" aria-live="polite">' . $cards . '</ul>'
            . '<p class="tb-cx-empty" hidden>' . $this->escape($this->cxText($props['empty_text'] ?? '') ?: 'آموزشی با این فیلتر وجود ندارد.') . '</p>'
            . '</div>' . $this->cxCourseFilterScript($domId));
    }

    protected function renderDownloadableAssets(array $props, string $inner = '', string $id = ''): string
    {
        $columns = $this->cxChoice((string) $this->cxText($props['columns'] ?? ''), ['1', '2', '3'], '2');
        $button = $this->cxText($props['button_label'] ?? '') ?: 'دانلود';
        $files = '';
        foreach ($this->cxSourceItems($props, $id) as $item) {
            $title = $this->cxText($item['title'] ?? '');
            if ($title === '') {
                continue;
            }
            $href = $this->cxText($this->linkValue($item['file_url'] ?? ''));
            $type = $this->cxChoice($item['file_type'] ?? null, ['pdf', 'doc', 'xls', 'zip', 'other'], 'other');
            $size = $this->cxText($item['size_label'] ?? '');
            $action = $href === '' || $href === '#'
                ? '<span class="tb-cx-btn-outline is-disabled" aria-disabled="true">به‌زودی</span>'
                : '<a class="tb-btn tb-btn-primary" href="' . $this->escapeUrl($href) . '" download rel="noopener" aria-label="'
                    . $this->escapeAttr($button . ' ' . $title . ($size !== '' ? ' (' . $this->displayNumber($size) . ')' : '')) . '">'
                    . $this->iconHtml('download') . '<span>' . $this->escape($button) . '</span></a>';
            $files .= '<li class="tb-cx-file tb-cx-file--' . $type . '"><span class="tb-cx-card-icon" aria-hidden="true">' . $this->iconHtml('file-text') . '</span>'
                . '<div class="tb-cx-file-body"><strong class="tb-cx-file-title">' . $this->escape($title) . '</strong>'
                . $this->cxOptional('span', 'tb-cx-meta', $item['description'] ?? '')
                . ($size !== '' ? '<span class="tb-cx-meta">' . $this->escape($this->displayNumber($size)) . '</span>' : '')
                . '</div>' . $action . '</li>';
        }

        return $this->cxSection('downloads', $props, $this->cxHead($props) . ($files !== ''
            ? '<ul class="tb-cx-files" style="--tb-cx-cols:' . $columns . '" role="list">' . $files . '</ul>'
            : $this->cxEmpty($props, 'فایلی برای دانلود وجود ندارد.')));
    }

    /* ── shared helpers ───────────────────────────────────────────────── */

    private function cxSection(string $kind, array $props, string $body): string
    {
        $spacing = $this->cxChoice($props['section_spacing'] ?? null, ['none', 'compact', 'regular'], 'none');

        return '<section class="tb-cx tb-cx--' . $kind . ' tb-cx-space--' . $spacing . '"><div class="tb-cx-inner">' . $body . '</div></section>';
    }

    private function cxHead(array $props): string
    {
        $head = $this->cxOptional('p', 'tb-cx-eyebrow', $props['eyebrow'] ?? '')
            . $this->cxOptional('h2', 'tb-cx-title', $props['title'] ?? '')
            . $this->cxOptional('p', 'tb-cx-subtitle', $props['subtitle'] ?? '');

        return $head === '' ? '' : '<header class="tb-cx-head">' . $head . '</header>';
    }

    private function cxOptional(string $tag, string $class, mixed $value): string
    {
        $text = $this->cxText($value);

        return $text === '' ? '' : "<{$tag} class=\"{$class}\">" . $this->escape($text) . "</{$tag}>";
    }

    private function cxEmpty(array $props, string $fallback): string
    {
        return '<p class="tb-cx-empty" role="status">' . $this->escape($this->cxText($props['empty_text'] ?? '') ?: $fallback) . '</p>';
    }

    /** A trimmed scalar, or '' for arrays, objects and null. */
    private function cxText(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function cxChoice(mixed $value, array $allowed, string $fallback): string
    {
        $value = is_scalar($value) ? (string) $value : '';

        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function cxInt(mixed $value, int $min, int $max): ?int
    {
        if (is_string($value)) {
            $value = strtr(trim($value), ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);
        }
        if (!is_numeric($value)) {
            return null;
        }
        $int = (int) $value;

        return $int < $min || $int > $max ? null : $int;
    }

    private function cxBool(mixed $value): bool
    {
        return !in_array($value, [false, 0, '0', 'false', 'off', ''], true);
    }

    /** A link prop (string or entity link) as an escaped, safe href. */
    private function cxUrl(mixed $value): string
    {
        return $this->escapeUrl($this->cxText($this->linkValue($value)) ?: '#');
    }

    /**
     * A repeater value — a list or its JSON string — as a bounded list of
     * item arrays. Anything else is an empty list.
     *
     * @return list<array>
     */
    private function cxList(mixed $value): array
    {
        if (is_string($value)) {
            $value = trim($value) === '' ? [] : json_decode($value, true, 8);
        }
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_slice(array_values($value), 0, 60), 'is_array'));
    }

    /**
     * The Phase 3C data_source contract: `static` renders the manual
     * repeater; `internal` asks the host's resolver. A host with no resolver
     * (the Builder preview) or a failing one falls back to the authored
     * items, so a page shows its content rather than a spinner; an internal
     * source that answers "no rows" renders the empty state.
     *
     * @return list<array>
     */
    private function cxSourceItems(array $props, string $id): array
    {
        $manual = $this->cxList($props['items'] ?? []);
        if (($props['source_type'] ?? 'static') !== 'internal') {
            return $manual;
        }
        $fetcher = $this->context['data_fetcher'] ?? null;
        if (!$fetcher instanceof DataFetcher) {
            $fetcher = $this->commercialDataFetcher ??= new DataFetcher();
        }
        $result = $fetcher->fetch($props, $id);

        return match ($result->status) {
            DataFetchResult::SUCCESS => $this->cxList($result->items),
            DataFetchResult::EMPTY => [],
            default => $manual,
        };
    }

    /** Free / paid filter and sort for one course grid, scoped to its id. */
    private function cxCourseFilterScript(string $domId): string
    {
        $encodedId = json_encode($domId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return '<script>(function(){var r=document.getElementById(' . $encodedId . ');if(!r||r.dataset.ready)return;r.dataset.ready="1";'
            . 'var list=r.querySelector(".tb-cx-courses"),empty=r.querySelector(".tb-cx-empty"),sel=r.querySelector("[data-sort]"),f="all";'
            . 'function items(){return Array.prototype.slice.call(list.children);}'
            . 'function apply(){var n=0;items().forEach(function(li){var show=f==="all"||li.getAttribute("data-access")===f;li.hidden=!show;if(show)n++;});if(empty)empty.hidden=n>0;}'
            . 'function sort(){var m=sel?sel.value:"default",a=items();a.sort(function(x,y){var d=0;'
            . 'if(m==="popular")d=(+y.dataset.popularity||0)-(+x.dataset.popularity||0);'
            . 'else if(m!=="default"){var px=x.dataset.published||"",py=y.dataset.published||"";d=px<py?-1:px>py?1:0;if(m==="newest")d=-d;}'
            . 'return d||(+x.dataset.index)-(+y.dataset.index);});'
            . 'a.forEach(function(li){list.appendChild(li);});}'
            . 'r.addEventListener("click",function(e){var b=e.target.closest&&e.target.closest(".tb-cx-filter");if(!b||!r.contains(b))return;f=b.getAttribute("data-filter");'
            . 'r.querySelectorAll(".tb-cx-filter").forEach(function(o){o.setAttribute("aria-pressed",o===b?"true":"false");});apply();});'
            . 'if(sel)sel.addEventListener("change",sort);})();</script>';
    }
}
