<?php
/** @var int $page */
/** @var int $totalPages */
/** @var string $baseUrl */
/** @var bool $isTable */
/** @var callable(string, int): string $e */
?>
<?php if ($totalPages > 1): ?>
    <?php if ($isTable ?? false): ?>
        <tr>
            <td colspan="2" class="pagination">
    <?php else: ?>
        <div class="pagination">
    <?php endif; ?>
        <?php if ($page > 1): ?>
            <a class="button" href="<?= $e($baseUrl) ?>?page=<?= $page - 1 ?>">Prev</a>
        <?php else: ?>
            <span class="muted">Prev</span>
        <?php endif; ?>
        <span class="muted">Page <?= $page ?> of <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
            <a class="button" href="<?= $e($baseUrl) ?>?page=<?= $page + 1 ?>">Next</a>
        <?php else: ?>
            <span class="muted">Next</span>
        <?php endif; ?>
    <?php if ($isTable ?? false): ?>
            </td>
        </tr>
    <?php else: ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
