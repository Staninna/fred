<?php
/** @var callable $renderPartial */
/** @var callable $e */
/** @var Community|null $currentCommunity */
/** @var string|null $customCss */

use Fred\Domain\Community\Community;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $e($pageTitle ?? 'Fred Forum') ?></title>
    <link rel="stylesheet" href="/css/layout.css">
    <?php if (!empty($customCss)): ?>
        <style id="custom-css">
            <?= $e($customCss, ENT_NOQUOTES) ?>
        </style>
    <?php endif; ?>
    <script src="https://unpkg.com/alpinejs@3.13.5/dist/cdn.min.js" defer></script>
</head>
<body>
<table class="page-frame" cellspacing="0" cellpadding="0" align="center">
    <tr>
        <td class="banner" colspan="2">
            <div class="banner-title"><?= $e($pageTitle ?? 'Fred Forum') ?></div>
            <div class="banner-line">Classic forum interface. Environment: <?= $e($environment ?? 'local') ?></div>
            <div class="banner-links">
                <a href="/">Home</a> |
                <?php if (isset($currentUser) && $currentUser->isAuthenticated()): ?>
                    <?php if (isset($currentCommunity)): ?>
                        <a href="/c/<?= $e($currentCommunity->slug) ?>/u/<?= $e($currentUser->username) ?>">Signed in as <?= $e($currentUser->displayName) ?></a> |
                    <?php else: ?>
                        Signed in as <?= $e($currentUser->displayName) ?> |
                    <?php endif; ?>
                    <form class="inline-form" method="post" action="/logout">
                        <?= $renderPartial('partials/csrf.php') ?>
                        <button class="button" type="submit">Sign out</button>
                    </form>
                <?php else: ?>
                    <a href="/login">Login</a> | <a href="/register">Register</a>
                <?php endif; ?>
            </div>
        </td>
    </tr>
    <tr>
        <td class="sidebar" valign="top">
            <?= $renderPartial('partials/nav.php', [
                'navSections' => $navSections ?? null,
                'activePath' => $activePath ?? null,
                'currentUser' => $currentUser ?? null,
                'currentCommunity' => $currentCommunity ?? null,
            ]) ?>
        </td>
        <td class="content" valign="top" id="main-content">
            <?= $content ?? '' ?>
        </td>
    </tr>
    <tr>
        <td class="footer" colspan="2">
            Fred forum engine · <?= $e($environment ?? 'local') ?>
            <?php if (!empty($baseUrl)): ?>
                · Base URL: <?= $e($baseUrl) ?>
            <?php endif; ?>
        </td>
    </tr>
