<?php
/** @var \Fred\Domain\Auth\Profile|null $profile */
/** @var \Fred\Domain\Community\Community $community */
/** @var array<int, string> $errors */
/** @var \Fred\Application\Auth\CurrentUser|null $currentUser */
/** @var callable(string, array): string $renderPartial */
?>

<article class="card card--hero">
    <div>
        <p class="eyebrow">Signature</p>
        <h1>Edit signature</h1>
        <p class="lede">Use BBCode to format your signature. Keep it short and friendly.</p>
    </div>
</article>

<article class="card card--compact">
    <?= $renderPartial('partials/errors.php', ['errors' => $errors]) ?>
    <form class="form" method="post" action="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/settings/signature" novalidate>
        <div class="field">
            <label for="signature">Signature</label>
            <textarea id="signature" name="signature" rows="5"><?= htmlspecialchars($profile?->signatureRaw ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            <div class="nav__subtitle">Supports [b], [i], [code], [quote], [url], and &gt;&gt;post links.</div>
        </div>
        <button class="button" type="submit">Save signature</button>
        <a class="button button--ghost" href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/u/<?= htmlspecialchars($currentUser?->username ?? '', ENT_QUOTES, 'UTF-8') ?>">Back to profile</a>
    </form>
</article>
