<?php
// One message of ./bottext.php as a field: its text (the default beside it once it has
// been changed), its switch, reaction and sticker. ./subapps.php shows the messages of
// 📲 with the same markup, posting to ./bottext.php with return=subapps.
function bt_web_field($key, array $v, $lang, array $t, array $isAlert, array $switchable, array $canReact, array $noSticker)
{
    $k = htmlspecialchars($key);
    ?>
        <div class="field">
            <label style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                <span><?= htmlspecialchars($v['label']) ?> <span class="cm" dir="ltr" style="font-size:.7rem;opacity:.6"><?= $k ?></span></span>
                <?php if ($v['changed']): ?>
                    <span class="tag tag-warn"><?= $t['bottextChangedLabel'] ?></span>
                <?php endif; ?>
            </label>
            <textarea name="texts[<?= $k ?>]" class="textarea" dir="auto" rows="<?= min(8, substr_count($v['current'], "\n") + 1) ?>"><?= htmlspecialchars($v['current']) ?></textarea>
            <?php if (isset($isAlert[$key])): ?><div class="field-hint">⚠️ <?= $t['btAlertHint'] ?></div><?php endif; ?>
            <?php if ($v['textChanged']): ?>
                <div class="field-hint" style="display:flex;align-items:flex-start;gap:8px">
                    <span style="flex:1;white-space:pre-wrap"><?= $t['bottextDefaultLabel'] ?> <?= htmlspecialchars($v['default']) ?></span>
                    <button type="button" class="btn btn-ghost btn-sm" data-default="<?= htmlspecialchars($v['default']) ?>"
                        onclick="this.closest('.field').querySelector('textarea').value = this.dataset.default"><?= $t['bottextResetBtn'] ?></button>
                </div>
            <?php endif; ?>
            <div class="lang-chips" style="margin-top:8px;align-items:center">
                <?php if (isset($switchable[$key])): ?>
                    <label class="lang-chip"><input type="hidden" name="on[<?= $k ?>]" value="0"><input type="checkbox" name="on[<?= $k ?>]" value="1"<?= bt_item_enabled($key, $lang) ? ' checked' : '' ?>> <?= htmlspecialchars(bt_item_switch_label($key)) ?></label>
                <?php endif; ?>
                <?php if (isset($canReact[$key])): ?>
                    <label class="lang-chip" title="<?= htmlspecialchars($t['btReactionHint']) ?>"><?= $t['btReaction'] ?> <input type="text" name="react[<?= $k ?>]" value="<?= htmlspecialchars($v['reaction']) ?>" class="input" style="width:64px;padding:4px 8px;text-align:center" placeholder="—"></label>
                <?php endif; ?>
                <?php if (!isset($noSticker[$key])): ?>
                    <label class="lang-chip"><?= $t['btSticker'] ?> <?= $v['sticker'] !== '' ? '✅' : (bt_default_sticker($key) !== '' ? '<span title="' . htmlspecialchars($t['btStickerDefault']) . '">🔹</span>' : '—') ?>
                        <input type="text" name="sticker[<?= $k ?>]" value="" class="input" dir="ltr" style="width:170px;padding:4px 8px" placeholder="<?= htmlspecialchars($t['btStickerPlaceholder']) ?>"></label>
                    <?php if ($v['sticker'] !== ''): ?>
                        <label class="lang-chip"><input type="checkbox" name="sticker_del[<?= $k ?>]" value="1"> <?= $t['btStickerDelete'] ?></label>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
<?php
}
