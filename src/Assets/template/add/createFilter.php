<?php use Osumi\OsumiFramework\Tools\OTools; ?>


  <?php echo $values['colors']->getColoredString('Osumi Framework', 'white', 'blue') ?>


<?php if ($values['error']!=0): ?>
<?php if ($values['error']==1): ?>
    <?php echo $values['colors']->getColoredString('ERROR', 'red') ?>: <?php echo OTools::getMessage('TASK_ADD_FILTER_ERROR') ?>


      <?php echo $values['colors']->getColoredString('php of add filter login', 'light_green') ?>


<?php endif ?>
<?php if ($values['error']==2): ?>
    <?php echo $values['colors']->getColoredString('ERROR', 'red') ?>: <?php echo OTools::getMessage('TASK_ADD_FILTER_EXISTS', [
		$values['colors']->getColoredString($values['filter_name'], 'light_green')
	]) ?>



<?php endif ?>
<?php else: ?>
	<?php echo OTools::getMessage('TASK_ADD_FILTER_NEW_FILTER', [
  	  $values['colors']->getColoredString($values['filter_name'], 'light_green')
    ]) ?>

	  <?php echo OTools::getMessage('TASK_ADD_FILTER_NEW_FILE', [
  	  $values['colors']->getColoredString($values['filter_file'], 'light_green')
    ]) ?>


<?php endif ?>
