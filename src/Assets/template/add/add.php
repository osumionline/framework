<?php use Osumi\OsumiFramework\Tools\OTools; ?>

  <?php echo $values['colors']->getColoredString('Osumi Framework', 'white', 'blue') ?>


  <?php echo $values['colors']->getColoredString('ERROR', 'red') ?>: <?php echo OTools::getMessage('TASK_ADD_DEFAULT_NOT_VALID') ?>

  <?php echo OTools::getMessage('TASK_ADD_DEFAULT_AVAILABLE_OPTIONS') ?>


  · <?php echo $values['colors']->getColoredString('action', 'light_green') ?>: <?php echo OTools::getMessage('TASK_ADD_DEFAULT_ACTION') ?>

  · <?php echo $values['colors']->getColoredString('service', 'light_green') ?>: <?php echo OTools::getMessage('TASK_ADD_DEFAULT_SERVICE') ?>

  · <?php echo $values['colors']->getColoredString('task', 'light_green') ?>: <?php echo OTools::getMessage('TASK_ADD_DEFAULT_TASK') ?>

  · <?php echo $values['colors']->getColoredString('modelComponent', 'light_green') ?>: <?php echo OTools::getMessage('TASK_ADD_DEFAULT_MODEL_COMPONENT') ?>

  · <?php echo $values['colors']->getColoredString('component', 'light_green') ?>: <?php echo OTools::getMessage('TASK_ADD_DEFAULT_COMPONENT') ?>

  · <?php echo $values['colors']->getColoredString('filter', 'light_green') ?>: <?php echo OTools::getMessage('TASK_ADD_DEFAULT_FILTER') ?>

 
