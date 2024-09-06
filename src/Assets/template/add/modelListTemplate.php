<?php
use Osumi\OsumiFramework\App\Component\Model\{{model_name}}\{{component_name}};

foreach ($values['list'] as $i => ${{model_name}}) {
  $component = new {{component_name}}([ '{{model_name}}' => ${{model_name}} ]);
	echo strval($component);
	if ($i<count($values['list'])-1) {
		echo ",\n";
	}
}
