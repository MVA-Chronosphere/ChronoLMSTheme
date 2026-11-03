(function() {
    'use strict';

    var BOT_ICON = '<svg class="clms-icon clms-icon-bot" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>';
    var USER_ICON = '<svg class="clms-icon clms-icon-user" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';

    // Up to this width the sidebar is an off-canvas drawer opened from the menu button.
    var DRAWER_QUERY = '(max-width: 1024px)';
    // Collapsed state lives in a cookie: Moodle clears localStorage on every login and cache bump, and
    // the server reads the cookie to render the sidebar collapsed from the first paint.
    var COLLAPSE_COOKIE = 'clms_sidebar';
    function readCollapsed() {
        return document.cookie.split('; ').indexOf(COLLAPSE_COOKIE + '=collapsed') !== -1;
    }
    function writeCollapsed(collapsed) {
        document.cookie = COLLAPSE_COOKIE + '=' + (collapsed ? 'collapsed' : '') + '; path=/; max-age=' +
            (collapsed ? 31536000 : 0) + '; SameSite=Lax' + (window.location.protocol === 'https:' ? '; Secure' : '');
    }
    function isDrawer() {
        return window.matchMedia(DRAWER_QUERY).matches;
    }

    // Apply saved sidebar width immediately to eliminate layout shift
    try {
        var preSavedSidebar = localStorage.getItem('chronolms_sidebar_width');
        if (preSavedSidebar) {
            var sw = parseInt(preSavedSidebar, 10);
            if (sw >= 180 && sw <= 600) {
                document.documentElement.style.setProperty('--chronolms-sidebar-width', sw + 'px');
            }
        }
    } catch (e) {}

    function initSidebarResizer() {
        var sidebar = document.getElementById('chronolms-sidebar');
        var resizer = document.getElementById('sidebar-resizer');
        if (!sidebar || !resizer) {
            return;
        }

        var savedWidth = localStorage.getItem('chronolms_sidebar_width');
        if (savedWidth) {
            var wInt = parseInt(savedWidth, 10);
            if (wInt >= 180 && wInt <= 600) {
                document.documentElement.style.setProperty('--chronolms-sidebar-width', wInt + 'px');
            }
        }

        var isDragging = false;
        var startX = 0;
        var startWidth = 0;

        var onPointerDown = function(e) {
            if (isDrawer()) {
                return;
            }
            isDragging = true;
            startX = e.clientX || (e.touches && e.touches[0].clientX) || 0;
            startWidth = sidebar.getBoundingClientRect().width;

            document.body.classList.add('is-resizing-sidebar');
            resizer.classList.add('is-resizing');

            document.addEventListener('mousemove', onPointerMove);
            document.addEventListener('mouseup', onPointerUp);
            document.addEventListener('touchmove', onPointerMove, { passive: false });
            document.addEventListener('touchend', onPointerUp);

            e.preventDefault();
        };

        var onPointerMove = function(e) {
            if (!isDragging) return;
            var clientX = e.clientX || (e.touches && e.touches[0].clientX) || 0;
            var delta = clientX - startX;
            var newWidth = startWidth + delta;

            if (newWidth < 180) newWidth = 180;
            if (newWidth > 550) newWidth = 550;

            document.documentElement.style.setProperty('--chronolms-sidebar-width', Math.round(newWidth) + 'px');
            if (e.cancelable) e.preventDefault();
        };

        var onPointerUp = function(e) {
            if (!isDragging) return;
            isDragging = false;
            document.body.classList.remove('is-resizing-sidebar');
            resizer.classList.remove('is-resizing');

            document.removeEventListener('mousemove', onPointerMove);
            document.removeEventListener('mouseup', onPointerUp);
            document.removeEventListener('touchmove', onPointerMove);
            document.removeEventListener('touchend', onPointerUp);

            var finalWidth = sidebar.getBoundingClientRect().width;
            try {
                localStorage.setItem('chronolms_sidebar_width', Math.round(finalWidth) + 'px');
            } catch (err) {}

            try {
                window.dispatchEvent(new Event('resize'));
            } catch (err) {}
        };

        resizer.addEventListener('mousedown', onPointerDown);
        resizer.addEventListener('touchstart', onPointerDown, { passive: false });

        // Double click to reset to default 260px
        resizer.addEventListener('dblclick', function() {
            document.documentElement.style.setProperty('--chronolms-sidebar-width', '260px');
            try {
                localStorage.setItem('chronolms_sidebar_width', '260px');
                window.dispatchEvent(new Event('resize'));
            } catch (err) {}
        });
    }

    function initSidebar() {
        var sidebar = document.getElementById('chronolms-sidebar');
        var wrapper = document.getElementById('chronolms-content-wrapper');
        var toggle = document.getElementById('sidebar-toggle-btn');
        var inner = document.getElementById('sidebar-collapse-btn');   // menu button inside the sidebar (desktop)
        var overlay = document.getElementById('sidebar-overlay');
        if (!sidebar || !toggle) {
            return;
        }

        // Desktop: collapse to the icon rail, remembered across pages.
        function setCollapsed(collapsed, save) {
            sidebar.classList.toggle('collapsed', collapsed);
            if (wrapper) {
                wrapper.classList.toggle('sidebar-collapsed', collapsed);
            }
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            if (inner) {
                inner.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                inner.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
            }
            if (save) {
                writeCollapsed(collapsed);
            }
        }

        // Drawer (tablet and phone): slides in over the page. While closed it is inert, so
        // keyboard users do not tab into links they cannot see.
        function setOpen(open, restoreFocus) {
            sidebar.classList.toggle('open', open);
            if (overlay) {
                overlay.classList.toggle('visible', open);
            }
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            sidebar.inert = !open;
            if (open) {
                // Move focus into the drawer. Flush styles first so it counts as visible, and retry once
                // after the 0.3s slide-in in case the browser still refused.
                var focusDrawer = function() {
                    if (!sidebar.classList.contains('open') || sidebar.contains(document.activeElement)) {
                        return;
                    }
                    var target = sidebar.querySelector('.sidebar-nav-item.active a') || sidebar.querySelector('a[href], button');
                    if (target) {
                        target.focus();
                    }
                };
                void sidebar.offsetWidth;
                focusDrawer();
                window.setTimeout(focusDrawer, 350);
            } else if (restoreFocus) {
                toggle.focus();
            }
        }

        function applyMode() {
            sidebar.classList.remove('open');
            if (overlay) {
                overlay.classList.remove('visible');
            }
            if (isDrawer()) {
                sidebar.classList.remove('collapsed');
                if (wrapper) {
                    wrapper.classList.remove('sidebar-collapsed');
                }
                toggle.setAttribute('aria-expanded', 'false');
                sidebar.inert = true;
            } else {
                sidebar.inert = false;
                setCollapsed(readCollapsed(), false);
            }
        }

        toggle.addEventListener('click', function() {
            if (isDrawer()) {
                setOpen(!sidebar.classList.contains('open'), false);
            } else {
                setCollapsed(!sidebar.classList.contains('collapsed'), true);
            }
        });

        if (inner) {
            inner.addEventListener('click', function() {
                if (!isDrawer()) {
                    setCollapsed(!sidebar.classList.contains('collapsed'), true);
                }
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function() {
                setOpen(false, true);
            });
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isDrawer() && sidebar.classList.contains('open')) {
                setOpen(false, true);
            }
        });

        var mq = window.matchMedia(DRAWER_QUERY);
        if (mq.addEventListener) {
            mq.addEventListener('change', applyMode);
        } else if (mq.addListener) {
            mq.addListener(applyMode);
        }
        applyMode();
    }

    // My courses: search box and progress pills.
    function initCourseFilters() {
        var cards = document.querySelectorAll('#courses-grid .mc-card');
        if (!cards.length) {
            return;
        }
        var search = document.getElementById('mc-search');
        var pills = document.querySelectorAll('[data-mc-status]');
        var empty = document.getElementById('mc-empty');
        var status = '';
        var apply = function() {
            var query = search ? search.value.trim().toLowerCase() : '';
            var shown = 0;
            cards.forEach(function(card) {
                var okStatus = !status || card.dataset.status === status;
                var okText = !query || (card.dataset.title || '').toLowerCase().indexOf(query) !== -1;
                card.hidden = !(okStatus && okText);
                if (!card.hidden) {
                    shown++;
                }
            });
            if (empty) {
                empty.hidden = shown > 0;
            }
        };
        pills.forEach(function(pill) {
            pill.addEventListener('click', function() {
                status = pill.dataset.mcStatus;
                pills.forEach(function(p) {
                    p.setAttribute('aria-pressed', p === pill ? 'true' : 'false');
                });
                apply();
            });
        });
        if (search) {
            search.addEventListener('input', apply);
        }
    }

    function initAssessmentFilters() {
        var rows = document.querySelectorAll('#as-list .as-row');
        var pills = document.querySelectorAll('[data-as-group]');
        if (!rows.length || !pills.length) {
            return;
        }
        var empty = document.getElementById('as-empty');
        pills.forEach(function(pill) {
            pill.addEventListener('click', function() {
                var group = pill.dataset.asGroup;
                var shown = 0;
                pills.forEach(function(p) {
                    p.setAttribute('aria-pressed', p === pill ? 'true' : 'false');
                });
                rows.forEach(function(row) {
                    row.hidden = !!group && row.dataset.group !== group;
                    if (!row.hidden) {
                        shown++;
                    }
                });
                if (empty) {
                    empty.hidden = shown > 0;
                }
            });
        });
    }

    // The VPL editor needs ~40 scripts and a few requests before it can draw anything. Until it does,
    // show a loading panel instead of an empty dark strip (styled in scss/_coding_lab.scss).
    function initEditorLoading() {
        var body = document.body;
        if (!body || body.id !== 'page-mod-vpl-edit') {
            return;
        }
        var ide = document.getElementById('vplide');
        if (!ide) {
            return;
        }
        body.classList.add('lab-booting');
        var done = false;
        var finish = function() {
            if (done) {
                return;
            }
            done = true;
            body.classList.remove('lab-booting');
            clearInterval(timer);
        };
        var ready = function() {
            if (ide.querySelector('.ace_editor')) {
                return true;
            }
            // Problems with no starter file open VPL's "new file" box instead of an editor.
            var dialogs = document.querySelectorAll('.ui-dialog');
            for (var i = 0; i < dialogs.length; i++) {
                if (dialogs[i].style.display !== 'none' && /new file/i.test(dialogs[i].textContent.slice(0, 80))) {
                    return true;
                }
            }
            return false;
        };
        var timer = setInterval(function() {
            if (ready()) {
                finish();
            }
        }, 200);
        setTimeout(finish, 30000);
    }

    // Celebrations (see theme_chronolms_celebration in lib.php): points gained since the last visit, a new
    // level, newly earned badges. Each is shown once; reduced-motion users get the same messages without motion.
    function initCelebrations() {
        var data = document.getElementById('clms-celebrate');
        if (!data) {
            return;
        }
        var info;
        try {
            info = JSON.parse(data.textContent);
        } catch (e) {
            return;
        }
        var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (info.xp) {
            var toast = document.createElement('div');
            toast.className = 'clms-xp-toast';
            toast.setAttribute('role', 'status');
            toast.textContent = '+' + info.xp.gain + ' XP';
            document.body.appendChild(toast);
            window.setTimeout(function() {
                toast.remove();
            }, 3200);
            // Fill the dashboard's level bar from empty so the gain is visible.
            var bar = document.querySelector('.dash-profile .dash-bar > i');
            if (bar && !calm) {
                bar.style.transition = 'none';
                bar.style.width = '0%';
                void bar.offsetWidth;
                bar.style.transition = 'width 1.1s cubic-bezier(.2,.8,.2,1) .3s';
                bar.style.width = info.xp.pct + '%';
            }
        }

        var queue = [];
        if (info.levelup) {
            queue.push({kind: 'level', mark: String(info.levelup.level),
                title: 'Level ' + info.levelup.level + ': ' + info.levelup.title,
                text: info.levelup.ismax ? 'You reached the top level. Amazing work.'
                    : 'You levelled up. Keep going to reach the next one.'});
        }
        (info.badges || []).forEach(function(b) {
            queue.push({kind: 'badge', icon: b.icon, title: b.name, text: 'Badge earned. ' + b.desc + '.'});
        });

        var confetti = function(host) {
            if (calm) {
                return;
            }
            var colours = ['#378ADD', '#EF9F27', '#1D9E75', '#D4537E', '#7F77DD', '#26c6da'];
            for (var i = 0; i < 60; i++) {
                var c = document.createElement('span');
                c.className = 'clms-confetti';
                c.style.left = (Math.random() * 100) + '%';
                c.style.background = colours[i % colours.length];
                c.style.animationDelay = (Math.random() * 0.5) + 's';
                c.style.animationDuration = (1.6 + Math.random() * 1.2) + 's';
                host.appendChild(c);
            }
        };

        var showNext = function() {
            var item = queue.shift();
            if (!item) {
                return;
            }
            var previous = document.activeElement;
            var overlay = document.createElement('div');
            overlay.className = 'clms-celebrate';
            overlay.innerHTML = '<div class="clms-celebrate-card" role="dialog" aria-modal="true" aria-labelledby="clms-cel-title">' +
                '<div class="clms-celebrate-medal clms-celebrate-' + item.kind + '"></div>' +
                '<h2 class="clms-celebrate-title" id="clms-cel-title"></h2>' +
                '<p class="clms-celebrate-text"></p>' +
                '<button type="button" class="clms-celebrate-btn">Keep going</button></div>';
            var medal = overlay.querySelector('.clms-celebrate-medal');
            if (item.icon) {
                medal.innerHTML = item.icon;   // trusted SVG from theme_chronolms\icons
            } else {
                medal.textContent = item.mark;
            }
            overlay.querySelector('.clms-celebrate-title').textContent = item.title;
            overlay.querySelector('.clms-celebrate-text').textContent = item.text;
            document.body.appendChild(overlay);
            confetti(overlay);
            var close = function() {
                overlay.remove();
                document.removeEventListener('keydown', onKey);
                if (queue.length) {
                    showNext();
                } else if (previous && previous.focus) {
                    previous.focus();
                }
            };
            var onKey = function(e) {
                if (e.key === 'Escape' || e.key === 'Enter') {
                    e.preventDefault();
                    close();
                }
            };
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    close();
                }
            });
            overlay.querySelector('.clms-celebrate-btn').addEventListener('click', close);
            document.addEventListener('keydown', onKey);
            overlay.querySelector('.clms-celebrate-btn').focus();
        };
        // Let the page and the XP toast land first.
        window.setTimeout(showNext, info.xp ? 900 : 300);
    }

    function initCodingLabSidebar() {
        var tabPrograms = document.getElementById('sidebar-tab-programs');
        var tabMenu = document.getElementById('sidebar-tab-menu');
        var codinglabPane = document.getElementById('sidebar-codinglab-pane');
        var mainNav = document.getElementById('sidebar-main-nav');

        if (tabPrograms && tabMenu && codinglabPane && mainNav) {
            tabPrograms.addEventListener('click', function() {
                tabPrograms.classList.add('active');
                tabPrograms.setAttribute('aria-pressed', 'true');
                tabMenu.classList.remove('active');
                tabMenu.setAttribute('aria-pressed', 'false');

                codinglabPane.classList.remove('d-none');
                mainNav.classList.add('d-none');
            });

            tabMenu.addEventListener('click', function() {
                tabMenu.classList.add('active');
                tabMenu.setAttribute('aria-pressed', 'true');
                tabPrograms.classList.remove('active');
                tabPrograms.setAttribute('aria-pressed', 'false');

                codinglabPane.classList.add('d-none');
                mainNav.classList.remove('d-none');
            });
        }

        // Handle level accordion collapse/expand matching Attachment 1
        var levelHeaders = document.querySelectorAll('.codinglab-level-header');
        levelHeaders.forEach(function(header) {
            header.addEventListener('click', function(e) {
                e.preventDefault();
                var card = header.closest('.codinglab-level-card');
                if (!card) return;
                var list = card.querySelector('.codinglab-programs-list');
                var isExpanded = card.classList.contains('expanded');

                if (isExpanded) {
                    card.classList.remove('expanded');
                    header.setAttribute('aria-expanded', 'false');
                    if (list) list.style.display = 'none';
                } else {
                    card.classList.add('expanded');
                    header.setAttribute('aria-expanded', 'true');
                    if (list) list.style.display = 'flex';
                }
            });
        });

        // Auto-scroll current active program into view
        var activeItem = document.querySelector('.codinglab-program-item.active');
        if (activeItem) {
            var activeCard = activeItem.closest('.codinglab-level-card');
            if (activeCard) {
                activeCard.classList.add('expanded');
                var h = activeCard.querySelector('.codinglab-level-header');
                if (h) h.setAttribute('aria-expanded', 'true');
                var l = activeCard.querySelector('.codinglab-programs-list');
                if (l) l.style.display = 'flex';
            }
            setTimeout(function() {
                activeItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 300);
        }
    }

    function initChatbot() {
        var toggle = document.getElementById('chronolms-chatbot-toggle');
        var panel = document.getElementById('chronolms-chatbot-panel');
        var close = document.getElementById('chatbot-close-btn');
        var inner = document.getElementById('chronolms-chatbot-inner');
        if (!toggle || !panel) {
            return;
        }
        document.body.classList.add('chronolms-chatbot-ready');
        panel.setAttribute('aria-hidden', 'true');
        toggle.setAttribute('aria-expanded', 'false');

        var setupNativeBot = function() {
            if (!inner) return false;
            var block = document.querySelector(
                '.chronolms-plugin-blocks .block_chronexa, ' +
                '.chronolms-plugin-blocks .block_openai_chat'
            );
            if (block) {
                block.hidden = false;
                inner.appendChild(block);
                return true;
            }
            return false;
        };

        var setupInteractiveChronoBot = function() {
            if (!inner || inner.querySelector('.chronolms-bot-wrapper')) return;

            // Build self-contained ChronoBot AI interface
            var wrapper = document.createElement('div');
            wrapper.className = 'chronolms-bot-wrapper';
            wrapper.innerHTML =
                '<div class="chronolms-bot-messages" id="chronolms-bot-messages">' +
                    '<div class="bot-bubble-row bot">' +
                        '<div class="bot-bubble-avatar">' + BOT_ICON + '</div>' +
                        '<div class="bot-bubble-msg">' +
                            '<p>Hello! I\'m <strong>ChronoBot</strong>, your AI coding assistant.</p>' +
                            '<p>How can I help you today? You can ask me to explain a concept, find a syntax error, or show Python examples.</p>' +
                        '</div>' +
                    '</div>' +
                    '<div class="bot-suggestions-bar">' +
                        '<button type="button" class="bot-chip" data-prompt="How do I use print() in Python?">print() help</button>' +
                        '<button type="button" class="bot-chip" data-prompt="Explain this coding challenge">Explain problem</button>' +
                        '<button type="button" class="bot-chip" data-prompt="How do Python loops work?">Loops guide</button>' +
                        '<button type="button" class="bot-chip" data-prompt="How do if-else statements work?">if-else logic</button>' +
                    '</div>' +
                '</div>' +
                '<form class="chronolms-bot-input-form" id="chronolms-bot-form">' +
                    '<input type="text" id="chronolms-bot-input" placeholder="Ask ChronoBot about your code..." autocomplete="off" />' +
                    '<button type="submit" id="chronolms-bot-send" aria-label="Send message">' +
                        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>' +
                    '</button>' +
                '</form>';

            inner.appendChild(wrapper);

            var messagesContainer = document.getElementById('chronolms-bot-messages');
            var form = document.getElementById('chronolms-bot-form');
            var input = document.getElementById('chronolms-bot-input');

            var scrollToBottom = function() {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            };

            var appendMessage = function(sender, text, isHtml) {
                var row = document.createElement('div');
                row.className = 'bot-bubble-row ' + sender;
                var avatar = sender === 'user' ? USER_ICON : BOT_ICON;
                var contentHtml = isHtml ? text : ('<p>' + text.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>') + '</p>');
                row.innerHTML =
                    '<div class="bot-bubble-avatar">' + avatar + '</div>' +
                    '<div class="bot-bubble-msg">' + contentHtml + '</div>';
                messagesContainer.appendChild(row);
                scrollToBottom();
            };

            var getAiResponse = function(query) {
                var q = query.toLowerCase().trim();

                if (q.indexOf('print') !== -1 || q.indexOf('hello world') !== -1) {
                    return '<p>In Python, the <code>print()</code> function outputs text or variables to the console:</p>' +
                           '<pre><code># Basic string output\nprint("Hello World")\n\n# Multiple values\nname = "Chrono"\nprint("Welcome,", name)</code></pre>' +
                           '<p><strong>Tip:</strong> Always enclose text inside quotation marks (single <code>\'...\'</code> or double <code>"..."</code>).</p>';
                }

                if (q.indexOf('loop') !== -1 || q.indexOf('for') !== -1 || q.indexOf('while') !== -1) {
                    return '<p>Python supports <code>for</code> and <code>while</code> loops:</p>' +
                           '<pre><code># For loop with range\nfor i in range(1, 6):\n    print("Count:", i)\n\n# While loop\ncount = 0\nwhile count < 3:\n    print(count)\n    count += 1</code></pre>' +
                           '<p><strong>Remember:</strong> Python uses indentation (4 spaces) to define the block inside the loop!</p>';
                }

                if (q.indexOf('if') !== -1 || q.indexOf('condition') !== -1 || q.indexOf('else') !== -1) {
                    return '<p>Use <code>if</code>, <code>elif</code>, and <code>else</code> for decision making:</p>' +
                           '<pre><code>score = 85\nif score >= 90:\n    print("Grade A")\nelif score >= 75:\n    print("Grade B")\nelse:\n    print("Keep Practicing!")</code></pre>' +
                           '<p>Conditions evaluate to <code>True</code> or <code>False</code>.</p>';
                }

                if (q.indexOf('explain') !== -1 || q.indexOf('challenge') !== -1 || q.indexOf('problem') !== -1) {
                    var descEl = document.querySelector('.vpl_ide_accordion_c_description');
                    var descText = descEl ? descEl.innerText.trim() : '';
                    if (descText) {
                        return '<p><strong>Current Challenge Summary:</strong></p>' +
                               '<p>' + descText.substring(0, 300) + '...</p>' +
                               '<p><strong>Strategy:</strong> Read the required output format carefully, check if capital letters or spaces are required, and test your solution with the <strong>Run</strong> button before clicking <strong>Evaluate</strong>.</p>';
                    }
                    return '<p>To solve this coding challenge: breakdown the goal into smaller steps. First identify what inputs you receive, what operations you must perform, and the exact output expected.</p>';
                }

                if (q.indexOf('indent') !== -1 || q.indexOf('syntax') !== -1 || q.indexOf('error') !== -1) {
                    return '<p><strong>Fixing Common Python Errors:</strong></p>' +
                           '<ul>' +
                           '<li><strong>IndentationError:</strong> Ensure all lines inside functions, loops, or if statements are indented consistently by 4 spaces.</li>' +
                           '<li><strong>SyntaxError:</strong> Check for missing colons <code>:</code> at the end of <code>if</code>, <code>for</code>, or <code>def</code> lines, or unmatched parentheses <code>()</code>.</li>' +
                           '<li><strong>NameError:</strong> Make sure you have defined and spelled your variables correctly.</li>' +
                           '</ul>';
                }

                return '<p>Great question! In programming, breaking problems into clear steps is key. Check your logic, verify edge cases, and run your code to inspect the output.</p>' +
                       '<p>Is there a specific line of code or error message you would like me to explain?</p>';
            };

            var handleSend = function(text) {
                if (!text || !text.trim()) return;
                appendMessage('user', text.trim(), false);

                // Add typing indicator
                var typingRow = document.createElement('div');
                typingRow.className = 'bot-typing-row';
                typingRow.innerHTML =
                    '<div class="bot-bubble-avatar">' + BOT_ICON + '</div>' +
                    '<div class="bot-typing-indicator"><span></span><span></span><span></span></div>';
                messagesContainer.appendChild(typingRow);
                scrollToBottom();

                setTimeout(function() {
                    if (typingRow.parentNode) {
                        typingRow.parentNode.removeChild(typingRow);
                    }
                    var reply = getAiResponse(text);
                    appendMessage('bot', reply, true);
                }, 600);
            };

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var text = input.value;
                input.value = '';
                handleSend(text);
            });

            // Delegate suggestion chips
            messagesContainer.addEventListener('click', function(e) {
                var chip = e.target.closest('.bot-chip');
                if (chip) {
                    var prompt = chip.getAttribute('data-prompt');
                    if (prompt) handleSend(prompt);
                }
            });
        };

        var setOpen = function(open) {
            panel.classList.toggle('open', open);
            panel.setAttribute('aria-hidden', open ? 'false' : 'true');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                var hasNative = setupNativeBot();
                if (!hasNative) {
                    setupInteractiveChronoBot();
                }
                var inp = document.getElementById('chronolms-bot-input');
                if (inp) {
                    setTimeout(function() { inp.focus(); }, 200);
                }
            }
        };

        toggle.addEventListener('click', function() {
            setOpen(!panel.classList.contains('open'));
        });
        if (close) {
            close.addEventListener('click', function() { setOpen(false); });
        }
        document.querySelectorAll('.chatbot-trigger a, [data-chatbot-trigger]').forEach(function(trigger) {
            trigger.addEventListener('click', function(event) {
                event.preventDefault();
                setOpen(true);
            });
        });
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        });
    }

    function initVplPage() {
        // Cleanly hide the administrative metadata block on mod/vpl/view.php
        var descBox = document.querySelector('#page-mod-vpl-view .box.generalbox .box.generalbox');
        if (descBox && descBox.parentNode) {
            var outer = descBox.parentNode;
            var toRemove = [];
            for (var i = 0; i < outer.childNodes.length; i++) {
                var node = outer.childNodes[i];
                if (node === descBox) {
                    break;
                }
                toRemove.push(node);
            }
            toRemove.forEach(function(n) {
                if (n.parentNode) {
                    n.parentNode.removeChild(n);
                }
            });
            outer.style.border = 'none';
            outer.style.background = 'transparent';
            outer.style.boxShadow = 'none';
            outer.style.padding = '0';
        }

        // Resizable Split enforcement for VPL Editor & Description
        var tr = document.getElementById('vpl_tr');
        var tabs = document.getElementById('vpl_tabs');
        var results = document.getElementById('vpl_results');

        if (tr && tabs && results) {
            // Create draggable splitter handle between tabs and results if not present
            var splitter = document.getElementById('vpl_splitter');
            if (!splitter) {
                splitter = document.createElement('div');
                splitter.id = 'vpl_splitter';
                splitter.className = 'vpl-ide-splitter';
                splitter.title = 'Drag to resize Editor & Description (Double-click to reset 50/50)';
                tr.insertBefore(splitter, results);
            }

            var currentSplit = '50%';
            try {
                var savedSplit = localStorage.getItem('chronolms_vpl_split');
                if (savedSplit) {
                    var sFloat = parseFloat(savedSplit);
                    if (sFloat >= 20 && sFloat <= 80) {
                        currentSplit = sFloat.toFixed(1) + '%';
                    }
                }
            } catch (e) {}

            var enforceSplit = function(customPct) {
                if (customPct) currentSplit = customPct;

                tr.style.setProperty('display', 'flex', 'important');
                tr.style.setProperty('width', '100%', 'important');
                tr.style.setProperty('flex-direction', 'row', 'important');
                tr.style.setProperty('--vpl-editor-split', currentSplit, 'important');

                tabs.style.setProperty('width', currentSplit, 'important');
                tabs.style.setProperty('max-width', '85%', 'important');
                tabs.style.setProperty('min-width', '200px', 'important');
                tabs.style.setProperty('flex', '0 0 ' + currentSplit, 'important');

                results.style.setProperty('flex', '1 1 0%', 'important');
                results.style.setProperty('max-width', '85%', 'important');
                results.style.setProperty('min-width', '200px', 'important');
                results.style.setProperty('width', 'calc(100% - ' + currentSplit + ' - 7px)', 'important');
                results.style.setProperty('display', 'block', 'important');

                // Resize Ace editor
                if (window.ace) {
                    var aceEls = document.querySelectorAll('.ace_editor');
                    aceEls.forEach(function(el) {
                        try {
                            var ed = window.ace.edit(el);
                            if (ed) ed.resize();
                        } catch (err) {}
                    });
                }
            };

            // Run immediately and after layout settles
            enforceSplit();
            setTimeout(function() { enforceSplit(); }, 250);
            setTimeout(function() { enforceSplit(); }, 800);

            // Automatically open Description tab if not already active
            var descH4 = document.querySelector('.vpl_ide_accordion_t_description');
            if (descH4 && !descH4.classList.contains('ui-state-active')) {
                descH4.click();
            }

            // Draggable splitter interaction
            var isSplitting = false;
            var onSplitDown = function(e) {
                isSplitting = true;
                document.body.classList.add('is-resizing-vpl');
                splitter.classList.add('is-resizing');

                document.addEventListener('mousemove', onSplitMove);
                document.addEventListener('mouseup', onSplitUp);
                document.addEventListener('touchmove', onSplitMove, { passive: false });
                document.addEventListener('touchend', onSplitUp);

                e.preventDefault();
            };

            var onSplitMove = function(e) {
                if (!isSplitting) return;
                var clientX = e.clientX || (e.touches && e.touches[0].clientX) || 0;
                var rect = tr.getBoundingClientRect();
                var pct = ((clientX - rect.left) / rect.width) * 100;

                if (pct < 20) pct = 20;
                if (pct > 80) pct = 80;

                var pctStr = pct.toFixed(1) + '%';
                enforceSplit(pctStr);
                if (e.cancelable) e.preventDefault();
            };

            var onSplitUp = function(e) {
                if (!isSplitting) return;
                isSplitting = false;
                document.body.classList.remove('is-resizing-vpl');
                splitter.classList.remove('is-resizing');

                document.removeEventListener('mousemove', onSplitMove);
                document.removeEventListener('mouseup', onSplitUp);
                document.removeEventListener('touchmove', onSplitMove);
                document.removeEventListener('touchend', onSplitUp);

                try {
                    localStorage.setItem('chronolms_vpl_split', currentSplit);
                } catch (err) {}

                enforceSplit();
            };

            splitter.addEventListener('mousedown', onSplitDown);
            splitter.addEventListener('touchstart', onSplitDown, { passive: false });

            // Double click splitter to reset to 50/50
            splitter.addEventListener('dblclick', function() {
                enforceSplit('50%');
                try {
                    localStorage.setItem('chronolms_vpl_split', '50%');
                } catch (err) {}
            });

            // MutationObserver to prevent VPL's inline JS from overwriting split
            var obs = new MutationObserver(function(mutations) {
                var needsReapply = false;
                mutations.forEach(function(m) {
                    if (m.attributeName === 'style') {
                        var target = m.target;
                        if (target === tabs || target === results) {
                            var w = target.style.width;
                            if (w && w !== currentSplit && !w.startsWith('calc')) {
                                needsReapply = true;
                            }
                        }
                    }
                });
                if (needsReapply) {
                    enforceSplit();
                }
            });
            obs.observe(tabs, { attributes: true, attributeFilter: ['style'] });
            obs.observe(results, { attributes: true, attributeFilter: ['style'] });

            window.addEventListener('resize', function() {
                setTimeout(function() { enforceSplit(); }, 100);
            });
        }
    }

    function initVplIndexFilters() {
        if (!document.body.classList.contains('path-mod-vpl') && !document.getElementById('page-mod-vpl-index')) {
            return;
        }
        var urlSelectDivs = document.querySelectorAll('div.urlselect');
        if (urlSelectDivs.length > 0 && !document.querySelector('.vpl-filters-bar')) {
            var bar = document.createElement('div');
            bar.className = 'vpl-filters-bar';
            urlSelectDivs[0].parentNode.insertBefore(bar, urlSelectDivs[0]);

            urlSelectDivs.forEach(function(div) {
                var form = div.querySelector('form');
                var label = div.querySelector('label');
                if (form && label) {
                    var targetId = label.getAttribute('for');
                    if (targetId) {
                        var sel = document.getElementById(targetId);
                        if (sel && sel.parentNode !== form) {
                            form.appendChild(sel);
                        }
                    }
                }
                bar.appendChild(div);
            });
        }

        // Clean up double-escaped HTML entities in table cells and breadcrumbs (e.g. &amp;amp; -> &amp; -> &)
        var targets = document.querySelectorAll('#page-mod-vpl-index td.cell, #page-mod-vpl-index th, #page-mod-vpl-index .breadcrumb-current, .generaltable td');
        targets.forEach(function(el) {
            if (el.innerHTML && el.innerHTML.indexOf('&amp;') !== -1) {
                el.innerHTML = el.innerHTML.replace(/&amp;(amp|lt|gt|quot|#039);/g, '&$1;');
            }
        });
    }

    function initSidebarActiveState() {
        var path = window.location.pathname;
        var search = window.location.search;
        var activeId = null;

        if (path.indexOf('/my/courses.php') !== -1 || path.indexOf('/course/index.php') !== -1) {
            activeId = 'mycourses';
        } else if (path.indexOf('/theme/chronolms/learningpath.php') !== -1) {
            activeId = 'learningpath';
        } else if (path.indexOf('/course/view.php') !== -1) {
            activeId = 'mycourses';
        } else if (path.indexOf('/mod/quiz') !== -1 || (path.indexOf('/course/overview.php') !== -1 && (search.indexOf('quiz') !== -1 || search.indexOf('expand') !== -1))) {
            activeId = 'tests';
        } else if (path.indexOf('/mod/vpl') !== -1 || path.indexOf('/mod/nextblocks') !== -1) {
            activeId = 'codinglab';
        } else if (path.indexOf('/local/code_analytics') !== -1 || path.indexOf('/theme/chronolms/progress.php') !== -1 || path.indexOf('/grade') !== -1 || path.indexOf('/report/progress') !== -1 || path.indexOf('/report/outline') !== -1) {
            activeId = 'progress';
        } else if (path.indexOf('/mod/customcert') !== -1 || path.indexOf('/badges') !== -1) {
            activeId = 'certificates';
        } else if (path.indexOf('/calendar') !== -1) {
            activeId = 'calendar';
        } else if (path.indexOf('/admin') !== -1) {
            activeId = 'admin';
        } else if (path.indexOf('/my') !== -1 || path === '/' || (path.endsWith('/index.php') && path.indexOf('/local/') === -1 && path.indexOf('/course/') === -1 && path.indexOf('/mod/') === -1)) {
            if (path.indexOf('/login') === -1 && path.indexOf('/error') === -1) {
                activeId = 'dashboard';
            }
        }

        if (activeId) {
            var items = document.querySelectorAll('.sidebar-nav-item');
            items.forEach(function(item) {
                if (item.getAttribute('data-navid') === activeId) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
        }
    }

    function initSecondaryTabs() {
        document.addEventListener('click', function(e) {
            // Only provide manual toggle for .dropdownmoremenu if Bootstrap 5 native handler is absent
            var moreMenuToggle = e.target.closest('.secondary-navigation .dropdownmoremenu > .dropdown-toggle');
            if (moreMenuToggle && typeof window.bootstrap === 'undefined') {
                var parentLi = moreMenuToggle.closest('.dropdown');
                var menu = parentLi ? parentLi.querySelector('.dropdown-menu') : null;
                if (menu) {
                    e.preventDefault();
                    var isShown = menu.classList.contains('show');
                    document.querySelectorAll('.dropdown-menu.show').forEach(function(m) {
                        m.classList.remove('show');
                    });
                    if (!isShown) {
                        menu.classList.add('show');
                        moreMenuToggle.setAttribute('aria-expanded', 'true');
                    } else {
                        moreMenuToggle.setAttribute('aria-expanded', 'false');
                    }
                    return;
                }
            }

            var link = e.target.closest('.secondary-navigation .nav-link[data-bs-toggle="tab"], .secondary-navigation .nav-link[data-toggle="tab"]');
            if (!link) {
                return;
            }
            var href = link.getAttribute('href');
            if (href && href.charAt(0) === '#' && href.length > 1) {
                e.preventDefault();
                var targetPane = document.querySelector(href);
                if (targetPane) {
                    var nav = link.closest('.more-nav, .nav-tabs');
                    if (nav) {
                        nav.querySelectorAll('.nav-link').forEach(function(l) {
                            l.classList.remove('active');
                            l.setAttribute('aria-selected', 'false');
                        });
                    }
                    link.classList.add('active');
                    link.setAttribute('aria-selected', 'true');

                    var content = targetPane.closest('.tab-content');
                    if (content) {
                        content.querySelectorAll('.tab-pane').forEach(function(p) {
                            p.classList.remove('active', 'show');
                        });
                    }
                    targetPane.classList.add('active', 'show');

                    if (window.history && window.history.replaceState) {
                        window.history.replaceState(null, null, href);
                    }
                }
            }
        });

        if (window.location.hash) {
            var targetLink = document.querySelector('.secondary-navigation .nav-link[href="' + window.location.hash + '"]');
            if (targetLink) {
                targetLink.click();
            }
        }
    }

    // Dashboard class leaderboard: "This week" / "All time" tabs.
    function initLeaderboardTabs() {
        document.querySelectorAll('[data-lb]').forEach(function(board) {
            var tabs = board.querySelectorAll('[data-lb-tab]');
            tabs.forEach(function(tab) {
                tab.addEventListener('click', function() {
                    tabs.forEach(function(t) {
                        t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
                    });
                    board.querySelectorAll('[data-lb-panel]').forEach(function(panel) {
                        panel.hidden = panel.getAttribute('data-lb-panel') !== tab.getAttribute('data-lb-tab');
                    });
                });
            });
        });
    }

    // "Skip to main content": on pages that hide Moodle's own main-content block (dashboard, My courses)
    // its anchor is invisible, so point the link at the visible content area instead.
    function initSkipLink() {
        var link = document.querySelector('a[href="#maincontent"]');
        var target = document.getElementById('maincontent');
        var region = document.getElementById('region-main');
        if (link && region && (!target || target.closest('.d-none'))) {
            region.setAttribute('tabindex', '-1');
            link.setAttribute('href', '#region-main');
        }
    }

    // Desktop navigation feel: react the instant a link is clicked (progress bar, new sidebar item
    // highlighted) and ignore repeat clicks on a link that is already loading - clicking again used to
    // cancel the page that was on its way and start over, so it looked like clicks were being lost.
    // Tablet and phone are handled separately.
    function initNavFeedback() {
        var pending = null;
        var shown = null;       // sidebar item highlighted early for the click, and the item(s) it replaced
        var clear = function() {
            pending = null;
            document.documentElement.classList.remove('clms-navigating');
            // The navigation did not happen (back button, error, prompt): put the highlight back where it was.
            if (shown) {
                shown.item.classList.remove('active');
                shown.previous.forEach(function(el) {
                    el.classList.add('active');
                });
                shown = null;
            }
        };

        document.addEventListener('click', function(e) {
            if (isDrawer() || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                return;
            }
            var link = e.target.closest ? e.target.closest('a[href]') : null;
            if (!link || (link.target && link.target !== '_self') || link.hasAttribute('download')
                    || link.hasAttribute('data-bs-toggle') || link.hasAttribute('data-toggle') || link.hasAttribute('data-action')) {
                return;
            }
            var url;
            try {
                url = new URL(link.href, window.location.href);
            } catch (err) {
                return;
            }
            if ((url.protocol !== 'http:' && url.protocol !== 'https:') || url.origin !== window.location.origin) {
                return;
            }
            // Same-page anchors and file downloads do not navigate away, so show no loading state for them.
            if ((url.pathname === window.location.pathname && url.search === window.location.search && url.hash)
                    || /\/(pluginfile|draftfile|webservice\/pluginfile)\.php/.test(url.pathname)
                    || /[?&]forcedownload=/.test(url.search)) {
                return;
            }
            if (pending && pending.href === url.href && Date.now() - pending.at < 10000) {
                e.preventDefault();
                return;
            }
            pending = {href: url.href, at: Date.now()};
            document.documentElement.classList.add('clms-navigating');
            var item = link.closest('.sidebar-nav-item');
            if (item) {
                var previous = Array.prototype.slice.call(document.querySelectorAll('.sidebar-nav-item.active'));
                previous.forEach(function(el) {
                    el.classList.remove('active');
                });
                item.classList.add('active');
                // Keep the first remembered set when clicks follow each other before the page changes.
                shown = shown ? {item: item, previous: shown.previous} : {item: item, previous: previous};
            }
            // If the navigation never happens (a "leave page?" prompt, an error), do not stay in a loading state.
            window.setTimeout(clear, 12000);
        });

        // Returning via the back button restores the page from memory: reset the loading state, restore the
        // highlight, and drop the focus ring the browser puts back on the link that was clicked.
        window.addEventListener('pageshow', function(e) {
            clear();
            var sidebar = document.getElementById('chronolms-sidebar');
            if (e && e.persisted && sidebar && document.activeElement && sidebar.contains(document.activeElement)
                    && document.activeElement.blur) {
                document.activeElement.blur();
            }
        });
    }

    // Coding lab pages run without Bootstrap's script (it breaks the VPL editor's jQuery UI widgets), so
    // give them a minimal dropdown toggle for the user menu and action menus. The "More" tab menu has its own
    // handler in initSecondaryTabs.
    function initLabDropdowns() {
        // Same pages lib.php leaves without Bootstrap: Moodle puts these classes on the body.
        if (!document.body.classList.contains('path-mod-vpl') && !document.body.classList.contains('path-mod-nextblocks')) {
            return;
        }
        var closeAll = function(except) {
            document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
                if (menu === except) {
                    return;
                }
                menu.classList.remove('show');
                var owner = menu.parentElement ? menu.parentElement.querySelector('[data-bs-toggle="dropdown"]') : null;
                if (owner) {
                    owner.setAttribute('aria-expanded', 'false');
                }
            });
        };
        document.addEventListener('click', function(e) {
            var toggle = e.target.closest ? e.target.closest('[data-bs-toggle="dropdown"]') : null;
            if (!toggle || toggle.closest('.secondary-navigation .dropdownmoremenu')) {
                if (!toggle && !e.target.closest('.dropdown-menu')) {
                    closeAll(null);
                }
                return;
            }
            var menu = toggle.parentElement ? toggle.parentElement.querySelector('.dropdown-menu') : null;
            if (!menu) {
                return;
            }
            e.preventDefault();
            var willOpen = !menu.classList.contains('show');
            closeAll(menu);
            menu.classList.toggle('show', willOpen);
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAll(null);
            }
        });
    }

    function init() {
        initLabDropdowns();
        initNavFeedback();
        initSkipLink();
        initLeaderboardTabs();
        initSidebar();
        initSidebarResizer();
        initCodingLabSidebar();
        initSidebarActiveState();
        initSecondaryTabs();
        initCourseFilters();
        initAssessmentFilters();
        initEditorLoading();
        initCelebrations();
        initChatbot();
        initVplPage();
        initVplIndexFilters();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
