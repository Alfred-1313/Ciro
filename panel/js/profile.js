window.switchTab = function (name) {
    var panes = { orders: 'paneOrders', pay: 'panePay', refs: 'paneRefs' };
    var tabs = { orders: 'tabOrders', pay: 'tabPay', refs: 'tabRefs' };

    Object.values(panes).forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });

    Object.values(tabs).forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            el.style.background = 'transparent';
            el.style.color = 'var(--mute)';
            el.style.border = 'none';
        }
    });

    var pane = document.getElementById(panes[name]);
    if (pane) {
        pane.style.display = 'block';
        // the pane arrives with the page's own small rise (not under reduced motion)
        pane.style.animation = 'none';
        void pane.offsetHeight;
        pane.style.animation = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? '' : 'rise 240ms var(--ease-out)';
    }

    var tab = document.getElementById(tabs[name]);
    if (tab) {
        tab.style.background = 'var(--sf3)';
        tab.style.color = 'var(--text)';
    }
};
