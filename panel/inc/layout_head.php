<?php
require_once __DIR__ . '/icons.php';
$pageLede = $pageLede ?? '';
$activeNav = $activeNav ?? '';
$showPageHead = $showPageHead ?? true;
$currentUser = $_SESSION['admin_user'] ?? $textbotlang['panel']['layoutDefaultAdminName'];
$initials = mb_strtoupper(mb_substr($currentUser, 0, 1, 'UTF-8'), 'UTF-8');
$L = $textbotlang['panel'];
// [href, activeNav key, title (tooltip / search), icon, label]
$navItem = fn(string $href, string $key, string $title, string $icon, string $label) =>
  ['href' => $href, 'key' => $key, 'title' => ui_plain($title), 'icon' => $icon, 'label' => ui_plain($label)];
$pageTitlePlain = ui_plain((string) $pageTitle);
$manageItems = [
  $navItem('users.php', 'users', $L['layoutPageTitleUsers'], 'users', $L['layoutMenuSectionMain']),
  $navItem('invoice.php', 'invoice', $L['layoutPageTitleInvoice'], 'invoice', $L['layoutMenuSectionManagement']),
  $navItem('service.php', 'service', $L['layoutPageTitleService'], 'server', $L['layoutMenuSectionFinancial']),
  $navItem('product.php', 'product', $L['layoutPageTitleProduct'], 'package', $L['layoutMenuSectionSystem']),
  $navItem('servers.php', 'servers', $L['srvPageTitle'], 'server', $L['srvPageTitle']),
  $navItem('category.php', 'category', $L['categoryPageTitle'], 'folder', $L['categoryPageTitle']),
  $navItem('payg.php', 'payg', $L['pgPageTitle'], 'chart', $L['pgPageTitle']),
  $navItem('bulkgift.php', 'bulkgift', $L['bgPageTitle'], 'gift', $L['bgPageTitle']),
];
if (bulkspeed_available()) { /* 🚀 only while a panel can hold a speed limit (Rebecca) */
  $manageItems[] = $navItem('bulkspeed.php', 'bulkspeed', $L['bsPageTitle'], 'zap', $L['bsPageTitle']);
}
array_push(
  $manageItems,
  $navItem('features.php', 'features', $L['featPageTitle'], 'settings', $L['featPageTitle']),
  $navItem('referral.php', 'referral', $L['refPageTitle'], 'users', $L['refPageTitle']),
  $navItem('payment.php', 'payment', $L['layoutPageTitlePayment'], 'card', $L['layoutSearchBoxPlaceholder']),
  $navItem('channels.php', 'channels', $L['chnPageTitle'], 'eye', $L['chnPageTitle']),
  $navItem('help.php', 'help', $L['helpPageTitle'], 'edit', $L['helpPageTitle']),
  $navItem('gateways.php', 'gateways', $L['gwPageTitle'], 'wallet', $L['gwPageTitle']),
  $navItem('topuplook.php', 'topuplook', $L['tlPageTitle'], 'invoice', $L['tlPageTitle']),
  $navItem('discounts.php', 'discounts', $L['discPageTitle'], 'chart', $L['discPageTitle']),
  $navItem('menu.php', 'menu', $L['menuPageTitle'], 'dashboard', $L['menuPageTitle']),
  $navItem('buttons.php', 'buttons', $L['btnPageTitle'], 'package', $L['btnPageTitle']),
  $navItem('looks.php', 'looks', $L['looksPageTitle'], 'menu', $L['looksPageTitle']),
  $navItem('svclook.php', 'svclook', $L['svcPageTitle'], 'package', $L['svcPageTitle']),
  $navItem('bottext.php', 'bottext', $L['bottextPageTitle'], 'edit', $L['bottextPageTitle']),
  $navItem('tools.php', 'tools', $L['toolsPageTitle'], 'settings', $L['toolsPageTitle'])
);
$navSections = [
  ['heading' => $L['layoutNavKeyboard'], 'items' => [
    $navItem('index.php', 'dashboard', $L['layoutPageTitleDashboard'], 'dashboard', $L['layoutNavSettings']),
  ]],
  ['heading' => $L['layoutNavLogout'], 'items' => $manageItems],
  ['heading' => $L['layoutSidebarToggleLabel'], 'items' => [
    $navItem('settings.php', 'settings', $L['layoutPageTitleSettings'], 'settings', $L['layoutProfileMenuLabel']),
    $navItem('logout.php', '', $L['layoutPageTitleLogout'], 'logout', $L['layoutNotificationsLabel']),
  ]],
];
$brandMark = panel_brand_mark();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#0f0f13" id="mtc">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title><?= $textbotlang['panel']['layoutBrandName'] ?></title>
  <link rel="icon" href="img/icon.svg" type="image/svg+xml">
  <link rel="preload" href="fonts/vazirmatn.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="css/fonts.css?v=<?= (int) @filemtime(__DIR__ . '/../css/fonts.css') ?>">
  <link rel="stylesheet" href="css/style.css?v=<?= (int) @filemtime(__DIR__ . '/../css/style.css') ?>">
  <link rel="stylesheet" href="css/shab.css?v=<?= (int) @filemtime(__DIR__ . '/../css/shab.css') ?>">
  <script src="js/theme-init.js?v=<?= (int) @filemtime(__DIR__ . '/../js/theme-init.js') ?>"></script>
  <script>
    window.PANEL_I18N = <?= json_encode(
      array_filter(
        $textbotlang['panel'] ?? [],
        fn($k) => preg_match('/^js[A-Z_]/', (string) $k) === 1,
        ARRAY_FILTER_USE_KEY
      ),
      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;
    window.t = function (key, vars) {
      var s = (window.PANEL_I18N && window.PANEL_I18N[key]) || key;
      if (vars) {
        for (var k in vars) {
          if (Object.prototype.hasOwnProperty.call(vars, k)) {
            s = s.replace('{' + k + '}', vars[k]);
          }
        }
      }
      return s;
    };
  </script>
</head>

<body>

  <div id="load-bar"></div>
  <div id="toast-area"></div>

  <div class="confirm-veil" id="confirm-veil">
    <div class="confirm-box">
      <div class="confirm-icon"><?= icon('block', 26) ?></div>
      <h4 id="confirm-title"><?= $textbotlang['panel']['layoutNavDashboard'] ?></h4>
      <p id="confirm-msg"><?= $textbotlang['panel']['layoutNavUsers'] ?></p>
      <div class="confirm-btns">
        <button class="btn btn-no" id="confirm-ok"><?= $textbotlang['panel']['layoutNavOrders'] ?></button>
        <button class="btn btn-ghost"
          onclick="closeConfirm()"><?= $textbotlang['panel']['layoutNavServices'] ?></button>
      </div>
    </div>
  </div>

  <div class="scenery" aria-hidden="true"><div class="ribbon ribbon-one"></div><div class="ribbon ribbon-two"></div><div class="ribbon ribbon-three"></div><div class="grain"></div></div>

  <div class="app">
    <div class="sidebar-backdrop" id="backdrop"></div>

    <aside class="sidebar" id="sidebar" aria-label="<?= htmlspecialchars($textbotlang['panel']['layoutBrandName']) ?>">
      <button type="button" class="sb-collapse" id="sb-collapse" aria-controls="sidebar" aria-expanded="true"
        aria-label="<?= htmlspecialchars($textbotlang['panel']['jsNavCollapse']) ?>"
        title="<?= htmlspecialchars($textbotlang['panel']['jsNavCollapse']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></button>
      <div class="sidebar-brand">
        <a class="brand-link" href="index.php">
          <span class="brand-mark"><?= $brandMark ?></span>
          <span class="brand-name"><?= $textbotlang['panel']['layoutNavProducts'] ?><span>
            <?= $textbotlang['panel']['layoutNavPayments'] ?></span></span>
        </a>
      </div>
      <div class="nav-search" id="nav-search">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.7" cy="10.7" r="7.3"/><path d="m16 16 4.6 4.6"/></svg>
        <input type="search" id="nav-search-input" autocomplete="off" spellcheck="false"
          placeholder="<?= htmlspecialchars($textbotlang['panel']['layoutNavSearch']) ?>"
          aria-label="<?= htmlspecialchars($textbotlang['panel']['layoutNavSearch']) ?>">
        <kbd id="nav-search-kbd">Ctrl K</kbd>
      </div>
      <nav class="sidebar-nav" id="sidebar-nav">
        <div class="nav-track" id="nav-track" aria-hidden="true"></div>
        <?php foreach ($navSections as $section): ?>
          <div class="nav-section">
            <div class="nav-heading"><?= $section['heading'] ?></div>
            <?php foreach ($section['items'] as $item):
              $isActive = $item['key'] !== '' && $activeNav === $item['key']; ?>
              <a href="<?= $item['href'] ?>" class="nav-item<?= $isActive ? ' active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>
                data-title="<?= htmlspecialchars(strip_tags($item['title'])) ?>">
                <span class="nav-icon"><?= icon($item['icon']) ?></span><span
                  class="nav-label"><?= $item['label'] ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
        <p class="nav-empty" id="nav-empty" hidden><?= htmlspecialchars($textbotlang['panel']['layoutNavNoResults']) ?></p>
      </nav>
      <div class="sidebar-foot">
        <a href="settings.php" class="user-pill" data-title="<?= htmlspecialchars($currentUser) ?>">
          <div class="user-mono"><?= htmlspecialchars($initials) ?></div>
          <div class="user-info">
            <div class="uname"><?= htmlspecialchars($currentUser) ?></div>
            <div class="urole"><?= $textbotlang['panel']['layoutMobileMenuLabel'] ?></div>
          </div>
        </a>
      </div>
    </aside>

    <div class="main">
      <header class="topbar">
        <button type="button" class="icon-btn menu-toggle" onclick="openSidebar()"
          aria-label="<?= htmlspecialchars($textbotlang['panel']['jsNavExpand']) ?>"><?= icon('menu', 18) ?></button>
        <h1 class="topbar-title"><bdi><?= htmlspecialchars($pageTitlePlain) ?></bdi></h1>
      </header>
      <main class="content">
        <?php if ($showPageHead && $pageLede): ?>
          <p class="page-lede fade-up"><?= htmlspecialchars($pageLede) ?></p>
        <?php endif; ?>
        <?php
        $s = get_flash('success');
        $e = get_flash('error');
        $w = get_flash('warning');
        if ($s): ?>
          <div class="notice notice-ok"><?= htmlspecialchars($s) ?></div><?php endif;
        if ($e): ?>
          <div class="notice notice-no"><?= htmlspecialchars($e) ?></div><?php endif;
        if ($w): ?>
          <div class="notice notice-warn"><?= htmlspecialchars($w) ?></div><?php endif; ?>