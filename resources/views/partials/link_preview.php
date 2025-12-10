<?php
/** @var array<int, array{url:string, title:string, description:?string, image:?string, host:string}> $previews */
/** @var callable $e */

if ($previews === []) {
    return '';
}
?>
<div class="link-preview-list">
    <?php foreach ($previews as $preview): ?>
        <?php $hasImage = !empty($preview['image']); ?>
        <a class="link-preview<?= $hasImage ? '' : ' link-preview--no-thumb' ?>" href="<?= $e($preview['url']) ?>" target="_blank" rel="noopener">
            <?php if ($hasImage): ?>
                <div class="link-preview__thumb">
                    <img src="<?= $e($preview['image']) ?>" alt="Preview image" loading="lazy">
                </div>
            <?php endif; ?>
            <div class="link-preview__body">
                <div class="link-preview__host small muted"><?= $e($preview['host']) ?></div>
                <div class="link-preview__title"><?= $e($preview['title']) ?></div>
                <?php if (!empty($preview['description'])): ?>
                    <div class="link-preview__desc small"><?= $e($preview['description']) ?></div>
                <?php endif; ?>
            </div>
        </a>
    <?php endforeach; ?>
</div>
