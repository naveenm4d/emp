// Live countdown to the event. The invitation lives in a shadow root:
// reach it through window.empInvitation.root, not document.
(function () {
    var root = window.empInvitation && window.empInvitation.root;
    var box = root && root.querySelector('[data-countdown]');

    if (!box) {
        return;
    }

    var months = ['january', 'february', 'march', 'april', 'may', 'june', 'july',
        'august', 'september', 'october', 'november', 'december'];
    // event.date looks like "Saturday, 12 October 2026"; event.start_time like "6:00 PM".
    var date = /(\d{1,2}) (\w+) (\d{4})/.exec(box.dataset.date || '');
    var time = /(\d{1,2}):(\d{2}) (AM|PM)/.exec(box.dataset.time || '');

    if (!date || months.indexOf(date[2].toLowerCase()) < 0) {
        return;
    }

    var hours = time ? (Number(time[1]) % 12) + (time[3] === 'PM' ? 12 : 0) : 0;
    var target = new Date(Number(date[3]), months.indexOf(date[2].toLowerCase()), Number(date[1]), hours, time ? Number(time[2]) : 0);

    function tick() {
        var left = Math.max(0, target.getTime() - Date.now());
        var units = {
            days: Math.floor(left / 86400000),
            hours: Math.floor(left / 3600000) % 24,
            minutes: Math.floor(left / 60000) % 60,
            seconds: Math.floor(left / 1000) % 60,
        };

        Object.keys(units).forEach(function (unit) {
            box.querySelector('[data-unit="' + unit + '"]').textContent = units[unit];
        });

        return left > 0;
    }

    box.hidden = false;
    tick();

    var timer = setInterval(function () {
        if (!box.isConnected || !tick()) {
            clearInterval(timer);
        }
    }, 1000);
})();
