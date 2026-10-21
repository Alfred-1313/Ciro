// runs in <head> before the page paints: the stored theme, its background
// and the collapsed sidebar are on the page from the first frame
(function () {
    var bg = {
        shab: '#0f0f13', navy: '#0F172A', purple: '#180D2E', emerald: '#0A1F1C',
        sunset: '#1A0D0D', slate: '#080808', light: '#F1F5F9',
        linen: '#FAF7F2', mint: '#F0FDF4', lavender: '#FAF5FF'
    };
    var light = { light: 1, linen: 1, mint: 1, lavender: 1 };
    var store = {
        get: function (k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } },
        set: function (k, v) { try { window.localStorage.setItem(k, v); } catch (e) { } }
    };
    // «شب» became the default look: a theme picked before it existed is
    // moved onto it once, after that the admin's own pick stays
    if (store.get('panel-theme-v') !== '2') {
        store.set('panel-theme', 'shab');
        store.set('panel-theme-v', '2');
    }
    var t = store.get('panel-theme');
    if (!bg[t]) t = 'shab';

    var root = document.documentElement;
    root.style.backgroundColor = bg[t];
    root.setAttribute('data-theme', t);
    root.style.colorScheme = light[t] ? 'light' : 'dark';
    var mtc = document.getElementById('mtc');
    if (mtc) mtc.content = bg[t];
    if (store.get('panel-sb-collapsed') === '1' && window.innerWidth > 768)
        root.classList.add('sb-pre-collapsed');

    window.PANEL_THEME_BG = bg;
    window.PANEL_LIGHT_THEMES = light;
    window.panelStore = store;
}());
