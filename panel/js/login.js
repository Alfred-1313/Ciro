(function () {
    var shell = document.getElementById('shell');
    var frame = document.getElementById('frame');
    var edge = document.getElementById('frame-path');

    // the card's outline: two corners cut away (top left, bottom right),
    // drawn for the card's real size so the cuts never stretch
    function draw() {
        var w = shell.offsetWidth, h = shell.offsetHeight;
        var d = parseFloat(getComputedStyle(shell).getPropertyValue('--cut')) || 125;
        frame.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
        edge.setAttribute('d',
            'M' + (d + 26) + ' 2H' + (w - 26) + 'Q' + (w - 2) + ' 2 ' + (w - 2) + ' 27' +
            'V' + (h - d - 8) + 'Q' + (w - 2) + ' ' + (h - d + 3) + ' ' + (w - 10) + ' ' + (h - d + 11) +
            'L' + (w - d + 13) + ' ' + (h - 11) + 'Q' + (w - d + 5) + ' ' + (h - 2) + ' ' + (w - d - 9) + ' ' + (h - 2) +
            'H27Q2 ' + (h - 2) + ' 2 ' + (h - 27) +
            'V' + (d + 9) + 'Q2 ' + (d - 2) + ' 10 ' + (d - 10) +
            'L' + (d - 10) + ' 10Q' + (d - 2) + ' 2 ' + (d + 26) + ' 2Z');
    }
    draw();
    if (window.ResizeObserver) new ResizeObserver(draw).observe(shell);
    else window.addEventListener('resize', draw);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(draw);

    var eye = document.getElementById('eye');
    var pw = document.getElementById('password');
    if (eye && pw) eye.addEventListener('click', function () {
        var show = pw.type === 'password';
        pw.type = show ? 'text' : 'password';
        eye.setAttribute('aria-pressed', String(show));
        eye.setAttribute('aria-label', show ? eye.dataset.hide : eye.dataset.show);
        pw.focus();
    });

    var form = document.getElementById('login-form');
    var submit = document.getElementById('submit');
    if (form) form.addEventListener('submit', function () {
        shell.classList.add('sending');
        submit.disabled = true;
    });
    // the browser's back button brings the page back as it was left
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        shell.classList.remove('sending');
        if (submit) submit.disabled = false;
    });
}());