</table>
<script>
document.addEventListener('alpine:init', function () {
    window.Alpine.data('bbcodeToolbar', function (targetId) {
        return {
            targetId: targetId,
            get target() {
                return document.getElementById(this.targetId);
            },
            wrap: function(tag) {
                const el = this.target;
                if (!el) return;
                const start = el.selectionStart ?? el.value.length;
                const end = el.selectionEnd ?? el.value.length;
                const before = el.value.slice(0, start);
                const selection = el.value.slice(start, end);
                const after = el.value.slice(end);
                const open = '[' + tag + ']';
                const close = '[/' + tag + ']';
                if (selection === '') {
                    el.value = before + open + close + after;
                    const cursor = before.length + open.length;
                    el.focus();
                    el.setSelectionRange(cursor, cursor);
                    return;
                }

                el.value = before + open + selection + close + after;
                const cursor = start + open.length + selection.length + close.length;
                el.focus();
                el.setSelectionRange(cursor, cursor);
            },
            insertLink: function() {
                const url = prompt('Enter URL (include http/https):', 'http://');
                if (!url) return;
                const el = this.target;
                if (!el) return;
                const start = el.selectionStart ?? el.value.length;
                const end = el.selectionEnd ?? el.value.length;
                const before = el.value.slice(0, start);
                const selection = el.value.slice(start, end);
                const after = el.value.slice(end);
                const label = selection !== '' ? selection : url;
                const snippet = '[url=' + url + ']' + label + '[/url]';
                el.value = before + snippet + after;
                const cursor = selection === ''
                    ? before.length + ('[url=' + url + ']').length
                    : before.length + snippet.length;
                el.focus();
                el.setSelectionRange(cursor, cursor);
            },
            insertImage: function() {
                const url = prompt('Image URL (http/https):', 'http://');
                if (!url) return;
                const el = this.target;
                if (!el) return;
                const start = el.selectionStart ?? el.value.length;
                const end = el.selectionEnd ?? el.value.length;
                const before = el.value.slice(0, start);
                const after = el.value.slice(end);
                const snippet = '[img]' + url + '[/img]';
                el.value = before + snippet + after;
                const cursor = before.length + snippet.length;
                el.focus();
                el.setSelectionRange(cursor, cursor);
            },
            insertList: function() {
                const el = this.target;
                if (!el) return;
                const start = el.selectionStart ?? el.value.length;
                const end = el.selectionEnd ?? el.value.length;
                const before = el.value.slice(0, start);
                const selection = el.value.slice(start, end);
                const after = el.value.slice(end);
                const items = selection ? selection.split(/\n+/) : ['item 1', 'item 2'];
                const body = items.map(function (line) {
                    return '[*]' + line;
                }).join('\n');
                const snippet = '[list]\n' + body + '\n[/list]';
                el.value = before + snippet + after;
                const selectStart = before.length + '[list]\n'.length;
                const selectEnd = selectStart + body.length;
                el.focus();
                el.setSelectionRange(selectStart, selectEnd);
            }
        };
    });
});
</script>
<script>
(function() {
    const key = 'fred-scroll:' + location.pathname;
    const hasHash = location.hash && location.hash.length > 1;
    if (!hasHash) {
        const saved = sessionStorage.getItem(key);
        if (saved !== null) {
            const pos = parseInt(saved, 10);
            if (!isNaN(pos)) {
                window.scrollTo(0, pos);
            }
        }
    }

    let lastWrite = 0;
    let pending = false;

    function saveScroll() {
        pending = false;
        lastWrite = performance.now();
        sessionStorage.setItem(key, String(window.scrollY || 0));
    }

    function onScroll() {
        const now = performance.now();
        if (now - lastWrite < 150) {
            return;
        }
        if (pending) {
            return;
        }
        pending = true;
        window.requestAnimationFrame(saveScroll);
    }

    window.addEventListener('scroll', onScroll, { passive: true });
})();
</script>
<script>
(function() {
    const tooltip = document.createElement('div');
    tooltip.className = 'reaction-tooltip';
    let active = null;
    let showTimer = null;
    let moveRaf = null;

    function ensureNode() {
        if (!document.body.contains(tooltip)) {
            document.body.appendChild(tooltip);
        }
    }

    function hide() {
        if (showTimer !== null) {
            clearTimeout(showTimer);
            showTimer = null;
        }
        if (moveRaf !== null) {
            window.cancelAnimationFrame(moveRaf);
            moveRaf = null;
        }
        tooltip.style.opacity = '0';
        active = null;
    }

    function getPoint(evt, el) {
        if (evt && typeof evt.clientX === 'number' && typeof evt.clientY === 'number') {
            return { x: evt.clientX, y: evt.clientY };
        }
        const rect = el.getBoundingClientRect();
        return { x: rect.left + (rect.width / 2), y: rect.top };
    }

    function position(point) {
        ensureNode();
        const padding = 8;
        const rect = tooltip.getBoundingClientRect();
        let left = Math.min(point.x + 12, window.innerWidth - rect.width - padding);
        let top = Math.min(point.y + 14, window.innerHeight - rect.height - padding);
        if (left < padding) left = padding;
        if (top < padding) top = padding;
        tooltip.style.left = left + 'px';
        tooltip.style.top = top + 'px';
    }

    function show(evt) {
        const el = evt.currentTarget;
        const text = el.getAttribute('data-tooltip');
        if (!text) return;
        const point = getPoint(evt, el);
        if (showTimer !== null) {
            clearTimeout(showTimer);
        }
        showTimer = window.setTimeout(function () {
            ensureNode();
            tooltip.textContent = text;
            active = el;
            tooltip.style.opacity = '1';
            position(point);
        }, 120);
    }

    function attach(el) {
        if (!el) return;
        el.addEventListener('mouseenter', show);
        el.addEventListener('focus', show);
        el.addEventListener('mouseleave', hide);
        el.addEventListener('blur', hide);
        el.addEventListener('mousemove', function (evt) {
            if (active !== el) return;
            if (moveRaf !== null) return;
            moveRaf = window.requestAnimationFrame(function () {
                moveRaf = null;
                position({ x: evt.clientX, y: evt.clientY });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-tooltip]').forEach(attach);
    });
    window.addEventListener('scroll', hide, { passive: true });
})();
</script>
    <script>
    (function() {
        function debounce(fn, wait) {
            let timer = null;
            return function () {
                const args = arguments;
                if (timer !== null) {
                    clearTimeout(timer);
                }
                timer = setTimeout(function () {
                    timer = null;
                    fn.apply(null, args);
                }, wait);
            };
        }

        function buildMenu(textarea) {
            const menu = document.createElement('div');
            menu.className = 'mention-suggestions';
            menu.setAttribute('role', 'listbox');
            menu.hidden = true;
            textarea.insertAdjacentElement('afterend', menu);
            return menu;
        }

        function setupMention(textarea) {
            const endpoint = textarea.getAttribute('data-mention-endpoint');
            if (!endpoint) return;

            const menu = buildMenu(textarea);
            let activeToken = null;

            function hide() {
                menu.hidden = true;
                menu.innerHTML = '';
            }

            function insertHandle(username) {
                if (!activeToken) return;
                const before = textarea.value.slice(0, activeToken.start);
                const after = textarea.value.slice(activeToken.end);
                const insertion = '@' + username + ' ';
                textarea.value = before + insertion + after;
                const next = (before + insertion).length;
                textarea.focus();
                textarea.setSelectionRange(next, next);
                hide();
            }

            function render(list) {
                if (!Array.isArray(list) || list.length === 0) {
                    hide();
                    return;
                }

                menu.innerHTML = '';
                list.slice(0, 8).forEach(function (entry, index) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'mention-suggestion';
                    let label = '@' + entry.username;
                    if (entry.display_name && entry.display_name !== entry.username) {
                        label += ' · ' + entry.display_name;
                    }
                    btn.textContent = label;
                    btn.setAttribute('role', 'option');
                    btn.setAttribute('data-index', String(index));
                    btn.addEventListener('click', function () {
                        insertHandle(entry.username);
                    });
                    menu.appendChild(btn);
                });

                menu.hidden = false;
            }

            const requestSuggestions = debounce(function (query, tokenKey) {
                fetch(endpoint + '?q=' + encodeURIComponent(query), {
                    headers: {'Accept': 'application/json'},
                }).then(function (response) {
                    if (!response.ok) return [];
                    return response.json();
                }).then(function (payload) {
                    if (!activeToken || activeToken.key !== tokenKey) return;
                    render(payload || []);
                }).catch(function () {
                    hide();
                });
            }, 140);

            function detectToken() {
                let caret = textarea.selectionStart;
                if (typeof caret !== 'number') {
                    caret = textarea.value.length;
                }

                const before = textarea.value.slice(0, caret);
                const at = before.lastIndexOf('@');
                if (at === -1) {
                    return null;
                }

                const prev = at === 0 ? ' ' : before.charAt(at - 1);
                if (!/\s|\(|\[|>/.test(prev)) {
                    return null;
                }

                const fragment = before.slice(at + 1);
                if (fragment.length < 2 || !/^[A-Za-z0-9_.-]+$/.test(fragment)) {
                    return null;
                }

                return {
                    start: at,
                    end: caret,
                    query: fragment,
                    key: at + ':' + caret,
                };
            }

            textarea.addEventListener('input', function () {
                const token = detectToken();
                if (!token) {
                    activeToken = null;
                    hide();
                    return;
                }

                activeToken = token;
                requestSuggestions(token.query, token.key);
            });

            textarea.addEventListener('blur', function () {
                setTimeout(hide, 150);
            });

            textarea.addEventListener('keydown', function (evt) {
                if (evt.key === 'Escape') {
                    hide();
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('textarea[data-mention-endpoint]').forEach(setupMention);
        });
    })();
    </script>
</body>
</html>
