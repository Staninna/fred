<?php
/** @var callable(string, int): string $e */
/** @var string $action */
/** @var string $submitLabel */
/** @var \Fred\Domain\Community\Board|null $board */
/** @var array<int, \Fred\Domain\Community\Category> $categories */
/** @var bool $includeCategorySelect */
/** @var string|null $deleteAction */

$name = $board?->name ?? '';
$slug = $board?->slug ?? '';
$description = $board?->description ?? '';
$position = $board?->position ?? 0;
$isLocked = $board?->isLocked ?? false;
$customCss = $board?->customCss ?? '';
$selectedCategoryId = $board?->categoryId ?? ($categories[0]->id ?? '');
?>
<form method="post" action="<?= $e($action) ?>" novalidate>
    <table class="form-table" cellpadding="0" cellspacing="0">
        <tr>
            <td width="140"><label for="board_name_<?= $e($slug ?: 'new') ?>">Name</label></td>
            <td><input id="board_name_<?= $e($slug ?: 'new') ?>" name="name" type="text" value="<?= $e($name) ?>" required></td>
        </tr>
        <tr>
            <td><label for="board_slug_<?= $e($slug ?: 'new') ?>">Slug</label></td>
            <td><input id="board_slug_<?= $e($slug ?: 'new') ?>" name="slug" type="text" value="<?= $e($slug) ?>" placeholder="<?= $board === null ? 'auto-generated from name' : '' ?>"></td>
        </tr>
        <tr>
            <td><label for="board_description_<?= $e($slug ?: 'new') ?>">Description</label></td>
            <td><input id="board_description_<?= $e($slug ?: 'new') ?>" name="description" type="text" value="<?= $e($description) ?>"></td>
        </tr>
        <?php if ($includeCategorySelect): ?>
            <tr>
                <td><label for="board_category_id_<?= $e($slug ?: 'new') ?>">Category</label></td>
                <td>
                    <select name="category_id" id="board_category_id_<?= $e($slug ?: 'new') ?>" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $e((string) $category->id) ?>"<?= (string) $category->id === (string) $selectedCategoryId ? ' selected' : '' ?>>
                                <?= $e($category->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        <?php endif; ?>
        <tr>
            <td><label for="board_position_<?= $e($slug ?: 'new') ?>">Position</label></td>
            <td><input id="board_position_<?= $e($slug ?: 'new') ?>" name="position" type="number" value="<?= (int) $position ?>"></td>
        </tr>
        <tr>
            <td>Status</td>
            <td><label><input type="checkbox" name="is_locked" value="1"<?= $isLocked ? ' checked' : '' ?>> Locked</label></td>
        </tr>
        <tr>
            <td><label for="board_custom_css_<?= $e($slug ?: 'new') ?>">Custom CSS</label></td>
            <td>
                <textarea id="board_custom_css_<?= $e($slug ?: 'new') ?>" name="custom_css" rows="3" placeholder="Optional, max 25000 characters" style="width: 100%;"><?= $e($customCss) ?></textarea>
            </td>
        </tr>
    </table>
    <button class="button" type="submit"><?= $e($submitLabel) ?></button>
    <?php if ($deleteAction !== null): ?>
        <button class="button" type="submit" formaction="<?= $e($deleteAction) ?>">Delete</button>
    <?php endif; ?>
</form>
