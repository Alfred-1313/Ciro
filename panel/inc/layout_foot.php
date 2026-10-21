<?php
// the phone's bottom bar: a bead that slides along it (js/toolbar.js), the
// bar's edge dipping under it and the icon beneath rising into it
$bottomTabs = [
  ['index.php', 'dashboard', 'dashboard', $textbotlang['panel']['layoutFooterCopyright']],
  ['users.php', 'users', 'users', $textbotlang['panel']['layoutFooterVersion']],
  ['invoice.php', 'invoice', 'invoice', $textbotlang['panel']['layoutFooterPoweredBy']],
  ['payment.php', 'payment', 'card', $textbotlang['panel']['layoutFooterLinkSupport']],
  ['settings.php', 'settings', 'settings', $textbotlang['panel']['layoutFooterLinkDocs']],
];
?>
  </main>
</div>

<nav class="mtb" data-toolbar aria-label="<?= htmlspecialchars($textbotlang['panel']['layoutBrandName']) ?>">
  <div class="mtb-ambient" aria-hidden="true"></div>
  <svg class="mtb-surface" aria-hidden="true" preserveAspectRatio="none">
    <defs>
      <linearGradient id="mtb-fill" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" class="mtb-fill-a"/><stop offset="1" class="mtb-fill-b"/>
      </linearGradient>
      <linearGradient id="mtb-rim" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" class="mtb-rim-a"/><stop offset="1" class="mtb-rim-b"/>
      </linearGradient>
    </defs>
    <path class="mtb-path" fill="url(#mtb-fill)" stroke="url(#mtb-rim)" stroke-width="1.4"/>
  </svg>
  <div class="mtb-bead" aria-hidden="true"><span class="mtb-sheen"></span></div>
  <div class="mtb-tabs">
    <?php foreach ($bottomTabs as [$href, $key, $ico, $label]):
      $on = ($activeNav ?? '') === $key; ?>
      <a href="<?= $href ?>" draggable="false" class="mtb-tab<?= $on ? ' on' : '' ?>"<?= $on ? ' aria-current="page"' : '' ?>
        data-label="<?= htmlspecialchars($label) ?>"><?= icon($ico, 23) ?><span class="mtb-label"><?= htmlspecialchars($label) ?></span></a>
    <?php endforeach; ?>
  </div>
</nav>
</div>

<script src="js/app.js?v=<?= (int) @filemtime(__DIR__ . '/../js/app.js') ?>"></script>
<script src="js/toolbar.js?v=<?= (int) @filemtime(__DIR__ . '/../js/toolbar.js') ?>"></script>
</body>
</html>
