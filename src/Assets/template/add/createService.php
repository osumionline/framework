<?php use Osumi\OsumiFramework\Tools\OTools; ?>


  <?php echo $values['colors']->getColoredString('Osumi Framework', 'white', 'blue') ?>


<?php if ($values['error'] !== 0): ?>
<?php if ($values['error'] === 1): ?>
    <?php echo $values['colors']->getColoredString('ERROR', 'red') ?>: <?php echo OTools::getMessage('TASK_ADD_SERVICE_ERROR') ?>


      <?php echo $values['colors']->getColoredString('php of add --option service --name backend', 'light_green') ?>


<?php endif ?>
<?php if ($values['error'] === 2): ?>
    <?php echo $values['colors']->getColoredString('ERROR', 'red') ?>: <?php echo OTools::getMessage('TASK_ADD_SERVICE_EXISTS', [
		$values['colors']->getColoredString($values['service_file'], 'light_green')
	]) ?>



<?php endif ?>
<?php else: ?>
	<?php echo OTools::getMessage('TASK_ADD_SERVICE_NEW_SERVICE', [
  	  $values['colors']->getColoredString($values['service_name'], 'light_green')
    ]) ?>

	  <?php echo OTools::getMessage('TASK_ADD_SERVICE_NEW_FILE', [
  	  $values['colors']->getColoredString($values['service_file'], 'light_green')
    ]) ?>


<?php endif ?>
