<?php
use Osumi\OsumiFramework\App\Component\Model\{{model_name}}\{{component_name}};

foreach ($list as $i => ${{model_name_lower}}) {
  $component = new {{component_name}}([ '{{model_name_lower}}' => ${{model_name_lower}} ]);
	echo strval($component);
	if ($i < count($values['list']) - 1) {
		echo ",\n";
	}
}
