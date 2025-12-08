<?php
/** @var \Fred\Domain\Community\Community $community */
/** @var \Fred\Domain\Auth\Profile|null $profile */
/** @var array<int, string> $avatarErrors */
/** @var callable(string, int): string $e */
/** @var callable(string, array): string $renderPartial */
?>
<?= $renderPartial('partials/form_section_header.php', ['title' => 'Avatar', 'errors' => $avatarErrors ?? [], 'infoText' => null]) ?>
            <form method="post" action="/c/<?= $e($community->slug) ?>/settings/avatar" enctype="multipart/form-data" novalidate>
                <?= $renderPartial('partials/csrf.php') ?>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="120">Current avatar</td>
                        <td>
                            <?= $renderPartial('partials/avatar_display.php', ['profile' => $profile]) ?>
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
