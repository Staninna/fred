<?php
/** @var string $targetId */
/** @var callable(string, int): string $e */
?>

<div class="bbcode-toolbar" x-data="bbcodeToolbar('<?= $e($targetId) ?>')">
    <span class="bbcode-toolbar__label">BBCode:</span>
    <button type="button" class="button" @click="wrap('b')">[b]</button>
    <button type="button" class="button" @click="wrap('i')">[i]</button>
    <button type="button" class="button" @click="wrap('quote')">[quote]</button>
    <button type="button" class="button" @click="wrap('code')">[code]</button>
    <button type="button" class="button" @click="insertLink()">[url]</button>
</div>
