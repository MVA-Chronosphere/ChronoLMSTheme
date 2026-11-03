// =============================================================================
// ChronoLMS Dashboard AMD Module
// Animates the progress ring and loads badge/completion data via AJAX.
// =============================================================================
define("theme_chronolms/dashboard", ['jquery', 'core/ajax', 'core/str'], function($, Ajax, Str) {
    'use strict';

    // Animate SVG progress ring
    function animateRing(pct) {
        const fill  = document.getElementById('progress-ring-fill');
        const label = document.getElementById('ring-pct-label');
        if (!fill || !label) return;

        const circumference = 2 * Math.PI * 37; // r=37
        const offset        = circumference - (pct / 100) * circumference;

        fill.style.strokeDasharray  = circumference;
        fill.style.strokeDashoffset = circumference;
        fill.setAttribute('data-pct', pct);

        setTimeout(function() {
            fill.style.transition = 'stroke-dashoffset 1.4s cubic-bezier(.4,0,.2,1)';
            fill.style.strokeDashoffset = offset;
            label.textContent = pct + '%';
        }, 300);
    }

    // Load course completion stats via Moodle's AJAX
    function loadCompletionStats() {
        Ajax.call([{
            methodname: 'core_course_get_enrolled_courses_by_timeline_classification',
            args: { classification: 'all', limit: 100, offset: 0, sort: 'ul.timeaccess desc', customfieldname: '', customfieldvalue: '' }
        }])[0].then(function(response) {
            var courses     = response.courses || [];
            var inProgress  = 0;
            var completed   = 0;
            var totalPct    = 0;

            courses.forEach(function(c) {
                var pct = c.progress || 0;
                totalPct += pct;
                if (pct === 100)     completed++;
                else if (pct > 0)    inProgress++;
            });

            var avgPct = courses.length > 0 ? Math.round(totalPct / courses.length) : 0;

            animateRing(avgPct);

            var el = function(id, val) {
                var node = document.getElementById(id);
                if (node) node.textContent = val;
            };
            el('courses-inprogress-count', inProgress);
            el('courses-completed-count',  completed);
            el('courses-total-count',      courses.length);

        }).catch(function() {
            animateRing(0);
        });
    }

    // Load badges
    function loadBadges() {
        var shelf = document.getElementById('dashboard-badge-shelf');
        if (!shelf) return;

        Ajax.call([{
            methodname: 'core_badges_get_user_badges',
            args: { userid: 0, courseid: 0, page: 0, perpage: 5, search: '', onlypublic: false }
        }])[0].then(function(response) {
            var badges = response.badges || [];
            if (badges.length === 0) {
                return;
            }

            shelf.innerHTML = '';
            badges.forEach(function(badge) {
                var div = document.createElement('a');
                div.className   = 'badge-item';
                div.href        = '/badges/badge.php?hash=' + badge.uniquehash;
                div.title       = badge.name;
                div.innerHTML   = '<div class="badge-icon-wrap"><img src="' + badge.imageurl + '" alt="' + badge.name + '" style="width:36px;height:36px;border-radius:50%;"></div>'
                                + '<span class="badge-name">' + badge.name + '</span>';
                shelf.appendChild(div);
            });
        }).catch(function() {
            // Keep default empty state box
        });
    }

    return {
        init: function() {
            loadCompletionStats();
            loadBadges();
        }
    };
});
