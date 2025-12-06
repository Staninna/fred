<?php /** @var callable(string, array): string $renderPartial */ ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle ?? 'Fred Forum', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/css/layout.css">
    <script src="https://unpkg.com/alpinejs@3.13.5/dist/cdn.min.js" defer></script>
</head>
<body>
<table class="page-frame" cellspacing="0" cellpadding="0" align="center">
    <tr>
        <td class="banner" colspan="2">
            <div class="banner-title"><?= htmlspecialchars($pageTitle ?? 'Fred Forum', ENT_QUOTES, 'UTF-8') ?></div>
            <div class="banner-line">Classic forum interface. Environment: <?= htmlspecialchars($environment ?? 'local', ENT_QUOTES, 'UTF-8') ?></div>
            <div class="banner-links">
                <a href="/">Home</a> |
                <?php if (isset($currentUser) && $currentUser->isAuthenticated()): ?>
                    Signed in as <?= htmlspecialchars($currentUser->displayName, ENT_QUOTES, 'UTF-8') ?> |
                    <form class="inline-form" method="post" action="/logout">
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
            ]) ?>
        </td>
        <td class="content" valign="top" id="main-content">
            <?= $content ?? '' ?>
        </td>
    </tr>
    <tr>
        <td class="footer" colspan="2">
            Fred forum engine · <?= htmlspecialchars($environment ?? 'local', ENT_QUOTES, 'UTF-8') ?>
            <?php if (!empty($baseUrl ?? '')): ?>
                · Base URL: <?= htmlspecialchars($baseUrl ?? '', ENT_QUOTES, 'UTF-8') ?>
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
</body>
</html>
