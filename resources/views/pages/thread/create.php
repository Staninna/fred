<?php
/** @var \Fred\Domain\Community\Community $community */
/** @var \Fred\Domain\Community\Board $board */
/** @var array<int, string> $errors */
/** @var array<string, string> $old */
/** @var callable(string, array): string $renderPartial */
?>

<article class="card card--hero">
    <div>
        <p class="eyebrow">New thread</p>
        <h1><?= htmlspecialchars($board->name, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="lede">Start a discussion in <?= htmlspecialchars($community->name, ENT_QUOTES, 'UTF-8') ?>.</p>
        <div class="tags">
            <span class="tag">Community: <?= htmlspecialchars($community->name, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="tag">Board ID: <?= $board->id ?></span>
        </div>
    </div>
</article>

<article class="card">
    <h2>Create thread</h2>
    <?= $renderPartial('partials/errors.php', ['errors' => $errors]) ?>
    <form class="form" method="post" action="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/b/<?= $board->id ?>/thread" novalidate>
        <div class="field">
            <label for="title">Title</label>
            <input id="title" name="title" type="text" value="<?= htmlspecialchars($old['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="field">
            <label for="body">Body</label>
            <textarea id="body" name="body" rows="6" required><?= htmlspecialchars($old['body'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <div class="account__actions">
            <button class="button" type="submit">Post thread</button>
            <a class="button button--ghost" href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/b/<?= $board->id ?>">Cancel</a>
        </div>
    </form>
</article>
