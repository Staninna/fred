<?php
/** @var \Fred\Domain\Community\Community $community */
/** @var array<int, \Fred\Domain\Community\Category> $categories */
/** @var array<int, array<int, \Fred\Domain\Community\Board>> $boardsByCategory */
?>

<article class="card card--hero">
    <div>
        <p class="eyebrow">Community</p>
        <h1><?= htmlspecialchars($community->name, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="lede"><?= htmlspecialchars($community->description, ENT_QUOTES, 'UTF-8') ?></p>
        <div class="tags">
            <span class="tag">Slug: <?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>
    <div class="status">
        <div class="status__item">
            <div class="status__label">Categories</div>
            <div class="status__value"><?= count($categories) ?></div>
        </div>
        <div class="status__item">
            <div class="status__label">Boards</div>
            <div class="status__value"><?= array_sum(array_map('count', $boardsByCategory)) ?></div>
        </div>
    </div>
</article>

<?php if ($categories === []): ?>
    <article class="card card--compact">
        <h2>Nothing here yet</h2>
        <p class="muted">No categories or boards. Head to the admin panel to create structure.</p>
        <a class="button" href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/admin/structure">Open admin</a>
    </article>
<?php else: ?>
    <?php foreach ($categories as $category): ?>
        <article class="card">
            <header class="card__header">
                <div>
                    <p class="eyebrow">Category</p>
                    <h2><?= htmlspecialchars($category->name, ENT_QUOTES, 'UTF-8') ?></h2>
                </div>
            </header>
            <?php $categoryBoards = $boardsByCategory[$category->id] ?? []; ?>
            <?php if ($categoryBoards === []): ?>
                <p class="muted">No boards in this category yet.</p>
            <?php else: ?>
                <ul class="list">
                    <?php foreach ($categoryBoards as $board): ?>
                        <li>
                            <div class="nav__title"><?= htmlspecialchars($board->name, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="nav__subtitle"><?= htmlspecialchars($board->description, ENT_QUOTES, 'UTF-8') ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
