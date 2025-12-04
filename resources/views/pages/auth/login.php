<?php
/** @var array<int, string> $errors */
/** @var array<string, string> $old */
/** @var callable(string, array): string $renderPartial */
?>

<article class="card card--compact">
    <p class="eyebrow">Account</p>
    <h1>Sign in</h1>
    <p class="lede">Access member-only actions. New here? <a href="/register">Create an account</a>.</p>

    <?= $renderPartial('partials/errors.php', ['errors' => $errors]) ?>

    <form class="form" method="post" action="/login" novalidate>
        <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" autocomplete="username" value="<?= htmlspecialchars($old['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <div>
            <button class="button" type="submit">Sign in</button>
        </div>
    </form>
</article>
