// Makes the neon title flicker now and then, like a real sign.
(function () {
    var root = window.empInvitation && window.empInvitation.root;
    var sign = root && root.querySelector('[data-neon]');

    if (!sign || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    function flicker() {
        if (!sign.isConnected) {
            return;
        }

        var flashes = 2 + Math.floor(Math.random() * 3);

        (function step(i) {
            sign.classList.toggle('is-off', i % 2 === 0);

            if (i < flashes * 2) {
                setTimeout(function () { step(i + 1); }, 60 + Math.random() * 90);
            } else {
                sign.classList.remove('is-off');
                setTimeout(flicker, 2500 + Math.random() * 4000);
            }
        })(0);
    }

    setTimeout(flicker, 1200);
})();
