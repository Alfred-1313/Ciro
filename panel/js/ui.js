/* The panel's small motions (css/ui.css draws them): the dashboard's figures
   count up the first time it opens in a session, the button that saved turns
   into a tick for a moment, an empty list gets a quiet drawing, and a colour
   picker shows the colour it holds. Someone who asked their system for less
   motion gets the end state straight away. */
(function () {
    'use strict';
    var still = !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches);

    // ── the dashboard's figures count up from zero, once a session
    function countUp() {
        var nums = document.querySelectorAll('.stats .stat-num');
        if (!nums.length || still) return;
        var key = 'panelCounted:' + location.pathname;
        try {
            if (sessionStorage.getItem(key)) return;
            sessionStorage.setItem(key, '1');
        } catch (e) { return; }
        var jobs = [];
        nums.forEach(function (el) {
            var w = document.createTreeWalker(el, NodeFilter.SHOW_TEXT), n;
            while ((n = w.nextNode())) {
                var text = n.nodeValue, re = /\d[\d,]*(?:\.\d+)?/g, m, parts = [], last = 0;
                while ((m = re.exec(text))) {
                    var raw = m[0], val = parseFloat(raw.replace(/,/g, ''));
                    if (!isFinite(val)) continue;
                    parts.push(text.slice(last, m.index));
                    parts.push({ v: val, dec: (raw.split('.')[1] || '').length, grp: raw.indexOf(',') !== -1 });
                    last = m.index + raw.length;
                }
                if (!parts.length) continue;
                parts.push(text.slice(last));
                jobs.push({ node: n, parts: parts, text: text });
            }
        });
        if (!jobs.length) return;
        var fmt = function (v, p) {
            return v.toLocaleString('en-US', { minimumFractionDigits: p.dec, maximumFractionDigits: p.dec, useGrouping: p.grp });
        };
        var t0 = null, dur = 700;
        // start from zero right away, so the real figure does not show for a frame first
        jobs.forEach(function (j) {
            j.node.nodeValue = j.parts.map(function (p) { return typeof p === 'string' ? p : fmt(0, p); }).join('');
        });
        function frame(ts) {
            if (t0 === null) t0 = ts;
            var k = Math.min(1, (ts - t0) / dur), e = 1 - Math.pow(1 - k, 3);
            jobs.forEach(function (j) {
                j.node.nodeValue = k === 1 ? j.text : j.parts.map(function (p) {
                    return typeof p === 'string' ? p : fmt(p.v * e, p);
                }).join('');
            });
            if (k < 1) requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);
    }

    // ── an empty list: a drawing above the words (unless it has one of its own)
    var INBOX = '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>';
    function emptyArt(root) {
        (root || document).querySelectorAll('.empty').forEach(function (el) {
            if (el.querySelector('svg.ill, .empty-art')) return;
            el.insertAdjacentHTML('afterbegin',
                '<svg class="empty-art" viewBox="0 0 104 104" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
                '<circle cx="52" cy="52" r="46" stroke-opacity=".18" stroke-dasharray="3 6"/>' +
                '<g class="ea-orbit"><circle cx="52" cy="6" r="3" fill="currentColor" stroke="none" fill-opacity=".55"/><circle cx="98" cy="52" r="2" fill="currentColor" stroke="none" fill-opacity=".3"/></g>' +
                '<circle cx="52" cy="52" r="30" fill="currentColor" fill-opacity=".08" stroke="none"/>' +
                '<g class="ea-glyph" transform="translate(34 34) scale(1.5)" stroke-width="1.1">' + INBOX.replace(/\/>/g, ' pathLength="1"/>') + '</g></svg>');
        });
    }

    // ── a colour picker (default / blue / green / red) shows the colour it holds
    var COLOURS = { '': 1, primary: 1, success: 1, danger: 1 };
    function swatches(root) {
        (root || document).querySelectorAll('select').forEach(function (sel) {
            if (sel.getAttribute('data-sw')) return;
            var vals = Array.prototype.map.call(sel.options, function (o) { return o.value; });
            var coloured = vals.filter(function (v) { return v !== ''; });
            if (coloured.length < 2 || !vals.every(function (v) { return Object.prototype.hasOwnProperty.call(COLOURS, v); })) return;
            sel.setAttribute('data-sw', '1');
            // the dot sits inside the select, where its text starts
            var wide = getComputedStyle(sel).display === 'block' || sel.offsetWidth >= sel.parentNode.clientWidth - 2;
            var wrap = document.createElement('span');
            wrap.className = 'sw-wrap' + (wide ? ' sw-wide' : '');
            var dot = document.createElement('span');
            dot.className = 'sw-dot';
            dot.setAttribute('aria-hidden', 'true');
            var sync = function () { dot.setAttribute('data-c', sel.value); };
            sel.parentNode.insertBefore(wrap, sel);
            wrap.appendChild(sel);
            wrap.appendChild(dot);
            sel.classList.add('sw-sel');
            sync();
            sel.addEventListener('change', sync);
        });
    }

    // ── saved in place (ajaxform.js): the button that did it becomes a tick for a moment
    var TICK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path pathLength="1" d="M20 6 9 17l-5-5"/></svg>';
    document.addEventListener('ajaxform:saved', function (e) {
        var f = e.detail && e.detail.form;
        if (!f) return;
        swatches(f);
        emptyArt(f);
        var b = f.querySelector('button[type=submit].btn-primary') || f.querySelector('button[type=submit]');
        if (!b || b.classList.contains('is-saved') || b.offsetParent === null) return;
        var mark = document.createElement('span');
        mark.className = 'btn-done';
        mark.setAttribute('aria-hidden', 'true');
        mark.innerHTML = TICK;
        b.appendChild(mark);
        b.classList.add('is-saved');
        setTimeout(function () { b.classList.remove('is-saved'); mark.remove(); }, still ? 700 : 1100);
    });

    swatches();
    emptyArt();
    countUp();
})();
