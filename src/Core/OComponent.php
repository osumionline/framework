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
use Exception;

/**
 * Base class for components
 *
 * @method void run(mixed $data = null) Optional hook executed before rendering
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
   * Loads component's properties and initializes data
   */
  public function __construct(array $vars = []) {
    $this->load($vars);
  }

  /**
   * Component initializer. Finds component's template file and it's type.
   * Adds received key/value pairs into component's public variables.
   *
   * @param array $vars List of variables to be mapped into component's public variables.
   */
  public function load(array $vars = []): void {
    if ($this->component_info['initialized']) {
      return; // Already initialized
    }

    // Get name of the class extending OComponent
    $component_class = get_class($this);

    // Create a log for the component
    $this->log = new OLog($component_class);

    // Use ReflectionClass to get file path
    $reflection = new ReflectionClass($component_class);
    $component_file = $reflection->getFileName(); // Full file path of the PHP file containing the class

    // Store the base directory of the component
    $this->component_info['component_base'] = dirname($component_file) . '/';

    // Get base name of the component's file (without extension)
    $base_name = str_ireplace('Component', '', pathinfo($component_file, PATHINFO_FILENAME));

    // Build template's name
    foreach ($this->allowed_extensions as $extension) {
      $template_name = $base_name . 'Template.' . $extension;
      $template_path = dirname($component_file) . '/' . $template_name;

      // Check if file exists
      if (file_exists($template_path)) {
        $this->component_info['template_name'] = $template_path;
        $this->component_info['template_type'] = $extension;
        break;
      }
    }

    // Check if template has been found
    if ($this->component_info['template_name'] === '') {
      throw new Exception("No valid template file found for the component: " . $component_class);
    }

    foreach ($vars as $key => $value) {
      // Verify if child class has a property with the same name
      if ($reflection->hasProperty($key)) {
        $property = $reflection->getProperty($key);

        // Check if the property is public
        if ($property->isPublic()) {
          $this->$key = $value;
        }
      }
    }

    // Set component as initialized
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
   * Render a component mixing it's properties into the template
   *
   * @param mixed $data Data to be passed to the "run" method, if exists
   *
   * @return string Return resulting string
   */
  public function render(mixed $data = null): string {
    // Check if component has a "run" method
    if (method_exists($this, 'run')) {
      $this->run($data);
    }

    if ($this->component_info['template_type'] === 'php') {
      return $this->renderPHP();
    }

    // Get template file's content
    $template_content = file_get_contents($this->component_info['template_name']);

    // Apply substitutions
    return $this->applyTemplateSubstitutions($template_content);
  }

  /**
   * PHP template files need to be executed instead of interpolated
   *
   * @return string Return resulting string
   */
  private function renderPHP(): string {
    ob_start();
    $reflection = new ReflectionClass($this);
    // Get component's public property list
    $public_properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

    // Put component variables into simple variables
    foreach ($public_properties as $property) {
      $property_name = $property->getName();
      $$property_name = $this->$property_name;
    }

    // Include template file so it get's executed and mixed with previously created variables
    include($this->component_info['template_name']);
    $content = ob_get_clean();

    // Apply substitutions
    return $this->applyTemplateSubstitutions($content);
  }

  /**
   * Using toString magic method allows component to be treated as a simple string variable
   *
   * @return string Return resulting string
   */
  public function __toString(): string {
    if (!$this->component_info['initialized']) {
      throw new Exception("Component hasn't been initialized.");
    }
    try {
      return $this->render();
    } catch (Exception $e) {
      return "Error rendering component: " . $e->getMessage();
    }
  }
}
