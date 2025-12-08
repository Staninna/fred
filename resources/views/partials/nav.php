<?php
/** @var array<int, array{title: string, items: array<int, array{label: string, href: string}>}>|null $navSections */
/** @var string|null $activePath */
/** @var callable(string, ?int=): string $e */
/** @var \Fred\Application\Auth\CurrentUser|null $currentUser */
/** @var \Fred\Domain\Community\Community|null $currentCommunity */
/** @var int|null $mentionUnreadCount */

$sections = $navSections ?? [];
$mentionUnreadCount = (int) ($mentionUnreadCount ?? 0);

$history = $_SESSION['nav_history'] ?? [];
$index = $_SESSION['nav_index'] ?? (is_countable($history) && $history !== [] ? count($history) - 1 : -1);
$backHref = is_array($history) && $index > 0 ? '/nav/back' : null;
$forwardHref = is_array($history) && $index >= 0 && $index < count($history) - 1 ? '/nav/forward' : null;
$hasHistoryControls = $backHref !== null || $forwardHref !== null;

if (isset($currentUser, $currentCommunity) && $currentUser !== null && $currentCommunity !== null && $currentUser->isAuthenticated()) {
    $sections = array_merge([[
        'title' => 'You',
        'items' => [
            ['label' => 'Profile', 'href' => '/c/' . $currentCommunity->slug . '/u/' . $currentUser->username],
            ['label' => $mentionUnreadCount > 0 ? 'Mentions (' . $mentionUnreadCount . ')' : 'Mentions', 'href' => '/c/' . $currentCommunity->slug . '/mentions'],
        ],
    ]], $sections);
}
?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Navigation</th>
    </tr>
    <?php if ($sections === []): ?>
        <tr>
            <td>
                <div class="info-line">Navigation will appear once communities and boards are available.</div>
            </td>
        </tr>
    <?php else: ?>
        <?php foreach ($sections as $section): ?>
            <tr>
                <td class="table-heading"><?= $e($section['title']) ?></td>
            </tr>
            <tr>
                <td>
                    <ul class="nav-list">
                        <?php foreach ($section['items'] as $item):
                            $label = $e($item['label']);
                            $href = $item['href'] ?? '#';
                            $isActive = $activePath !== null && $href !== '#' && $href === $activePath;
                            ?>
                            <li>
                                <?php if ($isActive): ?>
                                    <strong><?= $label ?></strong>
                                <?php else: ?>
                                    <a href="<?= $e($href) ?>"><?= $label ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($hasHistoryControls): ?>
        <tr>
            <td>
                <div class="nav-history">
                    <?php if ($backHref !== null): ?>
                        <a class="button" href="<?= $e($backHref) ?>">&laquo; Back</a>
                    <?php endif; ?>
                    <?php if ($forwardHref !== null): ?>
                        <a class="button" href="<?= $e($forwardHref) ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    <?php endif; ?>
</table>
