<?php if ($this->multiple): ?>

  <h3><?php echo $this->generateLabel(); echo $this->xlabel; ?></h3><?php endif; ?> 
  <?php echo $this->generateWithError(!$GLOBALS['TL_CONFIG']['oldBeTheme']); ?>
