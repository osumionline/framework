<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Core;

use Osumi\OsumiFramework\Tools\OPipeFunctions;
use Osumi\OsumiFramework\Core\OConfig;
use Osumi\OsumiFramework\Log\OLog;
use Osumi\OsumiFramework\Cache\OCacheContainer;
use Osumi\OsumiFramework\Web\OSession;
use ReflectionClass;
use ReflectionProperty;

/**
 * Base class for components.
 *
 * Components may optionally define a run() method. The method can receive
 * no parameters, an ORequest instance or an instance of a class extending
 * ODTO.
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

    if ($this->component_info['template_name'] === '') {
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
   * Method to apply template substitutions such as {{variable}} with class properties
   *
   * @param string $content Content of the template
   *
   * @return string Returns content with substitutions applied
   */
  private function applyTemplateSubstitutions(string $content): string {
    $reflection = new ReflectionClass($this);
    $public_properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

    foreach ($public_properties as $property) {
      $property_name = $property->getName();
      $property_value = $this->$property_name;

      // Check if there is any pattern of the variable in the content before proceeding
      if (!preg_match("/\{\{\s*" . preg_quote($property_name) . "(?:\.[a-zA-Z0-9_]+)?(?:\s*\|\s*[a-zA-Z0-9_]+(?:\(.*?\))?)?\s*\}\}/", $content)) {
        continue;
      }

      // If the value is a component, render it and replace the marker with the rendered content
      if ($property_value instanceof OComponent) {
        $content = preg_replace(
          "/\{\{\s*" . preg_quote($property_name) . "\s*\}\}/",
          $property_value->render(),
          $content
        );
        continue;
      }

      // Checking and handling {{variable | filter}}
      $content = preg_replace_callback(
        "/\{\{\s*" . preg_quote($property_name) . "\.([a-zA-Z0-9_]+)\s*\|\s*([a-zA-Z0-9_]+)(?:\(([^)]*)\))?\s*\}\}/",
        function ($matches) use ($property_value) {
          $sub_property = $matches[1];
          $filter_name = $matches[2];

          $params = isset($matches[3]) ? str_getcsv($matches[3], ',', '"') : [];
          $params = array_map('trim', $params);

          // Apply pipe function to value
          if (is_object($property_value) && property_exists($property_value, $sub_property)) {
            $sub_value = $property_value->$sub_property;

            switch ($filter_name) {
              case 'date':
                array_unshift($params, $sub_value);
                return OPipeFunctions::getDateValue(...$params);
              case 'number':
                array_unshift($params, $sub_value);
                return OPipeFunctions::getNumberValue(...$params);
              case 'string':
                return OPipeFunctions::getStringValue($sub_value);
              case 'plain':
                return OPipeFunctions::getStringPlainValue($sub_value);
              case 'bool':
                return OPipeFunctions::getBoolValue($sub_value);
              default:
                return $matches[0]; // If the pipe function is not valid just return the value
            }
          }
          return $matches[0]; // If property is not right just return it
        },
        $content
      );

      // Direct handling of {{variable | filter}} (no additional properties)
      $content = preg_replace_callback(
        "/\{\{\s*" . preg_quote($property_name) . "\s*\|\s*([a-zA-Z0-9_]+)(?:\(([^)]*)\))?\s*\}\}/",
        function ($matches) use ($property_value) {
          $filter_name = $matches[1];

          $params = isset($matches[2]) ? str_getcsv($matches[2], ',', '"') : [];
          $params = array_map('trim', $params);

          switch ($filter_name) {
            case 'date':
              array_unshift($params, $property_value);
              return OPipeFunctions::getDateValue(...$params);
            case 'number':
              array_unshift($params, $property_value);
              return OPipeFunctions::getNumberValue(...$params);
            case 'string':
              return OPipeFunctions::getStringValue($property_value);
            case 'plain':
              return OPipeFunctions::getStringPlainValue($property_value);
            case 'bool':
              return OPipeFunctions::getBoolValue($property_value);
            default:
              return $matches[0]; // If the pipe function is not valid just return the value
          }
        },
        $content
      );

      // Handling {{object.property}}
      if (is_object($property_value)) {
        preg_match_all("/\{\{\s*" . preg_quote($property_name) . "\.([a-zA-Z0-9_]+)\s*\}\}/", $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
          $sub_property = $match[1];
          if (property_exists($property_value, $sub_property)) {
            $sub_value = $property_value->$sub_property;
            $content = str_replace($match[0], strval($sub_value), $content);
          }
        }
      } else {
        // Direct substitution of {{variable}} or {{  variable  }}
        $content = preg_replace("/\{\{\s*" . preg_quote($property_name) . "\s*\}\}/", strval($property_value), $content);
      }
    }

    return $content;
  }

  /**
   * Render a component mixing its properties into the template.
   *
   * If the component defines a run method, it is executed before rendering.
   * The run method can receive an ORequest, an ODTO instance or no parameter.
   *
   * @param mixed $data Data to be passed to the run method, if any.
   *
   * @return string Resulting rendered content.
   *
   * @throws \RuntimeException If the component template cannot be read.
   */
  public function render(mixed $data = null): string {
    // Check if component has a "run" method
    if (method_exists($this, 'run')) {
      if (is_null($data)) {
        $this->run();
      } else {
        $this->run($data);
      }
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
   * Using toString magic method allows component to be treated as a simple string variable
   *
   * @return string Return resulting string
   */
  public function __toString(): string {
    if (!$this->component_info['initialized']) {
      throw new \RuntimeException(
        "Component hasn't been initialized."
      );
    }

    return $this->render();
  }
}
