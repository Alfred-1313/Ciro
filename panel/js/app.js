var _lb = (function () {
    var el   = document.getElementById('load-bar');
    var t    = null;
    var live = false;

    function setW(pct, dur) {
        if (!el) return;
        el.style.setProperty('--lb-w',  pct + '%');
        el.style.setProperty('--lb-dur', dur + 'ms');
        el.className = 'lb-go';
    }

    function start() {
        if (!el) return;
        live = true;
        clearTimeout(t);
        el.className = '';
        el.style.setProperty('--lb-w',  '0%');
        el.style.setProperty('--lb-dur','0ms');
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                setW(30, 300);
                t = setTimeout(function () { setW(60, 900);  }, 350);
                t = setTimeout(function () { setW(80, 1200); }, 1300);
                t = setTimeout(function () { setW(90, 800);  }, 2600);
            });
        });
    }

    function done() {
        if (!el || !live) return;
        live = false;
        clearTimeout(t);
        el.className = 'lb-end';
        t = setTimeout(function () {
            el.className = '';
            el.style.setProperty('--lb-w', '0%');
        }, 450);
    }

    return { start: start, done: done };
}());

window.addEventListener('load', function () { _lb.done(); });

document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href]');
    if (!a) return;
    if (a.target || a.dataset.confirm) return;
    var href = a.href || '';
    if (!href || href.startsWith('javascript') || href.startsWith('#') || href.startsWith('mailto')) return;
    try { if (new URL(href).origin !== location.origin) return; } catch (x) {}
    _lb.start();
});

document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f && f.method && f.method.toLowerCase() !== 'dialog') _lb.start();
});

var _TOAST_ICONS = {
    ok:   '<polyline points="20 6 9 17 4 12"/>',
    no:   '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
    warn: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    info: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'
};

window.toast = function (msg, type, dur) {
    type = type || 'info';
    dur  = dur  || 4500;
    var area = document.getElementById('toast-area');
    if (!area) return;
    var el = document.createElement('div');
    el.className = 'toast toast-' + type;
    el.innerHTML =
        '<div class="toast-icon"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">' +
        (_TOAST_ICONS[type] || _TOAST_ICONS.info) +
        '</svg></div>' +
        '<div style="flex:1;color:var(--text2)">' + String(msg).replace(/</g, '&lt;') + '</div>' +
        '<button class="toast-close">✕</button>';
    area.appendChild(el);
    var timer = setTimeout(function () {
        el.classList.add('closing');
        setTimeout(function () { el.remove(); }, 260);
    }, dur);
    el.querySelector('.toast-close').addEventListener('click', function () {
        clearTimeout(timer);
        el.remove();
    });
};

var _confirmCb = null;

window.showConfirm = function (msg, cb, title) {
    document.getElementById('confirm-title').textContent = title || t('jsConfirmTitle');
    document.getElementById('confirm-msg').textContent   = msg   || t('jsConfirmMsg');
    _confirmCb = cb;
    document.getElementById('confirm-veil').classList.add('open');
};

window.closeConfirm = function () {
    document.getElementById('confirm-veil').classList.remove('open');
    _confirmCb = null;
};

document.getElementById('confirm-ok').addEventListener('click', function () {
    document.getElementById('confirm-veil').classList.remove('open');
    if (_confirmCb) { var cb = _confirmCb; _confirmCb = null; cb(); }
});

document.getElementById('confirm-veil').addEventListener('click', function (e) {
    if (e.target === this) closeConfirm();
});

document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
        e.preventDefault();
        var href = el.href;
        showConfirm(el.dataset.confirm || t('jsConfirmDefault'), function () {
            _lb.start();
            window.location.href = href;
        });
    });
});

var _store = window.panelStore || {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) { } }
};
var _THEME_BG = window.PANEL_THEME_BG || {
    shab: '#0f0f13', navy: '#0F172A', purple: '#180D2E', emerald: '#0A1F1C',
    sunset: '#1A0D0D', slate: '#080808', light: '#F1F5F9',
    linen: '#FAF7F2', mint: '#F0FDF4', lavender: '#FAF5FF'
};
var _LIGHT_THEMES = window.PANEL_LIGHT_THEMES || { light: 1, linen: 1, mint: 1, lavender: 1 };

