// The phone's bottom bar: a bead on a spring slides to the page you pick, the
// bar's top edge dips under it and the icon beneath rises into it. Tap a tab
// or drag the bead; when it settles on another page, that page opens.
(function () {
    'use strict';
    var bar = document.querySelector('[data-toolbar]');
    if (!bar) return;
    var tabs = Array.prototype.slice.call(bar.querySelectorAll('.mtb-tab'));
    var bead = bar.querySelector('.mtb-bead');
    var surface = bar.querySelector('.mtb-surface');
    var path = bar.querySelector('.mtb-path');
    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    var DEPTH = 37;                           // how far the edge dips under the bead
    var current = tabs.findIndex(function (a) { return a.classList.contains('on'); });
    var selected = current;                   // -1: this page has no tab here
    var centers = [], width = 0, ink = '';
    var x = 0, target = 0, velocity = 0, dep = 0, depTarget = 0;
    var frame = 0, previous = 0, leaving = null;
    var pointer = null, dragged = false, pointerStart = 0, startX = 0, lastX = 0, lastT = 0;

    var lo = function () { return Math.min.apply(null, centers); };
    var hi = function () { return Math.max.apply(null, centers); };
    var clamp = function (v) { return Math.max(lo(), Math.min(hi(), v)); };
    var nearest = function (v) {
        return centers.reduce(function (best, c, i) { return Math.abs(c - v) < Math.abs(centers[best] - v) ? i : best; }, 0);
    };

    function render() {
        if (!width) return;
        // the dip is geometry, so the background shows through it wherever the bead is
        var c = Math.max(49, Math.min(width - 49, x)), y = 39 + dep;
        path.setAttribute('d', 'M22 39 H' + (c - 49) + ' C' + (c - 34) + ' 39 ' + (c - 38) + ' ' + y + ' ' + c + ' ' + y +
            ' C' + (c + 38) + ' ' + y + ' ' + (c + 34) + ' 39 ' + (c + 49) + ' 39 H' + (width - 22) +
            ' Q' + (width - 1) + ' 39 ' + (width - 1) + ' 61 V103 Q' + (width - 1) + ' 124 ' + (width - 22) + ' 124 H22 Q1 124 1 103 V61 Q1 39 22 39 Z');
        var stretch = reduced.matches ? 0 : Math.min(Math.abs(velocity) / 6500, 0.11);
        bead.style.transform = 'translate3d(' + (x - bead.offsetWidth / 2) + 'px,0,0) scale(' + (1 + stretch) + ',' + (1 - stretch * 0.6) + ')';
        bar.style.setProperty('--bead-x', x + 'px');
        var shown = dep / DEPTH;
        tabs.forEach(function (tab, i) {
            var p = Math.max(0, 1 - Math.abs(centers[i] - x) / 58);
            var lift = p * p * (3 - 2 * p) * shown;
            var svg = tab.querySelector('svg');
            svg.style.transform = 'translate3d(0,' + (-46 * lift) + 'px,0) scale(' + (1 + lift * 0.04) + ')';
            svg.style.color = lift > 0.65 ? ink : '';
        });
    }

    function settled() {
        return Math.abs(target - x) < 0.025 && Math.abs(velocity) < 0.025 && Math.abs(depTarget - dep) < 0.05;
    }

    function tick(time) {
        var dt = Math.min((time - previous) / 1000 || 1 / 60, 1 / 30);
        previous = time;
        if (reduced.matches) { x = target; velocity = 0; dep = depTarget; }
        else {
            // substeps: the same soft settle on a 60 Hz and a 120 Hz screen
            for (var i = 0; i < 4; i++) {
                velocity += ((target - x) * 370 - velocity * 29) * dt / 4;
                x += velocity * dt / 4;
            }
            dep += (depTarget - dep) * Math.min(1, dt * 14);
        }
        render();
        if (!settled()) frame = requestAnimationFrame(tick);
        else { x = target; velocity = 0; dep = depTarget; frame = 0; render(); }
    }

    function animate() {
        if (!frame) { previous = performance.now(); frame = requestAnimationFrame(tick); }
    }

    // which tab shows its name
    function present(index) {
        tabs.forEach(function (tab, i) { tab.classList.toggle('sel', i === index); });
        bar.classList.toggle('mtb-none', index < 0);
    }

    function select(index, focus) {
        if (selected < 0 && index >= 0) { x = centers[index]; velocity = 0; }   // appears in place, nothing to slide from
        selected = index;
        present(index);
        if (focus && tabs[index]) tabs[index].focus({ preventScroll: true });
        target = index >= 0 ? centers[index] : x;
        depTarget = index >= 0 ? DEPTH : 0;
        animate();
    }

    // open the picked page once the bead has had its moment
    function go(index) {
        if (index < 0 || index === current) return;
        clearTimeout(leaving);
        leaving = setTimeout(function () {
            if (window._lb) window._lb.start();
            window.location.href = tabs[index].href;
        }, reduced.matches ? 0 : 260);
    }

    function measure() {
        var r = bar.getBoundingClientRect();
        width = r.width;
        if (!width) return;                  // hidden (a wide screen)
        centers = tabs.map(function (t) { var b = t.getBoundingClientRect(); return b.left - r.left + b.width / 2; });
        surface.setAttribute('viewBox', '0 0 ' + width + ' 140');
        ink = getComputedStyle(bar).getPropertyValue('--mtb-ink').trim();
        x = target = selected >= 0 ? centers[selected] : width / 2;
        dep = depTarget = selected >= 0 ? DEPTH : 0;
        velocity = 0;
        present(selected);
        render();
    }

    bar.addEventListener('pointerdown', function (e) {
        if (!e.isPrimary || e.button !== 0 || !centers.length) return;
        if (e.clientY > bar.getBoundingClientRect().top + 126) return;
        pointer = e.pointerId;
        pointerStart = lastX = e.clientX;
        startX = selected >= 0 ? x : clamp(e.clientX - bar.getBoundingClientRect().left);
        lastT = performance.now();
        dragged = false;
    });

    bar.addEventListener('pointermove', function (e) {
        if (e.pointerId !== pointer) return;
        var delta = e.clientX - pointerStart;
        if (!dragged && Math.abs(delta) < 5) return;
        if (!dragged) {
            dragged = true;
            bar.setPointerCapture(pointer);
            bar.classList.add('is-dragging');
            if (selected < 0) { selected = nearest(startX); x = startX; }
            depTarget = DEPTH;
        }
        var now = performance.now();
        velocity = Math.max(-1600, Math.min(1600, (e.clientX - lastX) / Math.max(0.008, (now - lastT) / 1000)));
        lastX = e.clientX;
        lastT = now;
        target = x = clamp(startX + delta);
        if (dep < DEPTH) animate(); else { if (frame) cancelAnimationFrame(frame); frame = 0; render(); }
        present(nearest(x));                 // the name follows while scrubbing
    });

    function finish(e) {
        if (e.pointerId !== pointer) return;
        pointer = null;
        bar.classList.remove('is-dragging');
        if (bar.hasPointerCapture(e.pointerId)) bar.releasePointerCapture(e.pointerId);
        if (dragged) {
            // a short throw carries the release on into the nearest tab
            var momentum = performance.now() - lastT < 90 ? velocity * 0.045 : 0;
            var i = nearest(clamp(x + momentum));
            select(i);
            go(i);
        }
    }
    window.addEventListener('pointerup', finish);
    window.addEventListener('pointercancel', function (e) {
        if (e.pointerId === pointer) { velocity = 0; dragged = false; finish(e); select(current); }
    });

    // a link is draggable: the browser's own drag would take the pointer away
    bar.addEventListener('dragstart', function (e) { e.preventDefault(); });

    bar.addEventListener('click', function (e) {
        var tab = e.target.closest('.mtb-tab');
        if (dragged) { dragged = false; e.preventDefault(); return; }
        if (!tab) return;
        // a new tab or window: the browser's own business
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        e.preventDefault();
        var i = tabs.indexOf(tab);
        select(i);
        go(i);
    });

    // arrows move the bead along (the first tab is the rightmost), Enter opens
    bar.addEventListener('keydown', function (e) {
        var i = tabs.indexOf(document.activeElement), next;
        if (i < 0) return;
        if (e.key === 'ArrowRight') next = (i - 1 + tabs.length) % tabs.length;
        if (e.key === 'ArrowLeft') next = (i + 1) % tabs.length;
        if (e.key === 'Home') next = 0;
        if (e.key === 'End') next = tabs.length - 1;
        if (next !== undefined) { e.preventDefault(); select(next, true); }
    });
    bar.addEventListener('focusout', function (e) {
        // the focus left the bar without opening anything: back to this page's tab
        if (!bar.contains(e.relatedTarget) && !leaving) select(current);
    });

    // back to this page from the browser's history: the bead goes home again
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        clearTimeout(leaving);
        leaving = null;
        selected = current;
        measure();
    });

    if (reduced.addEventListener) reduced.addEventListener('change', function () { if (reduced.matches) { x = target; velocity = 0; render(); } });
    if (window.ResizeObserver) new ResizeObserver(measure).observe(bar);
    else window.addEventListener('resize', measure);
    measure();
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(measure);
}());
