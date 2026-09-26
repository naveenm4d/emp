// A short confetti burst over the invitation when it opens.
(function () {
    var root = window.empInvitation && window.empInvitation.root;
    var canvas = root && root.querySelector('[data-confetti]');

    if (!canvas || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    var context = canvas.getContext('2d');
    var colors = ['#ff5a8a', '#ffe066', '#7bdff2', '#b388eb', '#5ce1a5'];
    var width = (canvas.width = canvas.offsetWidth);
    var height = (canvas.height = canvas.offsetHeight);
    var pieces = [];

    for (var i = 0; i < 160; i++) {
        pieces.push({
            x: Math.random() * width,
            y: -20 - Math.random() * height * 0.5,
            size: 6 + Math.random() * 6,
            speed: 2 + Math.random() * 3,
            drift: -1 + Math.random() * 2,
            spin: Math.random() * Math.PI,
            color: colors[i % colors.length],
        });
    }

    var started = performance.now();

    function frame(now) {
        context.clearRect(0, 0, width, height);

        pieces.forEach(function (p) {
            p.y += p.speed;
            p.x += p.drift;
            p.spin += 0.1;
            context.save();
            context.translate(p.x, p.y);
            context.rotate(p.spin);
            context.fillStyle = p.color;
            context.fillRect(-p.size / 2, -p.size / 4, p.size, p.size / 2);
            context.restore();
        });

        if (now - started < 4000 && canvas.isConnected) {
            requestAnimationFrame(frame);
        } else {
            context.clearRect(0, 0, width, height);
        }
    }

    requestAnimationFrame(frame);
})();
