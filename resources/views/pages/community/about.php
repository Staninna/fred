<?php
/** @var \Fred\Domain\Community\Community $community */
/** @var array<int, \Fred\Domain\Community\Category> $categories */
/** @var array<int, array<int, \Fred\Domain\Community\Board>> $boardsByCategory */
/** @var callable(string, ?int=): string $e */
?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">About <?= $e($community->name) ?></th>
    </tr>
    <tr>
        <td class="table-heading">Description</td>
        <td><?= $community->description !== '' ? $e($community->description) : 'No description provided.' ?></td>
    </tr>
    <tr>
        <td class="table-heading">Slug</td>
        <td><?= $e($community->slug) ?></td>
    </tr>
    <tr>
        <td class="table-heading">Created</td>
        <td><?= date('Y-m-d H:i', $community->createdAt) ?></td>
    </tr>
    <tr>
        <td class="table-heading">Structure</td>
        <td>
            <?php if ($categories === []): ?>
                <span class="muted">No categories yet.</span>
            <?php else: ?>
                <ul>
                    <?php foreach ($categories as $category): ?>
                        <li>
                            <strong><?= $e($category->name) ?></strong>
                            <?php $boards = $boardsByCategory[$category->id] ?? []; ?>
                            <?php if ($boards === []): ?>
                                <span class="muted">(no boards)</span>
                            <?php else: ?>
                                <ul>
                                    <?php foreach ($boards as $board): ?>
                                        <li><?= $e($board->name) ?> (slug: <?= $e($board->slug) ?>)</li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </td>
    </tr>
</table>
