<?php
/** @var callable(string, array): string $renderPartial */
/** @var callable(string, int): string $e */
/** @var \Fred\Domain\Community\Community|null $currentCommunity */
/** @var string|null $customCss */
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $e($pageTitle ?? 'Fred Forum') ?></title>
    <link rel="stylesheet" href="/css/layout.css">
    <?php if (!empty($customCss ?? '')): ?>
        <style id="custom-css">
            <?= $e($customCss ?? '', ENT_NOQUOTES) ?>
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
                    <?php if (isset($currentCommunity) && $currentCommunity !== null): ?>
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
            <?php if (!empty($baseUrl ?? '')): ?>
                · Base URL: <?= $e($baseUrl ?? '') ?>
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
                var el = this.target;
                if (!el) return;
                var start = el.selectionStart ?? el.value.length;
                var end = el.selectionEnd ?? el.value.length;
                var before = el.value.slice(0, start);
                var selection = el.value.slice(start, end);
                var after = el.value.slice(end);
                var open = '[' + tag + ']';
                var close = '[/' + tag + ']';
                el.value = before + open + selection + close + after;
                var cursor = start + open.length + selection.length + close.length;
                el.focus();
                el.setSelectionRange(cursor, cursor);
            },
            insertLink: function() {
                var url = prompt('Enter URL (include http/https):', 'http://');
                if (!url) return;
                var el = this.target;
                if (!el) return;
                var start = el.selectionStart ?? el.value.length;
                var end = el.selectionEnd ?? el.value.length;
                var before = el.value.slice(0, start);
                var selection = el.value.slice(start, end);
                var after = el.value.slice(end);
                var label = selection !== '' ? selection : url;
                var snippet = '[url=' + url + ']' + label + '[/url]';
                el.value = before + snippet + after;
                var cursor = before.length + snippet.length;
                el.focus();
                el.setSelectionRange(cursor, cursor);
            }
        };
    });
});
</script>
<script>
(function() {
    var key = 'fred-scroll:' + location.pathname;
    var hasHash = location.hash && location.hash.length > 1;
    if (!hasHash) {
        var saved = sessionStorage.getItem(key);
        if (saved !== null) {
            var pos = parseInt(saved, 10);
            if (!isNaN(pos)) {
                window.scrollTo(0, pos);
            }
        }
    }
    window.addEventListener('scroll', function () {
        sessionStorage.setItem(key, String(window.scrollY || 0));
    });
})();
</script>
<script>
(function() {
    var tooltip = document.createElement('div');
    tooltip.className = 'reaction-tooltip';
    var active = null;
    var showTimer = null;
    var moveRaf = null;

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
        var rect = el.getBoundingClientRect();
        return { x: rect.left + (rect.width / 2), y: rect.top };
    }

    function position(point) {
        ensureNode();
        var padding = 8;
        var rect = tooltip.getBoundingClientRect();
        var left = Math.min(point.x + 12, window.innerWidth - rect.width - padding);
        var top = Math.min(point.y + 14, window.innerHeight - rect.height - padding);
        if (left < padding) left = padding;
        if (top < padding) top = padding;
        tooltip.style.left = left + 'px';
        tooltip.style.top = top + 'px';
    }

    function show(evt) {
        var el = evt.currentTarget;
        var text = el.getAttribute('data-tooltip');
        if (!text) return;
        var point = getPoint(evt, el);
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
            var timer = null;
            return function () {
                var args = arguments;
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
            var menu = document.createElement('div');
            menu.className = 'mention-suggestions';
            menu.setAttribute('role', 'listbox');
            menu.hidden = true;
            textarea.insertAdjacentElement('afterend', menu);
            return menu;
        }

        function setupMention(textarea) {
            var endpoint = textarea.getAttribute('data-mention-endpoint');
            if (!endpoint) return;

            var menu = buildMenu(textarea);
            var activeToken = null;

            function hide() {
                menu.hidden = true;
                menu.innerHTML = '';
            }

            function insertHandle(username) {
                if (!activeToken) return;
                var before = textarea.value.slice(0, activeToken.start);
                var after = textarea.value.slice(activeToken.end);
                var insertion = '@' + username + ' ';
                textarea.value = before + insertion + after;
                var next = (before + insertion).length;
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
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'mention-suggestion';
                    var label = '@' + entry.username;
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

            var requestSuggestions = debounce(function (query, tokenKey) {
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
                var caret = textarea.selectionStart;
                if (typeof caret !== 'number') {
                    caret = textarea.value.length;
                }

                var before = textarea.value.slice(0, caret);
                var at = before.lastIndexOf('@');
                if (at === -1) {
                    return null;
                }

                var prev = at === 0 ? ' ' : before.charAt(at - 1);
                if (!/\s|\(|\[|>/.test(prev)) {
                    return null;
                }

                var fragment = before.slice(at + 1);
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
                var token = detectToken();
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
