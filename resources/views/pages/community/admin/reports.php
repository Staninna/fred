<?php
/** @var Community $community */
/** @var array<int, array{report:Report, reporter_username:string, post_author_username:string, thread_id:int, thread_title:string}> $reports */
/** @var string $status */
/** @var callable $renderPartial */
/** @var callable $e */

use Fred\Domain\Community\Community;
use Fred\Domain\Moderation\Report;

?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Post reports · <?= $e($community->name) ?></th>
    </tr>
    <tr>
        <td class="table-heading" width="180">Filter</td>
        <td>
            <a class="button" href="/c/<?= $e($community->slug) ?>/admin/reports?status=open">Open</a>
            <a class="button" href="/c/<?= $e($community->slug) ?>/admin/reports?status=closed">Closed</a>
            <a class="button" href="/c/<?= $e($community->slug) ?>/admin/reports?status=all">All</a>
        </td>
    </tr>
</table>

<?php if ($reports === []): ?>
    <div class="notice">No reports found for this filter.</div>
<?php else: ?>
    <table class="section-table" cellpadding="0" cellspacing="0">
        <tr>
            <th>ID</th>
            <th>Post</th>
            <th>Reporter</th>
            <th>Reason</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        <?php foreach ($reports as $row): ?>
            <?php $report = $row['report']; ?>
            <tr>
                <td>#<?= $report->id ?></td>
                <td>
                    <div><a href="/c/<?= $e($community->slug) ?>/t/<?= $row['thread_id'] ?>#post-<?= $report->postId ?>">Post <?= $report->postId ?></a></div>
                    <div class="small muted">Thread: <?= $e($row['thread_title']) ?></div>
                    <div class="small muted">Author: <?= $e($row['post_author_username']) ?></div>
                </td>
                <td>
                    <div><?= $e($row['reporter_username']) ?></div>
                    <div class="small muted"><?= date('Y-m-d H:i', $report->createdAt) ?></div>
                </td>
                <td><?= nl2br($e($report->reason)) ?></td>
                <td><?= $e(ucfirst($report->status)) ?></td>
                <td>
                    <?php if ($report->status === 'open'): ?>
                        <form method="post" action="/c/<?= $e($community->slug) ?>/admin/reports/<?= $report->id ?>/resolve">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                            <button class="button" type="submit">Mark resolved</button>
                        </form>
                    <?php else: ?>
                        <span class="small muted">Closed <?= date('Y-m-d', $report->updatedAt) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
