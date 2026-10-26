<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/langsync.php';
require_once __DIR__ . '/inc/daterange.php';
require_auth();

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$role = $_GET['role'] ?? '';
// a language tab ('' = all): its users, the way the bot counts them
$lang = web_lang_pick('lang', '');
[$langSQL, $langParams] = $lang !== '' ? user_lang_where($lang) : ['1 = 1', []];
// the order: ?sort=new | charge_desc | charge_asc | balance_desc | balance_asc
$sortOptions = ['new', 'charge_desc', 'charge_asc', 'balance_desc', 'balance_asc'];
$sort = in_array($_GET['sort'] ?? '', $sortOptions, true) ? $_GET['sort'] : 'new';
[$sortCol, $sortDir] = array_pad(explode('_', $sort), 2, 'desc');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// 🗓 the time filter (?range=...): the day the user joined
$dr = dr_read();

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(id LIKE ? OR COALESCE(username,'') LIKE ? OR COALESCE(namecustom,'') LIKE ? OR COALESCE(number,'') LIKE ?)";
    // a @username is found without its @, the way it is kept
    $like = '%' . ltrim($search, '@') . '%';
    $params = [$like, $like, $like, $like];
}

if ($status !== '') {
    $where[] = "User_Status = ?";
    $params[] = $status;
}

if ($role !== '') {
    $where[] = "agent = ?";
    $params[] = $role;
}

if ($lang !== '') {
    $where[] = $langSQL;
    $params = array_merge($params, $langParams);
}

