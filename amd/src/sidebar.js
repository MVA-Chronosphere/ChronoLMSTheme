// =============================================================================
// ChronoLMS Sidebar AMD Module
// Handles sidebar toggle, collapse state, mobile behaviour.
// =============================================================================
define("theme_chronolms/sidebar", ['jquery', 'core/str'], function($, Str) {
    'use strict';

    const SIDEBAR_KEY    = 'clms_sidebar_collapsed';
    const sidebar        = document.getElementById('chronolms-sidebar');
    const contentWrapper = document.getElementById('chronolms-content-wrapper');
    const toggleBtn      = document.getElementById('sidebar-toggle-btn');
    const overlay        = document.getElementById('sidebar-overlay');

    function isCollapsed() {
        return localStorage.getItem(SIDEBAR_KEY) === '1';
    }

    function setCollapsed(collapsed) {
        if (collapsed) {
            sidebar.classList.add('collapsed');
            contentWrapper && contentWrapper.classList.add('sidebar-collapsed');
            localStorage.setItem(SIDEBAR_KEY, '1');
            toggleBtn && toggleBtn.setAttribute('aria-expanded', 'false');
        } else {
            sidebar.classList.remove('collapsed');
            contentWrapper && contentWrapper.classList.remove('sidebar-collapsed');
            localStorage.removeItem(SIDEBAR_KEY);
            toggleBtn && toggleBtn.setAttribute('aria-expanded', 'true');
        }
    }

    function isMobile() { return window.innerWidth <= 640; }

    function initSidebar() {
        if (!sidebar) return;

        // Restore saved state on desktop
        if (!isMobile() && isCollapsed()) {
            setCollapsed(true);
        }

        // Toggle button click
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                if (isMobile()) {
                    sidebar.classList.toggle('open');
                    overlay && overlay.classList.toggle('visible');
                } else {
                    setCollapsed(!sidebar.classList.contains('collapsed'));
                }
            });
        }

        // Overlay click closes on mobile
        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('open');
                overlay.classList.remove('visible');
            });
        }

        // Scroll-reveal animation for .reveal elements
        const revealObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08 });

        document.querySelectorAll('.reveal').forEach(function(el) {
            revealObserver.observe(el);
        });
    }

    return {
        init: function() { initSidebar(); }
    };
});
