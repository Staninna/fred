<?php
/** @var callable $e */
/** @var string $submitLabel */
/** @var string|null $backUrl */
/** @var bool $showBack */
?>
                </table>
                <button class="button" type="submit"><?= $e($submitLabel ?? 'Save') ?></button>
                <?php if ($showBack ?? false): ?>
                    <a class="button" href="<?= $e($backUrl ?? '#') ?>">Back</a>
                <?php endif; ?>
            </form>
        </td>
    </tr>
</table>
