<?php
/** @var array<int, string> $errors */
/** @var array<string, string> $old */
/** @var callable(string, array): string $renderPartial */
?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Create account</th>
    </tr>
    <tr>
        <td>
            <div class="info-line">Register to post and reply. New accounts get the Member role; Guests stay read-only.</div>
            <?= $renderPartial('partials/errors.php', ['errors' => $errors]) ?>
            <form method="post" action="/register" novalidate>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="140"><label for="username">Username</label></td>
                        <td><input id="username" name="username" type="text" autocomplete="username" value="<?= htmlspecialchars($old['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></td>
                    </tr>
                    <tr>
                        <td><label for="display_name">Display name</label></td>
                        <td><input id="display_name" name="display_name" type="text" value="<?= htmlspecialchars($old['display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Optional; defaults to username"></td>
                    </tr>
                    <tr>
                        <td><label for="password">Password</label></td>
                        <td><input id="password" name="password" type="password" autocomplete="new-password" required></td>
                    </tr>
                    <tr>
                        <td><label for="password_confirmation">Confirm password</label></td>
                        <td><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></td>
                    </tr>
                </table>
                <button class="button" type="submit">Create account</button>
            </form>
        </td>
    </tr>
</table>
