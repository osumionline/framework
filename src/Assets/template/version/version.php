<?php use Osumi\OsumiFramework\Tools\OTools; ?>


==============================================================================================================

  <?php echo $values['colors']->getColoredString('Osumi Framework', 'white', 'blue') ?>


  <?php echo OTools::getVersion() ?> - <?php echo OTools::getVersionInformation() ?>


  <?php echo $values['colors']->getColoredString('GitHub', 'light_green').':  '.$values['repo_url'] ?>

  <?php echo $values['colors']->getColoredString('X', 'light_green').':       '.$values['x_url'] ?>


==============================================================================================================