$drParams = [];
$drSQL = dr_cond($dr, dr_secs('register'), $drParams);
if ($drSQL !== '') {
    $where[] = $drSQL;
    $params = array_merge($params, $drParams);
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// What each user has charged: the paid top-ups, in the wallet they are on now -
// the balance an admin adds or takes off by hand is no top-up (the bot's own
// report leaves it out too). Amounts of two currencies are not comparable, so
// an amount order goes one currency at a time, the default one first; a user
// who never charged comes last in a charge order either way.
$dirSQL = $sortDir === 'asc' ? 'ASC' : 'DESC';
$userCur = web_cur_sql(null, 'u');
$defaultCur = $pdo->quote((string) currency_default_code());
$chargedSQL = "(SELECT p.id_user AS uid, " . web_cur_sql('p.currency', 'u2') . " AS cur, SUM(CAST(p.price AS DECIMAL(20,2))) AS charged"
    . " FROM Payment_report p LEFT JOIN `user` u2 ON u2.id = p.id_user"
    . " WHERE p.payment_Status = 'paid' AND p.Payment_Method NOT IN ('add balance by admin', 'low balance by admin')"
    . " GROUP BY p.id_user, cur)";
$orderSQL = match ($sortCol) {
    'charge' => "ORDER BY (c.charged IS NULL) ASC, ($userCur = $defaultCur) DESC, $userCur ASC, c.charged $dirSQL, u.id DESC",
    'balance' => "ORDER BY ($userCur = $defaultCur) DESC, $userCur ASC, u.Balance $dirSQL, u.id DESC",
    default => "ORDER BY u.register DESC",
};
$manyCurrencies = count(array_unique(array_map('currency_for_lang', panel_langs()))) > 1;
try {
    $total = db_count($pdo, "SELECT COUNT(*) FROM user $whereSQL", $params);
    $users = db_fetchAll($pdo, "SELECT u.*, c.charged FROM `user` u LEFT JOIN $chargedSQL c ON c.uid = u.id AND c.cur = $userCur $whereSQL $orderSQL LIMIT $perPage OFFSET $offset", $params);
} catch (Exception $e) {
    $total = 0;
    $users = [];
    error_log('users.php: ' . $e->getMessage());
}

$totalPages = max(1, (int) ceil($total / $perPage));

$blockedCount = 0;
$agentCount = 0;
$agentAdvCount = 0;

$langCounts = [];
try {
    $blockedCount = db_count($pdo, "SELECT COUNT(*) FROM user WHERE User_Status='block' AND $langSQL", $langParams);
    $agentCount = db_count($pdo, "SELECT COUNT(*) FROM user WHERE agent='n' AND $langSQL", $langParams);
    $agentAdvCount = db_count($pdo, "SELECT COUNT(*) FROM user WHERE agent='n2' AND $langSQL", $langParams);
    $cnt = um_lang_counts();
    $langCounts = ['' => $cnt['total']] + array_intersect_key($cnt, array_flip(panel_langs()));
} catch (Exception $e) {
}
// a link of this page that keeps the language tab
// the page's address for an order, keeping the filters
$sortUrl = fn(string $order) => 'users.php?' . http_build_query(array_filter(
    ['q' => $search, 'status' => $status, 'role' => $role, 'lang' => $lang, 'sort' => $order === 'new' ? '' : $order] + dr_params($dr),
    fn($v) => $v !== '' && $v !== null
));
// a column's header that sorts: a tap puts the highest first, the next tap the opposite
$sortTh = function (string $col, string $label) use ($sortCol, $sortDir, $sortUrl) {
    $on = $sortCol === $col;
    $next = $on && $sortDir === 'desc' ? $col . '_asc' : $col . '_desc';
    $ico = $on ? ($sortDir === 'desc' ? 'sort-desc' : 'sort-asc') : 'sort';
    return '<th' . ($on ? ' aria-sort="' . ($sortDir === 'desc' ? 'descending' : 'ascending') . '"' : '') . '>'
        . '<a class="th-sort' . ($on ? ' on' : '') . '" href="' . htmlspecialchars($sortUrl($next)) . '">'
        . htmlspecialchars($label) . icon($ico, 14) . '</a></th>';
};
$sortLabels = [
    'new' => $textbotlang['panel']['usersSortNewest'],
    'charge_desc' => $textbotlang['panel']['usersSortChargeDesc'],
    'charge_asc' => $textbotlang['panel']['usersSortChargeAsc'],
    'balance_desc' => $textbotlang['panel']['usersSortBalanceDesc'],
    'balance_asc' => $textbotlang['panel']['usersSortBalanceAsc'],
];
$here = fn(array $q = []) => 'users.php?' . http_build_query(array_filter(['lang' => $lang] + $q + dr_params($dr), fn($v) => $v !== '' && $v !== null));

// 👤 what was typed is exactly one user's id or @username: a way straight to the profile
$jump = null;
if ($search !== '') {
    $needle = ltrim($search, '@');
    try {
        if (ctype_digit($needle)) {
            $jump = db_fetch($pdo, "SELECT id, username, namecustom FROM `user` WHERE id = ? LIMIT 1", [$needle]);
        }
        if (!$jump && preg_match('/^\w{3,64}$/', $needle)) {
            $jump = db_fetch($pdo, "SELECT id, username, namecustom FROM `user` WHERE LOWER(username) = LOWER(?) LIMIT 1", [$needle]);
        }
    } catch (Exception $e) {
    }
}

$pageTitle = $textbotlang['panel']['usersTitle'];
$pageLede = $textbotlang['panel']['usersSubtitle'];
$activeNav = 'users';
include __DIR__ . '/inc/layout_head.php';
echo web_lang_assets();
?>

<?= web_lang_tabs($lang, fn($c) => 'users.php?' . http_build_query(['lang' => $c ?: null] + dr_params($dr)), true, $langCounts) ?>

<div class="card fade-up">
    <div class="toolbar">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <div class="toolbar-title"><?= $textbotlang['panel']['usersHeading'] ?> <small>(<?= number_format($total) ?>)</small></div>
            <a href="cleanup.php<?= $lang !== '' ? '?lang=' . htmlspecialchars($lang) : '' ?>" class="btn-link" style="font-size:.78rem">🧹 <?= htmlspecialchars($textbotlang['panel']['clnUsersLink']) ?></a>

            <?php if ($blockedCount > 0): ?>
                <a href="<?= htmlspecialchars($here(['status' => 'block'])) ?>" class="tag tag-no" style="cursor:pointer"><?= $blockedCount ?> <?= $textbotlang['panel']['usersColId'] ?></a>
            <?php endif; ?>
            <?php if ($agentCount > 0): ?>
                <a href="<?= htmlspecialchars($here(['role' => 'n'])) ?>" class="tag tag-info" style="cursor:pointer"><?= $agentCount ?> <?= $textbotlang['panel']['usersColName'] ?></a>
            <?php endif; ?>
            <?php if ($agentAdvCount > 0): ?>
                <a href="<?= htmlspecialchars($here(['role' => 'n2'])) ?>" class="tag tag-warn" style="cursor:pointer"><?= $agentAdvCount ?> <?= $textbotlang['panel']['usersColUsername'] ?></a>
            <?php endif; ?>
        </div>

        <form method="GET" id="usersForm" class="toolbar-end">
            <?php if ($lang !== ''): ?><input type="hidden" name="lang" value="<?= htmlspecialchars($lang) ?>"><?php endif; ?>
            <select name="sort" class="select" style="width:auto"
                aria-label="<?= htmlspecialchars($textbotlang['panel']['usersSortLabel']) ?>"
                onchange="document.getElementById('usersForm').submit()">
                <?php foreach ($sortLabels as $k => $lbl): ?>
                    <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="select" style="width:auto"
                onchange="document.getElementById('usersForm').submit()">
                <option value=""><?= $textbotlang['panel']['usersColBalance'] ?></option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>><?= $textbotlang['panel']['usersColGroup'] ?></option>
                <option value="block" <?= $status === 'block' ? 'selected' : '' ?>><?= $textbotlang['panel']['usersColStatus'] ?></option>
            </select>

            <select name="role" class="select" style="width:auto"
                onchange="document.getElementById('usersForm').submit()">
                <option value=""><?= $textbotlang['panel']['usersColActions'] ?></option>
                <option value="f" <?= $role === 'f' ? 'selected' : '' ?>><?= $textbotlang['panel']['usersColJoinDate'] ?></option>
                <option value="n" <?= $role === 'n' ? 'selected' : '' ?>><?= $textbotlang['panel']['usersColPhone'] ?></option>
                <option value="n2" <?= $role === 'n2' ? 'selected' : '' ?>><?= $textbotlang['panel']['usersColCustomName'] ?></option>
            </select>

            <?= dr_control($dr) ?>

            <div class="search-box" style="min-width:260px">
                <?= icon('search', 15) ?>
                <input type="text" name="q" placeholder="<?= $textbotlang['panel']['usersSearchUserPlaceholder'] ?>"
                    value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                <button type="button" class="search-clear">✕</button>
                <button type="submit" class="search-btn"><?= $textbotlang['panel']['usersAllGroups'] ?></button>
            </div>

            <?php if ($search || $status || $role || $sort !== 'new' || $dr['active']): ?>
                <a href="<?= htmlspecialchars($here()) ?>" class="btn-link" style="font-size:.78rem;white-space:nowrap"><?= $textbotlang['panel']['usersAllStatuses'] ?></a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($sortCol !== 'new'): ?>
        <div class="field-hint" style="padding:10px 18px 0">
            <?= htmlspecialchars($sortCol === 'charge' ? $textbotlang['panel']['usersSortNote'] : '') ?>
            <?php if ($manyCurrencies && $lang === ''): ?> <?= htmlspecialchars($textbotlang['panel']['paySortCurrencyNote']) ?><?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($jump):
        $jn = $jump['namecustom'] ?? '';
        $ju = $jump['username'] ?? '';
        $jn = $jn === 'none' ? '' : $jn;
        $ju = $ju === 'none' ? '' : $ju;
        $jl = $jn !== '' ? $jn : ($ju !== '' ? '@' . $ju : '#' . $jump['id']);
        ?>
        <a class="jump" href="user.php?id=<?= (int) $jump['id'] ?>">
            <span class="jump-av" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr(ltrim($jl, '@#'), 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
            <span class="jump-tx"><b dir="auto"><?= htmlspecialchars(trunc($jl, 28)) ?></b>
                <small dir="ltr"><?= htmlspecialchars($jump['id']) ?><?= $ju !== '' ? ' · @' . htmlspecialchars($ju) : '' ?></small></span>
            <span class="jump-go"><?= htmlspecialchars($textbotlang['panel']['ulGoProfile']) ?> <?= icon('eye', 14) ?></span>
        </a>
    <?php endif; ?>
    <div class="tbl-wrap">
        <table class="tbl-xl">
            <thead>
                <tr>
                    <th style="width:36px">#</th>
                    <th><?= $textbotlang['panel']['usersSearchBtn'] ?></th>
                    <th><?= $textbotlang['panel']['usersClearBtn'] ?></th>
                    <th><?= $textbotlang['panel']['usersGroupFreeUser'] ?></th>
                    <th><?= $textbotlang['panel']['usersGroupNormalAgent'] ?></th>
                    <?= $sortTh('balance', $textbotlang['panel']['usersGroupAdvancedAgent']) ?>
                    <?= $sortTh('charge', $textbotlang['panel']['usersColCharged']) ?>
                    <th><?= $textbotlang['panel']['usersStatusActiveFilter'] ?></th>
                    <th><?= $textbotlang['panel']['usersStatusBlockedFilter'] ?></th>
                    <th><?= $textbotlang['panel']['usersPaginationPrev'] ?></th>
                    <th style="width:150px"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="11">
                            <div class="empty">
                                <svg class="ill" viewBox="0 0 200 160" fill="none">
                                    <circle cx="100" cy="60" r="40" fill="var(--sf3)" />
                                    <circle cx="100" cy="47" r="18" fill="var(--bds)" />
                                    <path d="M62 105 Q100 88 138 105" stroke="var(--bds)" stroke-width="8"
                                        stroke-linecap="round" fill="none" />
                                </svg>
                                <p><?= $dr['active'] ? $textbotlang['panel']['drNoneInRange'] : ($search ? $textbotlang['panel']['usersNoResultFound'] : $textbotlang['panel']['usersNoUserYet']) ?></p>
                            </div>
                        </td>
                    </tr>
                <?php else:
                    $i = $offset + 1;
                    foreach ($users as $u):
                        $agent = $u['agent'] ?? 'f';
                        $isBlocked = ($u['User_Status'] ?? '') === 'block';
                        $name = $u['namecustom'] ?? '';
                        if ($name === 'none')
                            $name = '';
                        $uname = $u['username'] ?? '';
                        if ($uname === 'none')
                            $uname = '';
                        ?>
                        <tr>
                            <td class="cf"><?= $i++ ?></td>
                            <td class="cm"><?= user_link($u['id']) ?></td>
                            <td data-title>
                                <?php if ($uname): ?>
                                    <a class="cm ulink" dir="ltr" style="color:var(--ac);display:inline-block" href="user.php?id=<?= (int) $u['id'] ?>" title="<?= htmlspecialchars($textbotlang['panel']['ulOpenProfile']) ?>">@<?= htmlspecialchars($uname) ?></a>
                                <?php else: ?>
                                    <span class="cf">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="cs"><?= $name ? htmlspecialchars(trunc($name, 20)) : '<span class="cf">—</span>' ?></td>
                            <td class="cm cf">
                                <?= (!empty($u['number']) && $u['number'] !== 'none') ? htmlspecialchars($u['number']) : '—' ?>
                            </td>
                            <td class="cn cs" style="white-space:nowrap">
                                <?= htmlspecialchars(money($u['Balance'] ?? 0, currency_for_user($u))) ?>
                            </td>
                            <td class="cn cs" style="white-space:nowrap">
                                <?= $u['charged'] !== null
                                    ? htmlspecialchars(money($u['charged'], currency_for_user($u)))
                                    : '<span class="cf">—</span>' ?>
                            </td>
                            <td class="cn">
                                <?= (int) ($u['score'] ?? 0) > 0
                                    ? '<span style="color:var(--warn)">⭐ ' . number_format((int) ($u['score'] ?? 0)) . '</span>'
                                    : '<span class="cf">—</span>' ?>
                            </td>
                            <td class="cf"><?= safe_date($u['register'] ?? null) ?></td>
                            <td>
                                <?php if ($isBlocked): ?>
                                    <span class="tag tag-no"><?= $textbotlang['panel']['usersTotalCountLabel'] ?></span>
                                <?php else: ?>
                                    <span class="tag <?= user_role_tag($agent) ?>">
                                        <?= user_role_label($agent) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:4px">
                                    <a href="user.php?id=<?= (int) $u['id'] ?>" class="btn btn-ghost btn-sm prof-btn"
                                        title="<?= htmlspecialchars($textbotlang['panel']['ulOpenProfile']) ?>">
                                        <?= icon('eye', 14) ?> <span><?= htmlspecialchars($textbotlang['panel']['ulProfile']) ?></span>
                                    </a>
                                    <?php if ($isBlocked): ?>
                                        <a href="user_action.php?action=unblock&id=<?= (int) $u['id'] ?>&_csrf=<?= csrf_token() ?>&back=users.php"
                                            class="btn btn-ok btn-sm btn-icon" title="<?= htmlspecialchars($textbotlang['panel']['usersUnblockBtn']) ?>"
                                            data-confirm="<?= htmlspecialchars(sprintf($textbotlang['panel']['usersConfirmUnblockUser'], $name, $u['id'])) ?>">
                                            <?= icon('check', 13) ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="user_action.php?action=block&id=<?= (int) $u['id'] ?>&_csrf=<?= csrf_token() ?>&back=users.php"
                                            class="btn btn-no btn-sm btn-icon" title="<?= htmlspecialchars($textbotlang['panel']['usersBlockBtn']) ?>"
                                            data-confirm="<?= htmlspecialchars(sprintf($textbotlang['panel']['usersConfirmBlockUser'], $name, $u['id'])) ?>">
                                            <?= icon('block', 13) ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="tbl-foot">
        <span><?= number_format($total) ?> <?= $textbotlang['panel']['usersColReferrer'] ?> <?= $page ?> <?= $textbotlang['panel']['usersColAffiliateCount'] ?> <?= $totalPages ?></span>
        <div class="pager">
            <?php
            $qs = fn($p) => '?q=' . urlencode($search) . ($dr['active'] ? '&' . http_build_query(dr_params($dr)) : '')
                . '&status=' . urlencode($status)
                . '&role=' . urlencode($role)
                . ($sort !== 'new' ? '&sort=' . urlencode($sort) : '')
                . ($lang !== '' ? '&lang=' . urlencode($lang) : '')
                . '&page=' . $p;
            ?>
            <a class="<?= $page <= 1 ? 'dis' : '' ?>" href="<?= $qs(max(1, $page - 1)) ?>">‹</a>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                <a class="<?= $p === $page ? 'cur' : '' ?>" href="<?= $qs($p) ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a class="<?= $page >= $totalPages ? 'dis' : '' ?>" href="<?= $qs(min($totalPages, $page + 1)) ?>">›</a>
        </div>
    </div>
</div>

<script src="js/users.js"></script>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>