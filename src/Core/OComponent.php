<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Core;

use Osumi\OsumiFramework\Tools\OPipeFunctions;
use Osumi\OsumiFramework\Core\OConfig;
use Osumi\OsumiFramework\Log\OLog;
use Osumi\OsumiFramework\Cache\OCacheContainer;
use Osumi\OsumiFramework\Web\OSession;
use Osumi\OsumiFramework\Web\OStreamResponse;
use ReflectionNamedType;
use ReflectionClass;
use ReflectionProperty;

/**
 * Base class for components.
 *
 * Components may optionally define a run() method. The method can receive
 * no parameters, an ORequest instance or an instance of a class extending
 * ODTO.
 *
 * A component whose run() method explicitly returns OStreamResponse can omit
 * its template and produce a streamed HTTP response instead.
 */
class OComponent {
  protected OLog | null $log = null;
  protected array $allowed_extensions = ['html', 'json', 'xml', 'php'];
  public array $component_info = [
    'initialized' => false,
    'template_name' => '',
    'template_type' => 'html',
    'component_base' => ''
  ];

  /**
   * Initialize the component with the provided values.
   *
   * @param array<array-key, mixed> $vars Values to map to public component
   *                                      properties.
   *
   * @throws \RuntimeException If the component file or template cannot be
   *                           resolved.
   */
  public function __construct(array $vars = []) {
    $this->load($vars);
  }

  /**
   * Initialize the component.
   *
   * The component template is resolved from the component class filename and
   * the provided values are mapped to matching public properties.
   *
   * @param array<array-key, mixed> $vars Values to map to public component
   *                                      properties.
   *
   * @return void
   *
   * @throws \RuntimeException If the component file or template cannot be
   *                           resolved.
   */
  public function load(array $vars = []): void {
    if ($this->component_info['initialized']) {
      return;
    }

    $component_class = get_class($this);

    $this->log = new OLog(
      $component_class
    );

    $reflection = new ReflectionClass(
      $component_class
    );

    $component_file = $reflection->getFileName();
    if ($component_file === false) {
      throw new \RuntimeException(
        "Could not resolve component file for '{$component_class}'."
      );
    }

    $this->component_info['component_base'] = dirname(
      $component_file
    ) . '/';

    $base_name = str_ireplace(
      'Component',
      '',
      pathinfo(
        $component_file,
        PATHINFO_FILENAME
      )
    );

    foreach ($this->allowed_extensions as $extension) {
      $template_name = $base_name
        . 'Template.'
        . $extension;

      $template_path = dirname(
        $component_file
      ) . '/' . $template_name;

      if (is_file($template_path)) {
        $this->component_info['template_name'] = $template_path;
        $this->component_info['template_type'] = $extension;

        break;
      }
    }

    if (
      $this->component_info['template_name'] === '' &&
      !$this->hasStreamOnlyRunReturnType(
        $reflection
      )
    ) {
      throw new \RuntimeException(
        "No valid template file found for component '{$component_class}'."
      );
    }

    foreach ($vars as $key => $value) {
      if (!is_string($key)) {
        continue;
      }

      if (!$reflection->hasProperty($key)) {
        continue;
      }

      $property = $reflection->getProperty(
        $key
      );
      if ($property->isPublic()) {
        $this->$key = $value;
      }
    }

    $this->component_info['initialized'] = true;
  }


  /**
   * Get the application configuration (shortcut to $core->config)
   *
   * @return OConfig Configuration class object
   */
  public function getConfig(): OConfig {
    global $core;
    return $core->config;
  }

  /**
   * Get component's log object
   *
   * @return Olog | null Log object
   */
  public function getLog(): OLog | null {
    return $this->log;
  }

  /**
   * Get access to the users session information
   *
   * @return OSession Session configuration class object
   */
  public final function getSession(): OSession {
    global $core;
    return $core->session;
  }

  /**
   * Get access to the cache container
   *
   * @return OCacheContainer Cache container class object
   */
  public final function getCacheContainer(): OCacheContainer {
    global $core;
    return $core->cache_container;
  }

