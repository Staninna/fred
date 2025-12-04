<?php
/** @var array $errors */
/** @var array $old */
?>

<article class="card card--compact">
    <p class="eyebrow">Account</p>
    <h1>Create account</h1>
    <p class="lede">Register to post and reply. New accounts get the Member role; Guests stay read-only.</p>

    <?php if (!empty($errors)): ?>
        <div class="alert">
            <ul class="list">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form class="form" method="post" action="/register" novalidate>
        <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" autocomplete="username" value="<?= htmlspecialchars($old['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="field">
            <label for="display_name">Display name</label>
            <input id="display_name" name="display_name" type="text" value="<?= htmlspecialchars($old['display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Optional; defaults to username">
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>
        </div>
        <div class="field">
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        </div>
        <div>
            <button class="button" type="submit">Create account</button>
        </div>
    </form>
</article>
