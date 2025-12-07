<?php
/** @var \Fred\Domain\Community\Community $community */
/** @var \Fred\Domain\Auth\Profile|null $profile */
/** @var array<int, string> $avatarErrors */
/** @var callable(string, int): string $e */
?>
<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Avatar</th>
    </tr>
    <tr>
        <td>
            <?= $renderPartial('partials/errors.php', ['errors' => $avatarErrors ?? []]) ?>
            <form method="post" action="/c/<?= $e($community->slug) ?>/settings/avatar" enctype="multipart/form-data" novalidate>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="120">Current avatar</td>
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
            </form>
        </td>
    </tr>
</table>
