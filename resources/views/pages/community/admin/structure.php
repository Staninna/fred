<?php
/** @var Community $community */
/** @var array<int, Category> $categories */
/** @var array<int, array<int, Board>> $boardsByCategory */
/** @var array<int, string> $errors */
/** @var callable(string, array): string $renderPartial */
/** @var callable(string, int): string $e */
/** @var array<int, array{user_id:int, username:string, assigned_at:int}> $moderators */
/** @var array<int, string> $usernames */

use Fred\Domain\Community\Board;
use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;

?>

<?php $boardTotal = array_sum(array_map('count', $boardsByCategory)); ?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Manage structure: <?= $e($community->name) ?></th>
    </tr>
    <tr>
        <td class="table-heading">Description</td>
        <td><?= $e($community->description) ?></td>
    </tr>
    <tr>
        <td class="table-heading">Note</td>
        <td>Use the forms below to add or edit categories and boards.</td>
    </tr>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Community theme (CSS)</th>
    </tr>
    <tr>
        <td colspan="2">
            <form method="post" action="/c/<?= $e($community->slug) ?>/admin/custom-css" novalidate>
                <div class="small muted">Custom CSS is injected after the base theme. Max 8000 characters.</div>
                <textarea name="custom_css" rows="6" style="width: 100%;"><?= $e($community->customCss ?? '') ?></textarea>
                <div style="margin-top:6px;">
                    <button class="button" type="submit">Save community CSS</button>
                </div>
            </form>
        </td>
    </tr>
</table>

