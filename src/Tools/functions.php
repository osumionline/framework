<?php

declare(strict_types=1);

use Osumi\OsumiFramework\Core\OService;

function inject(string $item): OService {
  global $core;
  $reflection = new ReflectionClass($item);
  $class_name = str_ireplace('Service', '', $reflection->getShortName());

  if (!array_key_exists($class_name, $core->services)) {
    $service = new $item();
    $service->loadService();
    $core->services[$class_name] = $service;
  }

  return $core->services[$class_name];
}
