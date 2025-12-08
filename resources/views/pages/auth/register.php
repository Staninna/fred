<?php
/** @var array<int, string> $errors */
/** @var array<string, string> $old */
/** @var callable(string, array): string $renderPartial */
/** @var callable(string, ?int=): string $e */
/** @var string|null $success */

$messageIdPrefix = 'register-form';
$messageTargets = [];

if (!empty($errors)) {
    $messageTargets[] = $messageIdPrefix . '-errors';
}

if (!empty($success ?? '')) {
    $messageTargets[] = $messageIdPrefix . '-success';
}
$messageAria = $messageTargets === [] ? '' : ' aria-describedby="' . $e(implode(' ', $messageTargets)) . '"';
?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Create account</th>
    </tr>
    <tr>
        <td>
            <div class="info-line">Register to post and reply. New accounts get the Member role; Guests stay read-only.</div>
            <?= $renderPartial('partials/errors.php', [
                'errors' => $errors,
                'success' => $success ?? null,
                'idPrefix' => $messageIdPrefix,
            ]) ?>
            <form method="post" action="/register" novalidate>
                <?= $renderPartial('partials/csrf.php') ?>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="140"><label for="username">Username</label></td>
                        <td><input id="username" name="username" type="text" autocomplete="username" value="<?= $e($old['username'] ?? '') ?>" required<?= $messageAria ?>></td>
                    </tr>
                    <tr>
                        <td><label for="display_name">Display name</label></td>
                        <td><input id="display_name" name="display_name" type="text" value="<?= $e($old['display_name'] ?? '') ?>" placeholder="Optional; defaults to username"<?= $messageAria ?>></td>
                    </tr>
                    <tr>
                        <td><label for="password">Password</label></td>
                        <td><input id="password" name="password" type="password" autocomplete="new-password" required<?= $messageAria ?>></td>
                    </tr>
                    <tr>
                        <td><label for="password_confirmation">Confirm password</label></td>
                        <td><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required<?= $messageAria ?>></td>
                    </tr>
                </table>
                <button class="button" type="submit">Create account</button>
            </form>
        </td>
    </tr>
</table>
