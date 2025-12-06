<?php
/** @var array<int, array{title: string, items: array<int, array{label: string, href: string}>}>|null $navSections */
/** @var string|null $activePath */

$sections = $navSections ?? [];
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
                <td class="table-heading"><?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>
                    <ul class="nav-list">
                        <?php foreach ($section['items'] as $item):
                            $label = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
                            $href = $item['href'] ?? '#';
                            $isActive = $activePath !== null && $href !== '#' && $href === $activePath;
                            ?>
                            <li>
                                <?php if ($isActive): ?>
                                    <strong><?= $label ?></strong>
                                <?php else: ?>
                                    <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"><?= $label ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>
