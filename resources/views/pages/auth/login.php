<?php
/** @var array<int, string> $errors */
/** @var array<string, string> $old */
/** @var callable(string, array): string $renderPartial */
/** @var callable(string, int): string $e */
?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Sign in</th>
    </tr>
    <tr>
        <td>
            <div class="info-line">Access member-only actions. New here? <a href="/register">Create an account</a>.</div>
            <?= $renderPartial('partials/errors.php', ['errors' => $errors]) ?>
            <form method="post" action="/login" novalidate>
                <?= $renderPartial('partials/csrf.php') ?>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="140"><label for="username">Username</label></td>
                        <td><input id="username" name="username" type="text" autocomplete="username" value="<?= $e($old['username'] ?? '') ?>" required></td>
                    </tr>
                    <tr>
                        <td><label for="password">Password</label></td>
                        <td><input id="password" name="password" type="password" autocomplete="current-password" required></td>
                    </tr>
                </table>
                <button class="button" type="submit">Sign in</button>
            </form>
        </td>
    </tr>
</table>
