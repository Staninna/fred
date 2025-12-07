<?php
/** @var array<int, array{title: string, items: array<int, array{label: string, href: string}>}>|null $navSections */
/** @var string|null $activePath */
/** @var callable(string, int): string $e */
/** @var \Fred\Application\Auth\CurrentUser|null $currentUser */
/** @var \Fred\Domain\Community\Community|null $currentCommunity */

$sections = $navSections ?? [];

if (isset($currentUser, $currentCommunity) && $currentUser !== null && $currentCommunity !== null && $currentUser->isAuthenticated()) {
    $sections = array_merge([[
        'title' => 'You',
        'items' => [
            ['label' => 'Profile', 'href' => '/c/' . $currentCommunity->slug . '/u/' . $currentUser->username],
            ['label' => 'Settings', 'href' => '/c/' . $currentCommunity->slug . '/settings/profile'],
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
</table>