  /**
   * Add a CSS file (or list) to be added to output
   *
   * @param string | array $css CSS File (or list) to be added
   *
   * @return void
   */
  public function addCss(string | array $css): void {
    global $core;
    if (is_string($css)) {
      $css = [$css];
    }
    $core->includes['css'] = array_unique(array_merge($core->includes['css'], $css));
  }

  /**
   * Add a CSS file (or list) to be inlined to output
   *
   * @param string | array $css CSS File (or list) to be inlined
   *
   * @return void
   */
  public function addInlineCss(string | array $css): void {
    global $core;
    if (is_string($css)) {
      $css = [$css];
    }
    $list = [];
    foreach ($css as $item) {
      $item = $this->component_info['component_base'] . $item . '.css';
      $list[] = $item;
    }
    $core->includes['inline_css'] = array_unique(array_merge($core->includes['inline_css'], $list));
  }

  /**
   * Add a JS file (or list) to be added to output
   *
   * @param string | array $js JS File (or list) to be added
   *
   * @return void
   */
  public function addJs(string | array $js): void {
    global $core;
    if (is_string($js)) {
      $js = [$js];
    }
    $core->includes['js'] = array_unique(array_merge($core->includes['js'], $js));
  }

  /**
   * Add a JS file (or list) to be inlined to output
   *
   * @param string | array $js JS File (or list) to be inlined
   *
   * @return void
   */
  public function addInlineJs(string | array $js): void {
    global $core;
    if (is_string($js)) {
      $js = [$js];
    }
    $list = [];
    foreach ($js as $item) {
      $item = $this->component_info['component_base'] . $item . '.js';
      $list[] = $item;
    }
    $core->includes['inline_js'] = array_unique(array_merge($core->includes['inline_js'], $list));
  }

  /**
   * Check whether the component run() method explicitly returns a stream response.
   *
   * This allows stream-only components to omit a template while preserving the
   * existing template requirement for every other component.
   *
   * @param ReflectionClass<object> $reflection Component reflection.
   *
   * @return bool True when run() explicitly returns OStreamResponse.
   */
  private function hasStreamOnlyRunReturnType(
    ReflectionClass $reflection
  ): bool {
    if (!$reflection->hasMethod('run')) {
      return false;
    }

    $return_type = $reflection
      ->getMethod('run')
      ->getReturnType();

    return $return_type instanceof ReflectionNamedType
      && !$return_type->isBuiltin()
      && $return_type->getName() === OStreamResponse::class;
  }

