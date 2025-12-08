<?php
/** @var array<int, array<string, mixed>> $items */
/** @var string $emptyMessage */
/** @var string $type */
/** @var Community $community */
/** @var callable(string, ?int=): string $e */

use Fred\Domain\Community\Community;

?>

<?php if ($items === []): ?>
    <tr><td class="muted"><?= $e($emptyMessage) ?></td></tr>
<?php else: ?>
    <?php foreach ($items as $row): ?>
        <tr>
            <td>
                <?php if ($type === 'thread'): ?>
                    <div><a href="/c/<?= $e($community->slug) ?>/t/<?= (int) $row['thread_id'] ?>"><?= $e($row['title']) ?></a></div>
                <?php else: ?>
                    <div>
                        <a href="/c/<?= $e($community->slug) ?>/t/<?= (int) $row['thread_id'] ?>#post-<?= (int) $row['post_id'] ?>">
                            <?= $e($row['thread_title']) ?> · Post #<?= (int) $row['post_id'] ?>
                        </a>
                    </div>
                <?php endif; ?>

                <div class="small">
                    Board: <a href="/c/<?= $e($community->slug) ?>/b/<?= $e($row['board_slug']) ?>"><?= $e($row['board_name']) ?></a>
                    · Author: <?= $e($row['author_name']) ?>
                    · <?= date('Y-m-d H:i', (int) $row['created_at']) ?>
                </div>
                <?php if (!empty($row['snippet'] ?? '')): ?>
                    <div class="small muted"><?= $e($row['snippet']) ?></div>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
