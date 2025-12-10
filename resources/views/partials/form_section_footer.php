<?php
/** @var callable $e */
/** @var string $submitLabel */
/** @var string|null $backUrl */
/** @var bool $showBack */
?>
                </table>
                <button class="button" type="submit"><?= $e($submitLabel) ?></button>
                <?php if ($showBack): ?>
                    <a class="button" href="<?= $e($backUrl ?? '#') ?>">Back</a>
                <?php endif; ?>
            </form>
        </td>
    </tr>
</table>