window.applyTheme = function (t) {
    if (!_THEME_BG[t]) t = 'shab';
    var root = document.documentElement;
    root.setAttribute('data-theme', t);
    root.style.backgroundColor = _THEME_BG[t];
    root.style.colorScheme     = _LIGHT_THEMES[t] ? 'light' : 'dark';
    _store.set('panel-theme', t);
    _store.set('panel-theme-v', '2');
    var mtc = document.getElementById('mtc');
    if (mtc) mtc.content = _THEME_BG[t];
};

var _sb       = document.getElementById('sidebar');
var _sbNav    = document.getElementById('sidebar-nav');
var _sbTrack  = document.getElementById('nav-track');
var _sbToggle = document.getElementById('sb-collapse');
var _sbSearch = document.getElementById('nav-search-input');
var _sbItems  = _sbNav ? Array.prototype.slice.call(_sbNav.querySelectorAll('.nav-item')) : [];
var _isPhone  = function () { return window.matchMedia('(max-width: 768px)').matches; };

// the glowing strip behind the current page: it follows its item when the
// menu is filtered, collapsed or resized
function _updateTrack() {
    if (!_sbTrack) return;
    var cur = null;
    for (var i = 0; i < _sbItems.length; i++) {
        if (_sbItems[i].classList.contains('active') && !_sbItems[i].hidden) { cur = _sbItems[i]; break; }
    }
    if (!cur) { _sbTrack.style.opacity = '0'; return; }
    _sbTrack.style.opacity = '1';
    _sbTrack.style.height = cur.offsetHeight + 'px';
    _sbTrack.style.transform = 'translateY(' + cur.offsetTop + 'px)';
}

window.setSidebarCollapsed = function (collapsed, keep) {
    if (!_sb) return;
    _sb.classList.toggle('collapsed', collapsed);
    if (keep !== false) _store.set('panel-sb-collapsed', collapsed ? '1' : '0');
    if (_sbToggle) {
        var label = collapsed ? t('jsNavExpand') : t('jsNavCollapse');
        _sbToggle.setAttribute('aria-expanded', String(!collapsed));
        _sbToggle.setAttribute('aria-label', label);
        _sbToggle.title = label;
    }
    if (collapsed && _sbSearch && _sbSearch.value) { _sbSearch.value = ''; _filterNav(); }
    _hideTip();
    _updateTrack();
};

window.toggleSidebar = function () {
    if (_sb) setSidebarCollapsed(!_sb.classList.contains('collapsed'));
};

function openSidebar() {
    if (!_sb) return;
    _sb.classList.add('open');
    document.getElementById('backdrop').classList.add('show');
    document.body.style.overflow = 'hidden';
    _updateTrack();
}

function closeSidebar() {
    if (!_sb) return;
    _sb.classList.remove('open');
    var bd = document.getElementById('backdrop');
    if (bd) bd.classList.remove('show');
    document.body.style.overflow = '';
}

if (_sbToggle) _sbToggle.addEventListener('click', toggleSidebar);

// ── menu search ──
var _norm = function (s) {
    return String(s || '').replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/[‌‏‎]/g, '').replace(/\s+/g, ' ').toLowerCase();
};

function _filterNav() {
    if (!_sbNav) return;
    var q = _norm(_sbSearch ? _sbSearch.value.trim() : '');
    var any = false;
    _sbItems.forEach(function (a) {
        var hay = _norm(a.textContent + ' ' + (a.dataset.title || ''));
        a.hidden = q !== '' && hay.indexOf(q) < 0;
        if (!a.hidden) any = true;
    });
    _sbNav.querySelectorAll('.nav-section').forEach(function (sec) {
        sec.hidden = !sec.querySelector('.nav-item:not([hidden])');
    });
    var empty = document.getElementById('nav-empty');
    if (empty) empty.hidden = any;
    _updateTrack();
}

