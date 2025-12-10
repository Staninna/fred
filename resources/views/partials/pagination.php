<?php
/** @var int $page */
/** @var int $totalPages */
/** @var string $baseUrl */
/** @var bool $isTable */
/** @var callable $e */
?>
<?php if ($totalPages > 1): ?>
    <?php $containerIsTable = $isTable; ?>
    <?php if ($containerIsTable): ?>
        <tr>
            <td colspan="2" class="pagination">
    <?php else: ?>
        <div class="pagination" role="navigation" aria-label="Pagination">
    <?php endif; ?>
        <nav class="pagination-nav" aria-label="Pagination">
            <?php if ($page > 1): ?>
                <a class="button" href="<?= $e($baseUrl) ?>?page=1" aria-label="Go to first page">First</a>
                <a class="button" href="<?= $e($baseUrl) ?>?page=<?= $page - 1 ?>" aria-label="Go to page <?= $page - 1 ?>">Prev</a>
            <?php else: ?>
                <span class="muted" aria-disabled="true">First</span>
                <span class="muted" aria-disabled="true">Prev</span>
            <?php endif; ?>
            <span class="muted" aria-live="polite" aria-atomic="true">Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a class="button" href="<?= $e($baseUrl) ?>?page=<?= $page + 1 ?>" aria-label="Go to page <?= $page + 1 ?>">Next</a>
                <a class="button" href="<?= $e($baseUrl) ?>?page=<?= $totalPages ?>" aria-label="Go to last page (<?= $totalPages ?>)">Last</a>
            <?php else: ?>
                <span class="muted" aria-disabled="true">Next</span>
                <span class="muted" aria-disabled="true">Last</span>
            <?php endif; ?>
        </nav>
    <?php if ($containerIsTable): ?>
            </td>
        </tr>
    <?php else: ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
