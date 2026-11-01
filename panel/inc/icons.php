<?php

// A line icon of the panel (Lucide, see icon_set.php). Always decoration: the
// words next to it (or the aria-label of the button around it) say what it is,
// so it is hidden from screen readers. $class adds to the icon's own classes -
// css/shab.css moves a few of them (a gear turns, a bell rings) on hover.
function icon(string $name, int $size = 16, string $class = ''): string {
    static $set = null;
    // the names the pages used before the Lucide set came
    static $alias = [
        'dashboard' => 'layout-dashboard', 'invoice' => 'receipt-text', 'card' => 'credit-card', 'logout' => 'log-out',
        'edit' => 'square-pen', 'trash' => 'trash-2', 'block' => 'ban', 'close' => 'x', 'chart' => 'chart-no-axes-column',
        'sort' => 'chevrons-up-down', 'sort-asc' => 'chevron-up', 'sort-desc' => 'chevron-down',
    ];
    if ($set === null) {
        $set = require __DIR__ . '/icon_set.php';
    }
    $key = $alias[$name] ?? $name;
    $svg = $set[$key] ?? '';
    $cls = 'ic ic-' . preg_replace('/[^a-z0-9-]/', '', $key) . ($class !== '' ? ' ' . $class : '');
    return "<svg class='$cls' xmlns='http://www.w3.org/2000/svg' width='$size' height='$size' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round' aria-hidden='true' focusable='false'>$svg</svg>";
}

// the moon-and-swoosh mark of the «شب» look, in the sidebar and on the login page
function panel_brand_mark(): string {
    return '<svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="27" fill="none" stroke="currentColor" stroke-width="5"/><path d="M45 17c-9-6-23-2-26 9 7-4 14-1 15 5-10-4-20 4-18 14 5-4 10-4 14-1-2 4-5 7-10 9 20 5 35-12 30-27-1 6-4 8-8 9 3-7 3-12-4-16 3-1 5-1 7-2Z" fill="currentColor"/></svg>';
}

// a label of the panel's own chrome (menu, page titles, tabs) without its
// emoji - there the line icons stand in for them
function ui_plain(string $s): string {
    // every emoji (not © ® ™, which are typography), and what an emoji drags along
    $s = preg_replace('/(?:(?![\x{00A9}\x{00AE}\x{2122}])\p{Extended_Pictographic}|[\x{1F1E6}-\x{1F1FF}\x{FE0F}\x{FE0E}\x{200D}\x{20E3}\x{1F3FB}-\x{1F3FF}\x{E0020}-\x{E007F}])/u', '', $s) ?? $s;
    return trim(preg_replace('/\s{2,}/u', ' ', $s) ?? $s);
}

// the flag of one of the bot's languages, a rounded vector (img/flags/<code>.svg)
function panel_flag(string $code): string {
    if (!preg_match('/^[a-z]{2}$/', $code) || !is_file(__DIR__ . '/../img/flags/' . $code . '.svg')) {
        return '';
    }
    return '<img class="flag" src="img/flags/' . $code . '.svg" alt="" width="21" height="14">';
}

// ⟦i:name⟧ (or ⟦i:name:class⟧) in a page's text is drawn as that line icon -
// the panel's own text has no emoji; where one used to mark a heading, a tab or
// a state, a token stands in for it and this turns it into the SVG on the way
// out (every page through layout_head.php). Only text gets icons: a token in a
// tag (an attribute), in <option>/<textarea>/<title> or in a script or style
// block is dropped instead, as an <svg> cannot live there.
const UI_ICON_TOKEN = '/⟦i:([a-z0-9-]+)(?::([a-z0-9 -]+))?⟧/u';
function ui_rich_filter(string $html): string
{
    if (strpos($html, '⟦') === false) {
        return $html;
    }
    $plain = fn(string $s): string => preg_replace(UI_ICON_TOKEN, '', $s) ?? $s;
    $html = preg_replace_callback('#<(option|textarea|title)\b[^>]*>.*?</\1>#is', fn($m) => $plain($m[0]), $html) ?? $html;
    $parts = preg_split('#(<script\b.*?</script>|<style\b.*?</style>|<[^>]*>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return $plain($html);
    }
    foreach ($parts as $i => $p) {
        $parts[$i] = $i % 2 === 1
            ? $plain($p)
            : (preg_replace_callback(UI_ICON_TOKEN, fn($m) => icon($m[1], 15, 'ic-t' . (isset($m[2]) ? ' ic-' . str_replace(' ', ' ic-', trim($m[2])) : '')), $p) ?? $p);
    }
    return implode('', $parts);
}
// the token for $name, for PHP that builds a label: ui_ico('gift') . ' ' . $t['x']
function ui_ico(string $name, string $class = ''): string
{
    return '⟦i:' . $name . ($class !== '' ? ':' . $class : '') . '⟧';
}

// a Rebecca node's state as the panel shows it: an icon in the state's colour
// (the bot keeps its coloured circles - rbn_status_icon())
function ui_node_state_icon(string $status): string
{
    return [
        'connected' => '⟦i:circle-check:ok⟧', 'connecting' => '⟦i:loader-circle:warn spin⟧', 'error' => '⟦i:circle-x:no⟧',
        'disabled' => '⟦i:circle-pause:muted⟧', 'limited' => '⟦i:circle-alert:warn⟧', 'unreachable' => '⟦i:circle-x:no⟧',
    ][$status] ?? '⟦i:circle:muted⟧';
}
