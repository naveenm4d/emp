// Show / hide the event details panel.
(function () {
    var root = window.empInvitation && window.empInvitation.root;
    var button = root && root.querySelector('[data-toggle]');
    var panel = root && root.querySelector('[data-panel]');

    if (!button || !panel) {
        return;
    }

    button.addEventListener('click', function () {
        var open = panel.hidden;
        panel.hidden = !open;
        button.setAttribute('aria-expanded', String(open));
        button.textContent = open ? 'Hide event details' : 'Show event details';
    });
})();
