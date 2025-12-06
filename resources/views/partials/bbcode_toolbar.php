<?php
/** @var string $targetId */
?>

<div class="bbcode-toolbar" x-data="bbcodeToolbar('<?= htmlspecialchars($targetId, ENT_QUOTES, 'UTF-8') ?>')">
    <span class="bbcode-toolbar__label">BBCode:</span>
    <button type="button" class="button" @click="wrap('b')">[b]</button>
    <button type="button" class="button" @click="wrap('i')">[i]</button>
    <button type="button" class="button" @click="wrap('quote')">[quote]</button>
    <button type="button" class="button" @click="wrap('code')">[code]</button>
    <button type="button" class="button" @click="insertLink()">[url]</button>
</div>
