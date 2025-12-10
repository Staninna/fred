<?php
/** @var Community $community */
/** @var array<int, Category> $categories */
/** @var CurrentUser $currentUser*/
/** @var array<int, array<int, Board>> $boardsByCategory */
/** @var callable $e */
/** @var callable $renderPartial */
/** @var bool $canModerate */

use Fred\Application\Auth\CurrentUser;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;

?>

<?= $renderPartial('partials/info_table.php', [
    'title' => $community->name,
    'fields' => [
        'Description' => $e($community->description),
        'Slug' => $e($community->slug),
        'Stats' => 'Categories: ' . count($categories) . ' · Boards: ' . array_sum(array_map('count', $boardsByCategory)),
    ],
]) ?>
    <?php if (!empty($canModerate ?? false)): ?>
        <tr>
            <td class="table-heading">Admin</td>
            <td><a class="button" href="/c/<?= $e($community->slug) ?>/admin/structure">Manage structure</a></td>
        </tr>
    <?php endif; ?>
</table>

<?php if ($categories === []): ?>
    <div class="notice">No categories or boards. Visit the admin panel to create structure.</div>
    <a class="button" href="/c/<?= $e($community->slug) ?>/admin/structure">Open admin</a>
<?php else: ?>
    <?php foreach ($categories as $category): ?>
        <?php $categoryBoards = $boardsByCategory[$category->id] ?? []; ?>
        <table class="section-table" cellpadding="0" cellspacing="0" id="category-<?= $category->id ?>">
            <tr>
                <th colspan="2">Category: <?= $e($category->name) ?></th>
            </tr>
            <?php if ($categoryBoards === []): ?>
                <tr>
                    <td colspan="2" class="muted">No boards in this category yet.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($categoryBoards as $board): ?>
                    <tr>
                        <td width="240">
                            <a href="/c/<?= $e($community->slug) ?>/b/<?= $e($board->slug) ?>">
                                <?= $e($board->name) ?>
                            </a>
                        </td>
                        <td><?= $e($board->description) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    <?php endforeach; ?>
<?php endif; ?>
