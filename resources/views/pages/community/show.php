<?php
/** @var Community $community */
/** @var array<int, Category> $categories */
/** @var array<int, array<int, Board>> $boardsByCategory */

use Fred\Domain\Community\Board;
use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;

?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2"><?= htmlspecialchars($community->name, ENT_QUOTES, 'UTF-8') ?></th>
    </tr>
    <tr>
        <td class="table-heading">Description</td>
        <td><?= htmlspecialchars($community->description, ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <tr>
        <td class="table-heading">Slug</td>
        <td><?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <tr>
        <td class="table-heading">Stats</td>
        <td>Categories: <?= count($categories) ?> · Boards: <?= array_sum(array_map('count', $boardsByCategory)) ?></td>
    </tr>
    <?php if (($currentUser ?? null) !== null && $currentUser->isAuthenticated()): ?>
        <tr>
            <td class="table-heading">Admin</td>
            <td><a class="button" href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/admin/structure">Manage structure</a></td>
        </tr>
    <?php endif; ?>
</table>

<?php if ($categories === []): ?>
    <div class="notice">No categories or boards. Visit the admin panel to create structure.</div>
    <a class="button" href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/admin/structure">Open admin</a>
<?php else: ?>
    <?php foreach ($categories as $category): ?>
        <?php $categoryBoards = $boardsByCategory[$category->id] ?? []; ?>
        <table class="section-table" cellpadding="0" cellspacing="0" id="category-<?= $category->id ?>">
            <tr>
                <th colspan="2">Category: <?= htmlspecialchars($category->name, ENT_QUOTES, 'UTF-8') ?></th>
            </tr>
            <?php if ($categoryBoards === []): ?>
                <tr>
                    <td colspan="2" class="muted">No boards in this category yet.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($categoryBoards as $board): ?>
                    <tr>
                        <td width="240">
                            <a href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/b/<?= htmlspecialchars($board->slug, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($board->name, ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($board->description, ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    <?php endforeach; ?>
<?php endif; ?>