  /**
   * Apply template substitutions using the component public properties.
   *
   * @param string $content Template content.
   *
   * @return string Content with substitutions applied.
   *
   * @throws \RuntimeException If a template regular expression cannot be
   *                           evaluated.
   */
  private function applyTemplateSubstitutions(
    string $content
  ): string {
    $reflection = new ReflectionClass(
      $this
    );

    $public_properties = $reflection->getProperties(
      ReflectionProperty::IS_PUBLIC
    );

    foreach ($public_properties as $property) {
      $property_name = $property->getName();
      $property_value = $this->$property_name;

      $property_pattern = "/\{\{\s*"
        . preg_quote(
          $property_name,
          '/'
        )
        . "(?:\.[a-zA-Z0-9_]+)?(?:\s*\|\s*[a-zA-Z0-9_]+(?:\(.*?\))?)?\s*\}\}/";

      $match_result = preg_match(
        $property_pattern,
        $content
      );

      if ($match_result === false) {
        throw new \RuntimeException(
          "Could not evaluate template expression for property '{$property_name}'."
        );
      }

      if ($match_result === 0) {
        continue;
      }

      /*
       * If the value is another component, render it and replace the direct
       * property marker.
       */
      if ($property_value instanceof OComponent) {
        $rendered_component = $property_value->render();

        if ($rendered_component instanceof OStreamResponse) {
          throw new \RuntimeException(
            'A streamed response cannot be rendered as a nested component.'
          );
        }

        $replaced_content = preg_replace(
          "/\{\{\s*"
            . preg_quote(
              $property_name,
              '/'
            )
            . "\s*\}\}/",
          $rendered_component,
          $content
        );

        if ($replaced_content === null) {
          throw new \RuntimeException(
            "Could not replace component template property '{$property_name}'."
          );
        }

        $content = $replaced_content;

        continue;
      }

      /*
		   * Handle {{ object.property | filter }}.
		   */
      $replaced_content = preg_replace_callback(
        "/\{\{\s*"
          . preg_quote(
            $property_name,
            '/'
          )
          . "\.([a-zA-Z0-9_]+)\s*\|\s*([a-zA-Z0-9_]+)(?:\(([^)]*)\))?\s*\}\}/",
        function (
          array $matches
        ) use (
          $property_value
        ): string {
          $sub_property = $matches[1];
          $filter_name = $matches[2];

          $params = isset($matches[3])
            ? str_getcsv(
              $matches[3],
              ',',
              '"'
            )
            : [];

          $params = array_map(
            'trim',
            $params
          );

          if (
            !is_object($property_value) ||
            !property_exists(
              $property_value,
              $sub_property
            )
          ) {
            return $matches[0];
          }

          $sub_value = $property_value->$sub_property;

          return match ($filter_name) {
            'date' => OPipeFunctions::getDateValue(
              $sub_value,
              ...$params
            ),

            'number' => OPipeFunctions::getNumberValue(
              $sub_value,
              ...$params
            ),

            'string' => OPipeFunctions::getStringValue(
              $sub_value
            ),

            'plain' => OPipeFunctions::getStringPlainValue(
              $sub_value
            ),

            'bool' => OPipeFunctions::getBoolValue(
              $sub_value
            ),

            default => $matches[0]
          };
        },
        $content
      );

      if ($replaced_content === null) {
        throw new \RuntimeException(
          "Could not apply object template filter for property '{$property_name}'."
        );
      }

      $content = $replaced_content;

      /*
		   * Handle {{ property | filter }}.
		   */
      $replaced_content = preg_replace_callback(
        "/\{\{\s*"
          . preg_quote(
            $property_name,
            '/'
          )
          . "\s*\|\s*([a-zA-Z0-9_]+)(?:\(([^)]*)\))?\s*\}\}/",
        function (
          array $matches
        ) use (
          $property_value
        ): string {
          $filter_name = $matches[1];

          $params = isset($matches[2])
            ? str_getcsv(
              $matches[2],
              ',',
              '"'
            )
            : [];

          $params = array_map(
            'trim',
            $params
          );

          return match ($filter_name) {
            'date' => OPipeFunctions::getDateValue(
              $property_value,
              ...$params
            ),

            'number' => OPipeFunctions::getNumberValue(
              $property_value,
              ...$params
            ),

            'string' => OPipeFunctions::getStringValue(
              $property_value
            ),

            'plain' => OPipeFunctions::getStringPlainValue(
              $property_value
            ),

            'bool' => OPipeFunctions::getBoolValue(
              $property_value
            ),

            default => $matches[0]
          };
        },
        $content
      );

      if ($replaced_content === null) {
        throw new \RuntimeException(
          "Could not apply template filter for property '{$property_name}'."
        );
      }

      $content = $replaced_content;

      /*
		   * Handle {{ object.property }}.
		   */
      if (is_object($property_value)) {
        $match_result = preg_match_all(
          "/\{\{\s*"
            . preg_quote(
              $property_name,
              '/'
            )
            . "\.([a-zA-Z0-9_]+)\s*\}\}/",
          $content,
          $matches,
          PREG_SET_ORDER
        );

        if ($match_result === false) {
          throw new \RuntimeException(
            "Could not evaluate object template property '{$property_name}'."
          );
        }

        foreach ($matches as $match) {
          $sub_property = $match[1];

          if (!property_exists(
            $property_value,
            $sub_property
          )) {
            continue;
          }

          $sub_value = $property_value->$sub_property;

          $content = str_replace(
            $match[0],
            strval(
              $sub_value
            ),
            $content
          );
        }

        continue;
      }

      /*
		   * Handle {{ property }}.
		   */
      $replaced_content = preg_replace(
        "/\{\{\s*"
          . preg_quote(
            $property_name,
            '/'
          )
          . "\s*\}\}/",
        strval(
          $property_value
        ),
        $content
      );

      if ($replaced_content === null) {
        throw new \RuntimeException(
          "Could not replace template property '{$property_name}'."
        );
      }

      $content = $replaced_content;
    }

    return $content;
  }

