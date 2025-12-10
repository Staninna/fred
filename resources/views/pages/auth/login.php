<?php
/** @var array<int, string> $errors */
/** @var array<string, string> $old */
/** @var callable $renderPartial */
/** @var callable $e */
/** @var string|null $success */

$messageIdPrefix = 'login-form';
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
        <th>Sign in</th>
    </tr>
    <tr>
        <td>
            <div class="info-line">Access member-only actions. New here? <a href="/register">Create an account</a>.</div>
            <?= $renderPartial('partials/errors.php', [
                'errors' => $errors,
                'success' => $success ?? null,
                'idPrefix' => $messageIdPrefix,
            ]) ?>
            <form method="post" action="/login" novalidate>
                <?= $renderPartial('partials/csrf.php') ?>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="140"><label for="username">Username</label></td>
                        <td><input id="username" name="username" type="text" autocomplete="username" value="<?= $e($old['username'] ?? '') ?>" required<?= $messageAria ?>></td>
                    </tr>
                    <tr>
                        <td><label for="password">Password</label></td>
                        <td><input id="password" name="password" type="password" autocomplete="current-password" required<?= $messageAria ?>></td>
                    </tr>
                </table>
                <button class="button" type="submit">Sign in</button>
            </form>
        </td>
    </tr>
</table>