<?= $renderPartial('partials/errors.php', ['errors' => $errors]) ?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Moderators</th>
    </tr>
    <tr>
        <td class="table-heading" width="200">Current moderators</td>
        <td>
            <?php if ($moderators === []): ?>
                <div class="muted">No moderators assigned.</div>
            <?php else: ?>
                <ul class="nav-list">
                    <?php foreach ($moderators as $moderator): ?>
                        <li>
                            <?= $e($moderator['username']) ?>
                            <span class="small muted">(since <?= date('Y-m-d', (int) $moderator['assigned_at']) ?>)</span>
                            <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/admin/moderators/<?= $moderator['user_id'] ?>/delete">
                                <button class="button" type="submit">Remove</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <td class="table-heading">Add moderator</td>
        <td>
            <form method="post" action="/c/<?= $e($community->slug) ?>/admin/moderators" novalidate>
                <label for="mod_username">Username</label>
                <?php
                $modUserOptions = array_map(static fn ($username) => ['value' => $username, 'label' => $username], $usernames);
                echo $renderPartial('partials/select.php', [
                    'name' => 'username',
                    'id' => 'mod_username',
                    'placeholder' => 'Select user',
                    'options' => $modUserOptions,
                    'selected' => '',
                ]);
                ?>
                <button class="button" type="submit">Assign</button>
                <div class="small muted">User will be given the Moderator role if they do not already have it.</div>
            </form>
        </td>
    </tr>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Add category</th>
    </tr>
    <tr>
        <td>
            <form method="post" action="/c/<?= $e($community->slug) ?>/admin/categories" novalidate>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="140"><label for="category_name">Name</label></td>
                        <td><input id="category_name" name="name" type="text" required></td>
                    </tr>
                    <tr>
                        <td><label for="category_position">Position</label></td>
                        <td><input id="category_position" name="position" type="number" value="0"></td>
                    </tr>
                </table>
                <button class="button" type="submit">Add category</button>
            </form>
        </td>
    </tr>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Existing categories</th>
    </tr>
    <?php if ($categories === []): ?>
        <tr>
            <td class="muted">No categories yet.</td>
        </tr>
    <?php else: ?>
        <?php foreach ($categories as $category): ?>
            <tr>
                <td>
                    <form method="post" action="/c/<?= $e($community->slug) ?>/admin/categories/<?= $category->id ?>" novalidate>
                        <table class="form-table" cellpadding="0" cellspacing="0">
                            <tr>
                                <td width="140">Name</td>
                                <td><input name="name" type="text" value="<?= $e($category->name) ?>" required></td>
                            </tr>
                            <tr>
                                <td>Position</td>
                                <td><input name="position" type="number" value="<?= $category->position ?>"></td>
                            </tr>
                        </table>
                        <button class="button" type="submit">Update</button>
                        <button class="button" type="submit" formaction="/c/<?= $e($community->slug) ?>/admin/categories/<?= $category->id ?>/delete">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Add board</th>
    </tr>
    <tr>
        <td>
            <?php if ($categories === []): ?>
                <div class="muted">Create a category first to add boards.</div>
            <?php else: ?>
                <form method="post" action="/c/<?= $e($community->slug) ?>/admin/boards" novalidate>
                    <table class="form-table" cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="140"><label for="board_name">Name</label></td>
                            <td><input id="board_name" name="name" type="text" required></td>
                        </tr>
                        <tr>
                            <td><label for="board_slug">Slug</label></td>
                            <td><input id="board_slug" name="slug" type="text" placeholder="auto-generated from name"></td>
                        </tr>
                        <tr>
                            <td><label for="board_description">Description</label></td>
                            <td><input id="board_description" name="description" type="text"></td>
                        </tr>
                        <tr>
                            <td><label for="board_position">Position</label></td>
                            <td><input id="board_position" name="position" type="number" value="0"></td>
                        </tr>
                        <tr>
                            <td><label for="board_category_id">Category</label></td>
                            <td>
                                <?php
                                $categoryOptions = array_map(
                                    static fn ($category) => ['value' => (string) $category->id, 'label' => $category->name],
                                    $categories
                                );
                                echo $renderPartial('partials/select.php', [
                                    'name' => 'category_id',
                                    'id' => 'board_category_id',
                                    'options' => $categoryOptions,
                                    'selected' => $categories[0]->id ?? '',
                                    'required' => true,
                                ]);
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Status</td>
                            <td><label><input type="checkbox" name="is_locked" value="1"> Locked</label></td>
                        </tr>
                        <tr>
                            <td><label for="board_custom_css">Custom CSS</label></td>
                            <td>
                                <textarea id="board_custom_css" name="custom_css" rows="3" placeholder="Optional, max 5000 characters" style="width: 100%;"></textarea>
                            </td>
                        </tr>
                    </table>
                    <button class="button" type="submit">Add board</button>
                </form>
            <?php endif; ?>
        </td>
    </tr>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Boards</th>
    </tr>
    <?php if ($boardTotal === 0): ?>
        <tr>
            <td colspan="2" class="muted">No boards yet.</td>
        </tr>
    <?php else: ?>
        <?php foreach ($categories as $category): ?>
            <?php $categoryBoards = $boardsByCategory[$category->id] ?? []; ?>
            <?php if ($categoryBoards === []): continue; endif; ?>
            <tr>
                <td colspan="2" class="table-heading">Category: <?= $e($category->name) ?></td>
            </tr>
            <?php foreach ($categoryBoards as $board): ?>
                <tr>
                    <td colspan="2">
                        <form method="post" action="/c/<?= $e($community->slug) ?>/admin/boards/<?= $board->id ?>" novalidate>
                            <table class="form-table" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="140">Name</td>
                                    <td><input name="name" type="text" value="<?= $e($board->name) ?>" required></td>
                                </tr>
                                <tr>
                                    <td>Slug</td>
                                    <td><input name="slug" type="text" value="<?= $e($board->slug) ?>" required></td>
                                </tr>
                                <tr>
                                    <td>Description</td>
                                    <td><input name="description" type="text" value="<?= $e($board->description) ?>"></td>
                                </tr>
                                <tr>
                                    <td>Position</td>
                                    <td><input name="position" type="number" value="<?= $board->position ?>"></td>
                                </tr>
                                <tr>
                                    <td>Status</td>
                                    <td><label><input type="checkbox" name="is_locked" value="1"<?= $board->isLocked ? ' checked' : '' ?>> Locked</label></td>
                                </tr>
                                <tr>
                                    <td>Custom CSS</td>
                                    <td><textarea name="custom_css" rows="3" style="width: 100%;"><?= $e($board->customCss ?? '') ?></textarea></td>
                                </tr>
                            </table>
                            <button class="button" type="submit">Update</button>
                            <button class="button" type="submit" formaction="/c/<?= $e($community->slug) ?>/admin/boards/<?= $board->id ?>/delete">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</table>