if (_sbSearch) {
    _sbSearch.addEventListener('input', _filterNav);
    _sbSearch.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            e.stopPropagation();
            _sbSearch.value = '';
            _filterNav();
            _sbSearch.blur();
        } else if (e.key === 'Enter') {
            var first = _sbItems.filter(function (a) { return !a.hidden; })[0];
            if (first) { e.preventDefault(); first.click(); }
        } else if (e.key === 'ArrowDown') {
            var next = _sbItems.filter(function (a) { return !a.hidden; })[0];
            if (next) { e.preventDefault(); next.focus(); }
        }
    });
    var _kbd = document.getElementById('nav-search-kbd');
    if (_kbd && /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent)) _kbd.textContent = '⌘ K';
}

var _navSearchBox = document.getElementById('nav-search');
if (_navSearchBox) _navSearchBox.addEventListener('click', function () {
    if (_sb && _sb.classList.contains('collapsed') && !_isPhone()) {
        setSidebarCollapsed(false);
        setTimeout(function () { if (_sbSearch) _sbSearch.focus(); }, 350);
    } else if (_sbSearch) {
        _sbSearch.focus();
    }
});

document.addEventListener('keydown', function (e) {
    if ((e.metaKey || e.ctrlKey) && !e.altKey && !e.shiftKey && (e.key || '').toLowerCase() === 'k' && _sbSearch) {
        e.preventDefault();
        var wait = _isPhone() ? !_sb.classList.contains('open') : _sb.classList.contains('collapsed');
        if (_isPhone()) openSidebar();
        else if (wait) setSidebarCollapsed(false);
        var go = function () { _sbSearch.focus(); _sbSearch.select(); };
        if (wait) setTimeout(go, 300); else go();
    }
});

// arrow keys walk the visible menu items
if (_sbNav) _sbNav.addEventListener('keydown', function (e) {
    if (['ArrowDown', 'ArrowUp', 'Home', 'End'].indexOf(e.key) < 0) return;
    var vis = _sbItems.filter(function (a) { return !a.hidden; });
    var i = vis.indexOf(document.activeElement);
    if (i < 0) return;
    e.preventDefault();
    if (e.key === 'ArrowUp' && i === 0 && _sbSearch) { _sbSearch.focus(); return; }
    var n = e.key === 'Home' ? 0 : e.key === 'End' ? vis.length - 1
        : (i + (e.key === 'ArrowDown' ? 1 : -1) + vis.length) % vis.length;
    vis[n].focus();
});

// ── tooltips of the collapsed menu: one floating label, so the scrolling
// list never clips it ──
var _tip = document.createElement('div');
_tip.className = 'sb-tip';
_tip.setAttribute('role', 'tooltip');
document.body.appendChild(_tip);

function _hideTip() { if (_tip) _tip.classList.remove('show'); }

function _showTip(el) {
    if (!_sb || !_sb.classList.contains('collapsed') || _isPhone()) return;
    var lbl = el.querySelector('.nav-label, .uname');
    var text = (lbl ? lbl.textContent : el.dataset.title || '').trim();
    if (!text) return;
    _tip.textContent = text;
    var r = el.getBoundingClientRect();
    _tip.style.top = (r.top + r.height / 2) + 'px';
    _tip.style.right = (window.innerWidth - _sb.getBoundingClientRect().left + 14) + 'px';
    _tip.classList.add('show');
}

if (_sb) {
    _sb.querySelectorAll('.nav-item, .user-pill').forEach(function (el) {
        el.addEventListener('mouseenter', function () { _showTip(el); });
        el.addEventListener('mouseleave', _hideTip);
        el.addEventListener('focus', function () { _showTip(el); });
        el.addEventListener('blur', _hideTip);
    });
    if (_sbNav) _sbNav.addEventListener('scroll', _hideTip, { passive: true });
    _sb.addEventListener('transitionend', function (e) {
        if (e.target === _sb && e.propertyName === 'width') _updateTrack();
    });
}

// keep the current page in sight in a long menu
(function () {
    var cur = _sbNav && _sbNav.querySelector('.nav-item.active');
    if (cur && cur.offsetTop + cur.offsetHeight > _sbNav.clientHeight)
        _sbNav.scrollTop = cur.offsetTop - _sbNav.clientHeight / 2 + cur.offsetHeight / 2;
}());

(function () {
    if (document.documentElement.classList.contains('sb-pre-collapsed')) {
        // the stored state, put on without its animation
        if (_sb) _sb.classList.add('no-anim');
        setSidebarCollapsed(true, false);
        document.documentElement.classList.remove('sb-pre-collapsed');
        if (_sb) requestAnimationFrame(function () { requestAnimationFrame(function () { _sb.classList.remove('no-anim'); }); });
    }
}());

