<?php use Osumi\OsumiFramework\Tools\OTools; ?>


  <?php echo $values['colors']->getColoredString('Osumi Framework', 'white', 'blue') ?>


<?php if ($values['error'] !== 0): ?>
<?php if ($values['error'] === 1): ?>
    <?php echo $values['colors']->getColoredString('ERROR', 'red') ?>: <?php echo OTools::getMessage('TASK_ADD_ACTION_ERROR') ?>


      <?php echo $values['colors']->getColoredString('php of add --option action --name api/getUsers --url /api/get-users', 'light_green') ?>


<?php endif ?>
<?php if ($values['error'] === 2): ?>
    <?php echo $values['colors']->getColoredString('ERROR', 'red') ?>: <?php echo OTools::getMessage('TASK_ADD_ACTION_EXISTS', [
		$values['colors']->getColoredString($values['action_name'], 'light_green')
	]) ?>



<?php endif ?>
<?php if ($values['error'] === 3): ?>
    <?php echo $values['colors']->getColoredString('ERROR', 'red') ?>: <?php echo OTools::getMessage('TASK_ADD_ACTION_TEMPLATE_EXISTS', [
		$values['colors']->getColoredString($values['action_template'], 'light_green')
	]) ?>



<?php endif ?>
<?php else: ?>
  <?php echo OTools::getMessage('TASK_ADD_ACTION_NEW_ACTION', [
  	  $values['colors']->getColoredString($values['action_name'], 'light_green')
    ]) ?>

    <?php echo OTools::getMessage('TASK_ADD_ACTION_NEW_FOLDER', [
  	  $values['colors']->getColoredString($values['action_folder'], 'light_green')
    ]) ?>

    <?php echo OTools::getMessage('TASK_ADD_ACTION_NEW_FILE', [
      $values['colors']->getColoredString($values['action_file'], 'light_green')
    ]) ?>

    <?php echo OTools::getMessage('TASK_ADD_ACTION_NEW_TEMPLATE', [
      $values['colors']->getColoredString($values['action_template'], 'light_green')
    ]) ?>


  <?php echo OTools::getMessage('TASK_ADD_ACTION_URLS_UPDATED') ?>


<?php endif ?>
