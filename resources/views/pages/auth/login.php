<?php
/** @var array $errors */
/** @var array $old */
?>

<article class="card card--compact">
    <p class="eyebrow">Account</p>
    <h1>Sign in</h1>
    <p class="lede">Access member-only actions. New here? <a href="/register">Create an account</a>.</p>

    <?php if (!empty($errors)): ?>
        <div class="alert">
            <ul class="list">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

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