// 🇮🇷 / 🇬🇧 written anywhere on the page (a bot button's name, a language in a
// sentence) show as the panel's own rounded flag pictures; a list's options
// cannot hold a picture, so there the flag is only left out. The text the
// bot sends is not touched - this is how the page draws it.
var _FLAG_RE = /\uD83C\uDDEE\uD83C\uDDF7|\uD83C\uDDEC\uD83C\uDDE7/g;
var _FLAG_OF = { '\uD83C\uDDEE\uD83C\uDDF7': 'fa', '\uD83C\uDDEC\uD83C\uDDE7': 'en' };
var _FLAG_SKIP = /^(SCRIPT|STYLE|TEXTAREA|INPUT|SELECT|OPTION|CODE|PRE|NOSCRIPT|TITLE)$/;

function _flagText(node) {
    var text = node.nodeValue, frag = document.createDocumentFragment(), last = 0, m;
    _FLAG_RE.lastIndex = 0;
    while ((m = _FLAG_RE.exec(text))) {
        if (m.index > last) frag.appendChild(document.createTextNode(text.slice(last, m.index)));
        var img = document.createElement('img');
        img.className = 'flag';
        img.src = 'img/flags/' + _FLAG_OF[m[0]] + '.svg';
        img.alt = m[0];
        img.width = 21; img.height = 14;
        frag.appendChild(img);
        last = m.index + m[0].length;
    }
    if (last < text.length) frag.appendChild(document.createTextNode(text.slice(last)));
    node.parentNode.replaceChild(frag, node);
}

function _flagify(root) {
    if (!root) return;
    if (root.nodeType === 3) { root = root.parentNode; if (!root) return; }
    if (root.nodeType !== 1 && root.nodeType !== 9) return;
    var opts = root.nodeName === 'OPTION' ? [root] : (root.querySelectorAll ? root.querySelectorAll('option') : []);
    Array.prototype.forEach.call(opts, function (o) {
        _FLAG_RE.lastIndex = 0;
        if (_FLAG_RE.test(o.textContent)) o.textContent = o.textContent.replace(_FLAG_RE, '').replace(/^\s+/, '');
        _FLAG_RE.lastIndex = 0;
    });
    var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
        acceptNode: function (n) {
            var p = n.parentNode;
            if (!p || _FLAG_SKIP.test(p.nodeName) || p.isContentEditable) return NodeFilter.FILTER_REJECT;
            _FLAG_RE.lastIndex = 0;
            return _FLAG_RE.test(n.nodeValue) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
        }
    });
    var found = [], n;
    while ((n = walker.nextNode())) found.push(n);
    found.forEach(_flagText);
}

_flagify(document.body);
if (window.MutationObserver) new MutationObserver(function (list) {
    list.forEach(function (m) { Array.prototype.forEach.call(m.addedNodes, _flagify); });
}).observe(document.body, { childList: true, subtree: true });

// A table of five columns or more is a card per row on a phone (css/shab.css):
// each cell named after its column, the row's own name as the card's title
// (a cell marked data-title, else its first .cs cell, else the first cell that
// is not the row number), and a cell with a text to type, or a row of boxes,
// the card's full width. Four columns or fewer stay a table: they fit.
document.querySelectorAll('.tbl-wrap > table:not(.no-stack)').forEach(function (t) {
    var heads = Array.prototype.map.call(t.querySelectorAll('thead th'), function (th) { return th.textContent.trim(); });
    if (!t.classList.contains('tbl-xl') && heads.length < 5) return;
    t.parentElement.classList.add('stk-wrap');
    t.querySelectorAll('tbody tr').forEach(function (tr) {
        var cells = Array.prototype.slice.call(tr.children);
        var title = tr.querySelector('td[data-title], td.stk-title, td.cs');
        if (!title && cells.length > 1) title = cells.filter(function (td, i) { return heads[i] !== '#' && td.getAttribute('colspan') === null; })[0];
        if (title) title.classList.add('stk-title');
        var col = 0;
        cells.forEach(function (td) {
            var span = parseInt(td.getAttribute('colspan') || '1', 10);
            if (span === 1 && heads[col]) td.setAttribute('data-label', heads[col]);
            if (span > 1 || td.querySelector('textarea, .lang-chips, input.input:not([style*="width"])')) td.classList.add('stk-wide');
            // a field with nothing in it (a dash) is left out of the card
            if (span === 1 && td !== title && /^[\s—–-]*$/.test(td.textContent) && !td.querySelector('a, button, input, select, textarea, img, svg')) td.classList.add('stk-empty');
            col += span;
        });
    });
});

