<?php
/** @var \Fred\Domain\Community\Community $community */
/** @var array<int, \Fred\Domain\Community\Category> $categories */
/** @var array<int, array<int, \Fred\Domain\Community\Board>> $boardsByCategory */
/** @var array<int, string> $errors */
?>

<article class="card card--compact">
    <p class="eyebrow">Structure</p>
    <h1><?= htmlspecialchars($community->name, ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="lede">Manage categories and boards for this community.</p>
    <?= $renderPartial('partials/errors.php', ['errors' => $errors]) ?>
</article>

<section class="grid">
    <article class="card">
        <h2>Categories</h2>
        <form class="form" method="post" action="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/admin/categories" novalidate>
            <div class="field">
                <label for="category_name">Name</label>
                <input id="category_name" name="name" type="text" required>
            </div>
            <div class="field">
                <label for="category_position">Position</label>
                <input id="category_position" name="position" type="number" value="0">
            </div>
            <div>
                <button class="button" type="submit">Add category</button>
            </div>
        </form>

        <?php if ($categories === []): ?>
            <p class="muted">No categories yet.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($categories as $category): ?>
                    <li>
                        <form class="form" method="post" action="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/admin/categories/<?= $category->id ?>" novalidate>
                            <div class="field">
                                <label>Name</label>
                                <input name="name" type="text" value="<?= htmlspecialchars($category->name, ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="field">
                                <label>Position</label>
                                <input name="position" type="number" value="<?= $category->position ?>">
                            </div>
                            <div class="account__actions">
                                <button class="button button--ghost" type="submit">Update</button>
                                <button class="button" type="submit" formaction="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/admin/categories/<?= $category->id ?>/delete">Delete</button>
                            </div>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
    <article class="card">
        <h2>Boards</h2>
        <?php if ($categories === []): ?>
            <p class="muted">Create a category first to add boards.</p>
        <?php else: ?>
            <form class="form" method="post" action="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/admin/boards" novalidate>
                <div class="field">
                    <label for="board_name">Name</label>
                    <input id="board_name" name="name" type="text" required>
                </div>
                <div class="field">
                    <label for="board_description">Description</label>
                    <input id="board_description" name="description" type="text">
                </div>
                <div class="field">
                    <label for="board_position">Position</label>
                    <input id="board_position" name="position" type="number" value="0">
                </div>
                <div class="field">
                    <label for="board_category_id">Category</label>
                    <select id="board_category_id" name="category_id">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category->id ?>"><?= htmlspecialchars($category->name, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>
                        <input type="checkbox" name="is_locked" value="1"> Locked
                    </label>
                </div>
                <div>
                    <button class="button" type="submit">Add board</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($boardsByCategory === []): ?>
            <p class="muted">No boards yet.</p>
        <?php else: ?>
            <?php foreach ($categories as $category): ?>
                <?php $categoryBoards = $boardsByCategory[$category->id] ?? []; ?>
                <?php if ($categoryBoards === []): continue; endif; ?>
                <div class="card card--compact">
                    <div class="card__header">
                        <div>
                            <p class="eyebrow">Category</p>
                            <h3><?= htmlspecialchars($category->name, ENT_QUOTES, 'UTF-8') ?></h3>
                        </div>
                    </div>
                    <ul class="list">
                        <?php foreach ($categoryBoards as $board): ?>
                            <li>
                                <form class="form" method="post" action="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/admin/boards/<?= $board->id ?>" novalidate>
                                    <div class="field">
                                        <label>Name</label>
                                        <input name="name" type="text" value="<?= htmlspecialchars($board->name, ENT_QUOTES, 'UTF-8') ?>" required>
                                    </div>
                                    <div class="field">
                                        <label>Description</label>
                                        <input name="description" type="text" value="<?= htmlspecialchars($board->description, ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                    <div class="field">
                                        <label>Position</label>
                                        <input name="position" type="number" value="<?= $board->position ?>">
                                    </div>
                                    <div class="field">
                                        <label>
                                            <input type="checkbox" name="is_locked" value="1"<?= $board->isLocked ? ' checked' : '' ?>> Locked
                                        </label>
                                    </div>
                                    <div class="account__actions">
                                        <button class="button button--ghost" type="submit">Update</button>
                                        <button class="button" type="submit" formaction="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/admin/boards/<?= $board->id ?>/delete">Delete</button>
                                    </div>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </article>
</section>