  /**
   * Render a component or return its streamed response.
   *
   * If the component defines a run method, it is executed before rendering.
   * A run method that returns OStreamResponse bypasses template rendering and
   * returns the stream response directly.
   *
   * @param mixed $data Data to be passed to the run method, if any.
   *
   * @return string|OStreamResponse Rendered content or streamed response.
   *
   * @throws \RuntimeException If the component template cannot be read.
   */
  public function render(
    mixed $data = null
  ): string | OStreamResponse {
    if (method_exists($this, 'run')) {
      $run_result = is_null($data)
        ? $this->run()
        : $this->run($data);

      if ($run_result instanceof OStreamResponse) {
        return $run_result;
      }
    }

    if ($this->component_info['template_name'] === '') {
      throw new \RuntimeException(
        'Component did not return an OStreamResponse and has no template.'
      );
    }

    if ($this->component_info['template_type'] === 'php') {
      return $this->renderPHP();
    }

    $template_name = $this->component_info['template_name'];

    if (!is_readable($template_name)) {
      throw new \RuntimeException(
        "Component template '{$template_name}' is not readable."
      );
    }

    $template_content = file_get_contents(
      $template_name
    );

    if ($template_content === false) {
      throw new \RuntimeException(
        "Unable to read component template '{$template_name}'."
      );
    }

    return $this->applyTemplateSubstitutions(
      $template_content
    );
  }

  /**
   * Render a PHP component template.
   *
   * Public component properties are exposed as local variables to the PHP
   * template before it is executed.
   *
   * @return string Rendered component content.
   *
   * @throws \RuntimeException If the component template cannot be read or
   *                           output buffering cannot be used.
   */
  private function renderPHP(): string {
    $template_name = $this->component_info['template_name'];

    if (!is_readable($template_name)) {
      throw new \RuntimeException(
        "Component template '{$template_name}' is not readable."
      );
    }

    $buffer_level = ob_get_level();

    if (!ob_start()) {
      throw new \RuntimeException(
        "Could not start output buffering for component template '{$template_name}'."
      );
    }

    try {
      $reflection = new ReflectionClass($this);

      // Put component public properties into local variables
      $public_properties = $reflection->getProperties(
        ReflectionProperty::IS_PUBLIC
      );

      foreach ($public_properties as $property) {
        $property_name = $property->getName();
        $$property_name = $this->$property_name;
      }

      include $template_name;

      $content = ob_get_contents();

      if ($content === false) {
        throw new \RuntimeException(
          "Could not retrieve rendered component template '{$template_name}'."
        );
      }
    } finally {
      while (ob_get_level() > $buffer_level) {
        ob_end_clean();
      }
    }

    return $this->applyTemplateSubstitutions(
      $content
    );
  }

  /**
   * Render the component when it is used as a string.
   *
   * Stream responses cannot be converted to strings and must be handled by the
   * HTTP response pipeline instead.
   *
   * @return string Rendered component content.
   *
   * @throws \RuntimeException When the component returns a streamed response.
   */
  public function __toString(): string {
    if (!$this->component_info['initialized']) {
      throw new \RuntimeException(
        "Component hasn't been initialized."
      );
    }

    $result = $this->render();

    if ($result instanceof OStreamResponse) {
      if ($result->shouldCloseOnFinish()) {
        $result->close();
      }

      throw new \RuntimeException(
        'An OStreamResponse cannot be converted to a string.'
      );
    }

    return $result;
  }
}
