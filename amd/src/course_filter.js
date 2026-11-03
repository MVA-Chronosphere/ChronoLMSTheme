// =============================================================================
// ChronoLMS Course Filter AMD Module
// Filters the My Courses grid by grade and status.
// =============================================================================
define("theme_chronolms/course_filter", ['jquery'], function($) {
    'use strict';

    function applyFilters() {
        var gradeFilter  = document.getElementById('filter-grade')  ? document.getElementById('filter-grade').value  : '';
        var statusFilter = document.getElementById('filter-status') ? document.getElementById('filter-status').value : '';

        document.querySelectorAll('.course-card').forEach(function(card) {
            var cardGrade  = card.getAttribute('data-grade')  || '';
            var cardStatus = card.getAttribute('data-status') || '';

            var gradeMatch  = !gradeFilter  || cardGrade.includes(gradeFilter);
            var statusMatch = !statusFilter || cardStatus === statusFilter;

            card.style.display = (gradeMatch && statusMatch) ? '' : 'none';
        });
    }

    return {
        init: function() {
            var gradeEl  = document.getElementById('filter-grade');
            var statusEl = document.getElementById('filter-status');

            if (gradeEl)  gradeEl.addEventListener('change', applyFilters);
            if (statusEl) statusEl.addEventListener('change', applyFilters);
        }
    };
});
