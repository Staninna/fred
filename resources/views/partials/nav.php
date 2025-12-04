<?php
/** @var array|null $navSections */
/** @var string|null $activePath */

$sections = $navSections;

if ($sections === null) {
    $sections = [
        [
            'title' => 'Communities',
            'items' => [
                ['label' => 'Main Plaza', 'href' => '#'],
                ['label' => 'Retro PC', 'href' => '#'],
                ['label' => 'Design Lab', 'href' => '#'],
            ],
        ],
        [
            'title' => 'Boards',
            'items' => [
                ['label' => 'Announcements', 'href' => '#'],
                ['label' => 'General Chat', 'href' => '#'],
                ['label' => 'Help Desk', 'href' => '#'],
            ],
        ],
    ];
}
?>

<nav class="nav">
    <div class="nav__brand">
        <div class="nav__logo">F</div>
        <div>
            <div class="nav__title">Fred</div>
            <div class="nav__subtitle">Multi-community forum</div>
        </div>
    </div>
    <?php foreach ($sections as $section): ?>
        <div class="nav__section">
            <div class="nav__section-title"><?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?></div>
            <ul class="nav__list">
                <?php foreach ($section['items'] as $item):
                    $label = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
                    $href = $item['href'] ?? '#';
                    $isActive = $activePath !== null && $href !== '#' && $href === $activePath;
                    ?>
                    <li>
                        <a class="nav__link<?= $isActive ? ' nav__link--active' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"><?= $label ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
</nav>
