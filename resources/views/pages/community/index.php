<?php
/** @var array<int, Community> $communities */
/** @var array<int, string> $errors */
/** @var array<string, string> $old */
/** @var callable(string, array): string $renderPartial */

use Fred\Domain\Community\Community;

?>

<article class="card card--hero">
    <div>
        <p class="eyebrow">Communities</p>
        <h1>Select a community</h1>
        <p class="lede">Pick where you want to explore. Create a new one to start fresh.</p>
    </div>
    <div class="status">
        <div class="status__item">
            <div class="status__label">Available</div>
            <div class="status__value"><?= count($communities) ?></div>
        </div>
        <div class="status__item">
            <div class="status__label">Environment</div>
            <div class="status__value"><?= htmlspecialchars($environment ?? '', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>
</article>

<section class="grid">
    <article class="card">
        <h2>Communities</h2>
        <?php if ($communities === []): ?>
            <p class="muted">No communities yet. Create the first one to get started.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($communities as $community): ?>
                    <li>
                        <a class="nav__link" href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($community->name, ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <div class="nav__subtitle"><?= htmlspecialchars($community->description, ENT_QUOTES, 'UTF-8') ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
    <article class="card">
        <h2>Create community</h2>
        <?= $renderPartial('partials/errors.php', ['errors' => $errors]) ?>
        <form class="form" method="post" action="/communities" novalidate>
            <div class="field">
                <label for="name">Name</label>
                <input id="name" name="name" type="text" value="<?= htmlspecialchars($old['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <div class="field">
                <label for="slug">Slug</label>
                <input id="slug" name="slug" type="text" value="<?= htmlspecialchars($old['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="auto-generated from name">
            </div>
            <div class="field">
                <label for="description">Description</label>
                <input id="description" name="description" type="text" value="<?= htmlspecialchars($old['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div>
                <button class="button" type="submit">Create</button>
            </div>
        </form>
    </article>
</section>
