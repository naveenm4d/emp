// Scatters twinkling stars across the sky behind the card.
(function () {
    var root = window.empInvitation && window.empInvitation.root;
    var sky = root && root.querySelector('[data-sky]');

    if (!sky) {
        return;
    }

    for (var i = 0; i < 70; i++) {
        var star = document.createElement('span');
        star.className = 'star';
        star.style.left = Math.random() * 100 + '%';
        star.style.top = Math.random() * 100 + '%';
        star.style.setProperty('--speed', 2 + Math.random() * 3 + 's');
        star.style.animationDelay = -Math.random() * 3 + 's';
        sky.appendChild(star);
    }
})();
