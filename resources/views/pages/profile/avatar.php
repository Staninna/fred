<?php
/** @var \Fred\Domain\Auth\Profile|null $profile */
/** @var \Fred\Domain\Community\Community $community */
/** @var \Fred\Application\Auth\CurrentUser|null $currentUser */
/** @var array<int, string> $errors */
/** @var callable(string, array): string $renderPartial */
/** @var callable(string, int): string $e */
?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Edit avatar</th>
    </tr>
    <tr>
        <td>
            <div class="info-line">Upload an image (png, jpg, gif, webp). Max 500 KB.</div>
            <?= $renderPartial('partials/errors.php', ['errors' => $errors]) ?>
            <form method="post" action="/c/<?= $e($community->slug) ?>/settings/avatar" enctype="multipart/form-data" novalidate>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="140">Current avatar</td>
                        <td>
                            <?php if (!empty($profile?->avatarPath ?? '')): ?>
                                <img src="/uploads/<?= $e($profile->avatarPath) ?>" alt="Avatar" style="max-width: 120px; max-height: 120px;">
                            <?php else: ?>
                                <span class="muted">No avatar set.</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="avatar">New avatar</label></td>
                        <td><input id="avatar" name="avatar" type="file" accept=".png,.jpg,.jpeg,.gif,.webp" required></td>
                    </tr>
                </table>
                <button class="button" type="submit">Upload avatar</button>
                <a class="button" href="/c/<?= $e($community->slug) ?>/u/<?= $e($currentUser?->username ?? '') ?>">Back to profile</a>
            </form>
        </td>
    </tr>
</table>
