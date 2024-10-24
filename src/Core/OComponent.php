<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Core;

use Osumi\OsumiFramework\Tools\OTools;
use Osumi\OsumiFramework\Core\OConfig;
use Osumi\OsumiFramework\Log\OLog;
use Osumi\OsumiFramework\Cache\OCacheContainer;
use Osumi\OsumiFramework\Web\OSession;
use \ReflectionClass;
use \ReflectionProperty;

/**
 * Base class for components
 */
class OComponent {
  protected ?OLog $log = null;
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
   * @return ?Olog Log object
   */
  public function getLog(): ?OLog {
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
      $item = $this->component_info['component_base'].$item.'.css';
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
      $item = $this->component_info['component_base'].$item.'.js';
      $list[] = $item;
    }
    $core->includes['inline_js'] = array_unique(array_merge($core->includes['inline_js'], $list));
  }

  /**
   * Render a component mixing it's properties into the template
   *
   * @return string Return resulting string
   */
  public function render(): string {
    if ($this->component_info['template_type'] === 'php') {
      return $this->renderPHP();
    }

    // Get template file's content
    $template_content = file_get_contents($this->component_info['template_name']);
    $reflection = new ReflectionClass($this);

    // Get component's public property list
    $public_properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

    // Put variables into the template
    foreach ($public_properties as $property) {
      $property_name = $property->getName();
      $property_value = $this->$property_name;
      $template_content = str_replace("{{" . $property_name . "}}", strval($property_value), $template_content);
    }

    return $template_content;
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
    return ob_get_clean();
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
