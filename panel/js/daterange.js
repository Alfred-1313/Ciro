/* 🗓 The date filter of every list (inc/daterange.php draws the button; this opens
 * what hangs from it).
 *
 * A tap on a ready-made range, or on «آخرین N روز», goes straight to the list. A
 * range from one day to another is picked on a calendar - the Persian one or the
 * Gregorian one (the choice is remembered; the tables of the panel show
 * Gregorian dates, so every day carries both) - first day, then last day; either
 * may be left open. The address always carries Gregorian ISO days, so a link of
 * a range means the same wherever it is opened.
 */
(function () {
    'use strict';

    // ---- calendars: a day is «YYYY-MM-DD» (Gregorian) and a number of days (JDN) between the two worlds ----
    function div(a, b) { return ~~(a / b); }
    function mod(a, b) { return a - ~~(a / b) * b; }
    var BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
    function jalCal(jy) {
        var gy = jy + 621, leapJ = -14, jp = BREAKS[0], jm, jump = 0, i, n, leap, leapG, march;
        for (i = 1; i < BREAKS.length; i++) {
            jm = BREAKS[i]; jump = jm - jp;
            if (jy < jm) break;
            leapJ += div(jump, 33) * 8 + div(mod(jump, 33), 4);
            jp = jm;
        }
        n = jy - jp;
        leapJ += div(n, 33) * 8 + div(mod(n, 33) + 3, 4);
        if (mod(jump, 33) === 4 && jump - n === 4) leapJ += 1;
        leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
        march = 20 + leapJ - leapG;
        if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33;
        leap = mod(mod(n + 1, 33) - 1, 4);
        if (leap === -1) leap = 4;
        return { leap: leap, gy: gy, march: march };
    }
    function g2d(gy, gm, gd) {
        var d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4) + div(153 * mod(gm + 9, 12) + 2, 5) + gd - 34840408;
        return d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;
    }
    function d2g(jdn) {
        var j = 4 * jdn + 139361631;
        j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
        var i = div(mod(j, 1461), 4) * 5 + 308;
        var gd = div(mod(i, 153), 5) + 1, gm = mod(div(i, 153), 12) + 1, gy = div(j, 1461) - 100100 + div(8 - gm, 6);
        return { y: gy, m: gm, d: gd };
    }
    function j2d(jy, jm, jd) {
        var r = jalCal(jy);
        return g2d(r.gy, 3, r.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1;
    }
    function d2j(jdn) {
        var gy = d2g(jdn).y, jy = gy - 621, r = jalCal(jy), k = jdn - g2d(gy, 3, r.march), jm, jd;
        if (k >= 0) {
            if (k <= 185) return { y: jy, m: 1 + div(k, 31), d: mod(k, 31) + 1 };
            k -= 186;
        } else {
            jy -= 1; k += 179;
            if (r.leap === 1) k += 1;
        }
        jm = 7 + div(k, 30); jd = mod(k, 30) + 1;
        return { y: jy, m: jm, d: jd };
    }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function iso2d(iso) { return g2d(+iso.slice(0, 4), +iso.slice(5, 7), +iso.slice(8, 10)); }
    function d2iso(jdn) { var g = d2g(jdn); return g.y + '-' + pad(g.m) + '-' + pad(g.d); }
    // Saturday first: the column of a day
    function col(jdn) { return mod(jdn + 2, 7); }
    function monthLen(cal, y, m) {
        if (cal === 'g') return g2d(m === 12 ? y + 1 : y, m === 12 ? 1 : m + 1, 1) - g2d(y, m, 1);
        return j2d(m === 12 ? y + 1 : y, m === 12 ? 1 : m + 1, 1) - j2d(y, m, 1);
    }
    function ymd(cal, jdn) { var o = cal === 'g' ? d2g(jdn) : d2j(jdn); return o; }
    function digits(s, cal) {
        if (cal !== 'j') return String(s);
        return String(s).replace(/\d/g, function (c) { return '۰۱۲۳۴۵۶۷۸۹'.charAt(+c); });
    }
    function show(iso, cal) {
        if (!iso) return '';
        var o = ymd(cal, iso2d(iso));
        return digits(o.y + '/' + pad(o.m) + '/' + pad(o.d), cal);
    }

    function T(key, fallback) { return (window.t && window.PANEL_I18N && window.PANEL_I18N[key]) ? window.t(key) : fallback; }
    function list(key, fallback) { return T(key, fallback).split(','); }
    function load(k, d) { try { return localStorage.getItem(k) || d; } catch (e) { return d; } }
    function save(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }

    var CHEV = {
        prev: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>',
        next: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>'
    };

    function init(root) {
        var form = root.closest('form');
        var btn = root.querySelector('[data-dr-open]'), pop = root.querySelector('.dr-pop');
        // the popover lives on the body: a card that is mid-animation (a transform) would
        // otherwise be what its fixed position is measured from, and a table wrap would clip it
        document.body.appendChild(pop);
        var calBox = pop.querySelector('[data-dr-cal]');
        var applyBtn = pop.querySelector('[data-dr-range]'), numIn = pop.querySelector('[data-dr-n]');
        var fromBtn = pop.querySelector('[data-dr-field=from]'), toBtn = pop.querySelector('[data-dr-field=to]');
        var today = root.getAttribute('data-today');
        var cal = load('dr_cal', 'j') === 'g' ? 'g' : 'j';
        var st = {
            from: root.getAttribute('data-range') === 'custom' ? root.getAttribute('data-from') : '',
            to: root.getAttribute('data-range') === 'custom' ? root.getAttribute('data-to') : '',
            pick: 'from', hover: '', view: null, focus: ''
        };
        var veil = null;

        // ---- the button's own words, in the calendar chosen ----
        function paintText() {
            if (root.getAttribute('data-range') !== 'custom') return;
            var f = root.getAttribute('data-from'), t = root.getAttribute('data-to'), s;
            if (f && t) s = show(f, cal) + ' → ' + show(t, cal);
            else if (f) s = T('jsDrSince', 'from %s').replace('%s', show(f, cal));
            else s = T('jsDrUntil', 'until %s').replace('%s', show(t, cal));
            root.querySelector('[data-dr-text]').textContent = s;
        }
        paintText();

        // what a ready-made range means in days, in the calendar chosen
        var spanEl = pop.querySelector('[data-dr-span]');
        function paintSpan() {
            var k = root.getAttribute('data-range'), f = root.getAttribute('data-from'), t = root.getAttribute('data-to');
            spanEl.textContent = k && k !== 'custom' && f ? show(f, cal) + ' → ' + show(t || today, cal) : '';
        }

        // ---- submit: the page's own form, with the range in it ----
        function go(p) {
            if (!form) return;
            form.querySelectorAll('input[data-dr-h]').forEach(function (i) { i.remove(); });
            Object.keys(p).forEach(function (k) {
                if (p[k] === '' || p[k] == null) return;
                var i = document.createElement('input');
                i.type = 'hidden'; i.name = k; i.value = p[k]; i.setAttribute('data-dr-h', '');
                form.appendChild(i);
            });
            // a new filter starts at the first page
            form.querySelectorAll('input[name=page]').forEach(function (i) { i.remove(); });
            if (window._lb) window._lb.start();
            if (form.requestSubmit) form.requestSubmit(); else form.submit();
        }
        function clearAll() { go({}); }

        // ---- the popover: where it sits, how it opens and closes ----
        function place() {
            var r = btn.getBoundingClientRect(), vw = document.documentElement.clientWidth, sheet = vw < 560;
            pop.classList.toggle('dr-sheet', sheet);
            if (sheet) {
                pop.style.left = pop.style.top = pop.style.transformOrigin = '';
                return;
            }
            var vh = window.innerHeight, w = pop.offsetWidth, h = Math.min(pop.scrollHeight + 2, vh - 24), left = r.right - w;
            // the end edge under the button, kept inside the screen
            if (document.documentElement.dir !== 'rtl') left = r.left;
            left = Math.max(12, Math.min(left, vw - w - 12));
            var top = r.bottom + 8;
            if (top + h > vh - 12) top = r.top - h - 8 >= 12 ? r.top - h - 8 : Math.max(12, vh - 12 - h);
            pop.style.left = left + 'px';
            pop.style.top = top + 'px';
            pop.style.transformOrigin = Math.round(r.left + r.width / 2 - left) + 'px ' + (top >= r.bottom ? '0' : '100%');
        }
        function open() {
            if (root.hasAttribute('data-open')) return;
            document.querySelectorAll('[data-dr][data-open]').forEach(function (o) { if (o !== root && o._drClose) o._drClose(); });
            st.pick = st.from && !st.to ? 'to' : 'from';
            st.view = null; st.hover = '';
            paintSpan();
            render();
            root.setAttribute('data-open', '');
            btn.setAttribute('aria-expanded', 'true');
            pop.classList.add('dr-open');
            if (window.innerWidth < 560) {
                veil = document.createElement('div');
                veil.className = 'dr-veil';
                document.body.appendChild(veil);
                requestAnimationFrame(function () { veil.classList.add('on'); });
                veil.addEventListener('pointerdown', close);
            }
            place();
        }
        function close(refocus) {
            if (!root.hasAttribute('data-open')) return;
            root.removeAttribute('data-open');
            pop.classList.remove('dr-open');
            btn.setAttribute('aria-expanded', 'false');
            if (veil) { var v = veil; veil = null; v.classList.remove('on'); setTimeout(function () { v.remove(); }, 200); }
            if (refocus === true) btn.focus();
        }
        root._drClose = close;
        btn.addEventListener('click', function (e) {
            if (root.hasAttribute('data-open')) { close(); return; }
            open();
            // opened from the keyboard (Enter / Space: a click with no pointer): the way in is the popover itself
            if (e.detail === 0) { var f = pop.querySelector('.dr-chip'); if (f) f.focus(); }
        });
        // Tab stays inside while it is open
        pop.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab') return;
            var items = Array.prototype.filter.call(pop.querySelectorAll('button:not([disabled]),input,[tabindex="0"]'), function (x) { return x.offsetParent !== null && x.tabIndex >= 0; });
            if (!items.length) return;
            var first = items[0], last = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        });
        document.addEventListener('pointerdown', function (e) {
            if (root.hasAttribute('data-open') && !root.contains(e.target) && !pop.contains(e.target) && !(veil && e.target === veil)) close();
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && root.hasAttribute('data-open')) { e.stopPropagation(); close(true); } });
        window.addEventListener('resize', function () { if (root.hasAttribute('data-open')) place(); });
        window.addEventListener('scroll', function () { if (root.hasAttribute('data-open') && window.innerWidth >= 560) place(); }, true);

        // ---- ready-made ranges and «last N days» ----
        pop.querySelectorAll('[data-dr-pick]').forEach(function (b) {
            b.addEventListener('click', function () {
                var k = b.getAttribute('data-dr-pick'), n = b.getAttribute('data-n');
                go(k === 'days' ? { range: 'days', n: n } : { range: k });
            });
        });
        function applyDays() {
            var n = parseInt(numIn.value, 10);
            if (!(n >= 1)) { numIn.focus(); numIn.classList.add('bad'); return; }
            go({ range: 'days', n: Math.min(n, 3650) });
        }
        pop.querySelector('[data-dr-days]').addEventListener('click', applyDays);
        numIn.addEventListener('input', function () { numIn.classList.remove('bad'); });
        numIn.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); applyDays(); } });
        [root, pop].forEach(function (box) { box.querySelectorAll('[data-dr-clear]').forEach(function (b) { b.addEventListener('click', clearAll); }); });

        // ---- from / to on a calendar ----
        function viewOf(iso) { var o = ymd(cal, iso2d(iso || today)); return { y: o.y, m: o.m }; }
        function setPick(which) { st.pick = which; paintFields(); paintDays(); }
        fromBtn.addEventListener('click', function () { setPick('from'); });
        toBtn.addEventListener('click', function () { setPick('to'); });
        applyBtn.addEventListener('click', function () {
            if (!st.from && !st.to) return;
            go({ range: 'custom', from: st.from, to: st.to });
        });
        function choose(iso) {
            if (st.pick === 'from') {
                st.from = iso;
                if (st.to && st.to < iso) st.to = '';
                st.pick = 'to';
            } else {
                if (st.from && iso < st.from) { st.to = st.from; st.from = iso; } else st.to = iso;
                st.pick = 'from';
            }
            st.hover = '';
            paintFields(); paintDays();
        }
        function paintFields() {
            [['from', fromBtn], ['to', toBtn]].forEach(function (p) {
                var iso = st[p[0]], other = cal === 'j' ? 'g' : 'j';
                p[1].classList.toggle('on', st.pick === p[0]);
                p[1].querySelector('b').textContent = iso ? show(iso, cal) : '—';
                p[1].querySelector('i').textContent = iso ? show(iso, other) : '';
            });
            applyBtn.disabled = !(st.from || st.to);
        }
        function paintDays() {
            var lo = st.from, hi = st.to || (st.pick === 'to' && st.from && st.hover && st.hover >= st.from ? st.hover : '');
            calBox.querySelectorAll('.dr-d').forEach(function (c) {
                var iso = c.getAttribute('data-iso');
                var isFrom = iso === st.from, isTo = iso === st.to;
                c.classList.toggle('from', isFrom);
                c.classList.toggle('to', isTo);
                c.classList.toggle('in', !!lo && !!hi && iso > lo && iso < hi);
                c.classList.toggle('edge', !!lo && !!hi && lo !== hi && (iso === lo || iso === hi));
                c.setAttribute('aria-pressed', isFrom || isTo ? 'true' : 'false');
            });
        }
        function render() {
            if (!st.view) st.view = viewOf(st.from || st.to || today);
            var v = st.view, other = cal === 'j' ? 'g' : 'j';
            var months = list(cal === 'j' ? 'jsDrMonthsJ' : 'jsDrMonthsG', cal === 'j' ? 'Farvardin,Ordibehesht,Khordad,Tir,Mordad,Shahrivar,Mehr,Aban,Azar,Dey,Bahman,Esfand' : 'January,February,March,April,May,June,July,August,September,October,November,December');
            var week = list('jsDrWeek', 'S,S,M,T,W,T,F');
            var first = cal === 'g' ? g2d(v.y, v.m, 1) : j2d(v.y, v.m, 1), len = monthLen(cal, v.y, v.m);
            var html = '<div class="dr-hd">'
                + '<button type="button" class="dr-nav" data-nav="-1" aria-label="' + T('jsDrPrev', 'Previous month') + '">' + CHEV.prev + '</button>'
                + '<div class="dr-title" aria-live="polite">' + months[v.m - 1] + ' ' + digits(v.y, cal) + '</div>'
                + '<button type="button" class="dr-nav" data-nav="1" aria-label="' + T('jsDrNext', 'Next month') + '">' + CHEV.next + '</button>'
                + '</div>'
                + '<div class="dr-seg" role="group">'
                + '<button type="button" data-cal="j" aria-pressed="' + (cal === 'j') + '">' + T('jsDrJalali', 'Persian') + '</button>'
                + '<button type="button" data-cal="g" aria-pressed="' + (cal === 'g') + '">' + T('jsDrGregorian', 'Gregorian') + '</button></div>'
                + '<div class="dr-grid" role="grid">';
            week.forEach(function (w) { html += '<span class="dr-w" aria-hidden="true">' + w + '</span>'; });
            for (var i = 0; i < col(first); i++) html += '<span class="dr-e"></span>';
            var todayIso = today;
            for (var d = 1; d <= len; d++) {
                var jdn = first + d - 1, iso = d2iso(jdn), o2 = ymd(other, jdn);
                html += '<button type="button" class="dr-d' + (iso === todayIso ? ' today' : '') + '" data-iso="' + iso + '" title="' + show(iso, 'j') + ' · ' + show(iso, 'g') + '" tabindex="-1">'
                    + digits(d, cal) + '</button>';
            }
            html += '</div>';
            calBox.innerHTML = html;
            // one tab stop in the grid: the picked day, else today, else the first
            var stop = calBox.querySelector('.dr-d[data-iso="' + (st.focus || st.from || st.to || today) + '"]') || calBox.querySelector('.dr-d');
            if (stop) stop.tabIndex = 0;
            paintFields(); paintDays();
        }
        function shift(delta) {
            var v = st.view, m = v.m + delta, y = v.y;
            while (m > 12) { m -= 12; y++; }
            while (m < 1) { m += 12; y--; }
            st.view = { y: y, m: m };
            render();
        }
        calBox.addEventListener('click', function (e) {
            var n = e.target.closest('[data-nav]'), c = e.target.closest('[data-cal]'), d = e.target.closest('.dr-d');
            if (n) shift(+n.getAttribute('data-nav'));
            else if (c) {
                cal = c.getAttribute('data-cal'); save('dr_cal', cal);
                st.view = viewOf(st.from || st.to || today);
                render(); paintText(); paintSpan();
            } else if (d) { st.focus = d.getAttribute('data-iso'); choose(st.focus); }
        });
        pop.querySelector('[data-dr-today]').addEventListener('click', function () { st.view = viewOf(today); st.focus = today; render(); });
        calBox.addEventListener('mouseover', function (e) {
            var d = e.target.closest('.dr-d');
            if (d && st.pick === 'to' && st.from) { st.hover = d.getAttribute('data-iso'); paintDays(); }
        });
        calBox.addEventListener('mouseleave', function () { if (st.hover) { st.hover = ''; paintDays(); } });
        // the keys: a day is a step in the way the page reads (a left arrow is later in a right-to-left page)
        calBox.addEventListener('keydown', function (e) {
            var d = e.target.closest('.dr-d');
            if (!d) return;
            var rtl = document.documentElement.dir === 'rtl', step = 0, iso = d.getAttribute('data-iso'), jdn = iso2d(iso);
            if (e.key === 'ArrowLeft') step = rtl ? 1 : -1;
            else if (e.key === 'ArrowRight') step = rtl ? -1 : 1;
            else if (e.key === 'ArrowUp') step = -7;
            else if (e.key === 'ArrowDown') step = 7;
            else if (e.key === 'Home') step = -col(jdn);
            else if (e.key === 'End') step = 6 - col(jdn);
            else if (e.key === 'PageUp' || e.key === 'PageDown') {
                e.preventDefault(); shift(e.key === 'PageUp' ? -1 : 1);
                var f = calBox.querySelector('.dr-d'); if (f) f.focus();
                return;
            } else return;
            e.preventDefault();
            var target = d2iso(jdn + step), o = ymd(cal, jdn + step);
            if (o.y !== st.view.y || o.m !== st.view.m) { st.view = { y: o.y, m: o.m }; st.focus = target; render(); }
            var el = calBox.querySelector('.dr-d[data-iso="' + target + '"]');
            if (el) { calBox.querySelectorAll('.dr-d').forEach(function (x) { x.tabIndex = -1; }); el.tabIndex = 0; el.focus(); }
        });
    }

    document.querySelectorAll('[data-dr]').forEach(init);
})();
