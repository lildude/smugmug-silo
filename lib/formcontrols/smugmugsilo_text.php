<div<?php echo ($class) ? ' class="' . $class . '"' : ''?><?php echo ($id) ? ' id="' . $id . '"' : ''?> style="clear:both">
	<span class="pct25"><label for="<?php echo $id; ?>"><?php echo $caption; ?></label></span>
    <span class="pct75"><input type="text" name="<?php echo $field; ?>" <?php if(!empty($style)) { echo "style='$style'"; } ?> value="<?php echo $value; ?>" <?php echo isset($disabled) ? 'disabled="disabled"' : ''; ?> <?php echo isset($tabindex) ? ' tabindex="' . $tabindex . '"' : ''?>><?php if (is_string($hpct)) {?></span><?php } ?>
</div>