// the first placement jumps straight to the current page; from then on it slides
if (_sbTrack) {
    var _prevY = null;
    try { _prevY = parseFloat(sessionStorage.getItem('panel-nav-y')); } catch (e) { }
    _sbTrack.style.transition = 'none';
    _updateTrack();
    var _curY = _sbTrack.style.transform;
    // coming from another page: start where the glow was there, then slide
    if (!isNaN(_prevY) && _sbTrack.style.opacity === '1' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        _sbTrack.style.transform = 'translateY(' + _prevY + 'px)';
    }
    void _sbTrack.offsetHeight;
    _sbTrack.style.transition = '';
    _sbTrack.style.transform = _curY;
    window.addEventListener('pagehide', function () {
        var cur = _sbItems.filter(function (a) { return a.classList.contains('active') && !a.hidden; })[0];
        try { if (cur) sessionStorage.setItem('panel-nav-y', String(cur.offsetTop)); } catch (e) { }
    });
}
// a tap slides the glow onto the new page while it loads
_sbItems.forEach(function (a) {
    a.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        _sbItems.forEach(function (b) { b.classList.toggle('active', b === a); });
        _updateTrack();
    });
});
if (_sbNav && window.ResizeObserver) new ResizeObserver(_updateTrack).observe(_sbNav);
window.addEventListener('resize', _updateTrack);
if (document.fonts && document.fonts.ready) document.fonts.ready.then(_updateTrack);

var _backdrop = document.getElementById('backdrop');
if (_backdrop) _backdrop.addEventListener('click', closeSidebar);

var _swipeSb = document.getElementById('sidebar');
if (_swipeSb) {
    var _swipeX = 0;
    _swipeSb.addEventListener('touchstart', function (e) { _swipeX = e.touches[0].clientX; }, { passive: true });
    _swipeSb.addEventListener('touchmove',  function (e) { if (e.touches[0].clientX - _swipeX > 40) closeSidebar(); }, { passive: true });
}

window.openModal = function (id) {
    var m = document.getElementById(id);
    if (m) m.classList.add('open');
};
window.closeModal = function (id) {
    var m = document.getElementById(id);
    if (m) m.classList.remove('open');
};

document.querySelectorAll('.modal-veil').forEach(function (v) {
    v.addEventListener('click', function (e) { if (e.target === v) v.classList.remove('open'); });
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-veil.open').forEach(function (m) { m.classList.remove('open'); });
        closeSidebar();
        closeConfirm();
    }
});

document.querySelectorAll('.search-box').forEach(function (box) {
    var inp = box.querySelector('input');
    var btn = box.querySelector('.search-clear');
    if (!inp || !btn) return;
    function update() { btn.style.display = inp.value ? 'grid' : 'none'; }
    inp.addEventListener('input', update);
    update();
    btn.addEventListener('click', function () {
        inp.value = '';
        inp.focus();
        update();
        inp.dispatchEvent(new Event('input'));
    });
});

document.querySelectorAll('[data-filter]').forEach(function (inp) {
    var tbl = document.getElementById(inp.dataset.filter);
    if (!tbl) return;
    inp.addEventListener('input', function () {
        var q = inp.value.trim().toLowerCase();
        tbl.querySelectorAll('tbody tr').forEach(function (tr) {
            if (tr.querySelector('.empty')) return;
            tr.style.display = !q || tr.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
});

setTimeout(function () {
    document.querySelectorAll('.notice').forEach(function (n) {
        n.style.transition = 'opacity .4s,transform .4s';
        n.style.opacity    = '0';
        n.style.transform  = 'translateY(-4px)';
        setTimeout(function () { n.remove(); }, 420);
    });
}, 5500);
