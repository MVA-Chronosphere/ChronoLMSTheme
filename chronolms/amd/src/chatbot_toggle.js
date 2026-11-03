// =============================================================================
// ChronoLMS Chatbot Toggle AMD Module
// Manages the floating chatbot button and panel.
// =============================================================================
define("theme_chronolms/chatbot_toggle", ['jquery'], function($) {
    'use strict';

    var toggleBtn   = null;
    var panel       = null;
    var closeBtn    = null;
    var isOpen      = false;

    function openPanel() {
        isOpen = true;
        panel.classList.add('open');
        panel.setAttribute('aria-hidden', 'false');
        toggleBtn.classList.add('open');
        toggleBtn.setAttribute('aria-expanded', 'true');

        // Lazy-load the chat block into the panel on first open
        var inner = document.getElementById('chronolms-chatbot-inner');
        if (inner && inner.children.length === 0) {
            var chatBlock = document.querySelector('.block_openai_chat, .block_chronexa');
            if (chatBlock) {
                chatBlock.style.display = 'flex';
                inner.appendChild(chatBlock);
            }
        }
        document.dispatchEvent(new CustomEvent('chronolms:chatbot:open'));
    }

    function closePanel() {
        isOpen = false;
        panel.classList.remove('open');
        panel.setAttribute('aria-hidden', 'true');
        toggleBtn.classList.remove('open');
        toggleBtn.setAttribute('aria-expanded', 'false');
    }

    return {
        init: function() {
            toggleBtn = document.getElementById('chronolms-chatbot-toggle');
            panel     = document.getElementById('chronolms-chatbot-panel');
            closeBtn  = document.getElementById('chatbot-close-btn');

            if (!toggleBtn || !panel) return;

            toggleBtn.addEventListener('click', function() {
                isOpen ? closePanel() : openPanel();
            });

            if (closeBtn) {
                closeBtn.addEventListener('click', closePanel);
            }

            // Close on ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && isOpen) closePanel();
            });

            // Wire up "Help" nav item to open chatbot
            var helpItem = document.querySelector('[data-navid="help"] a');
            if (helpItem) {
                helpItem.addEventListener('click', function(e) {
                    e.preventDefault();
                    isOpen ? closePanel() : openPanel();
                });
            }

            // Wire up any .chatbot-trigger button/link
            document.querySelectorAll('[data-chatbot-trigger]').forEach(function(el) {
                el.addEventListener('click', function(e) {
                    e.preventDefault();
                    openPanel();
                });
            });
        }
    };
});
