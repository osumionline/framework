<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Tools;

/**
 * OTools - Utility class with auxiliary tools
 */
class OTools {
	/**
	 * Get a string with a random number of characters (letters, numbers or special characters)
	 *
	 * @param array $options Array of options to generate the string (num -number of characters to return-, lower -include lower case letters-, upper -include upper case letters-, numbers -include numbers- and special -include special characters-)
	 *
	 * @return string Generated string based on given options
	 */
	public static function getRandomCharacters(array $options): string {
		$num     = array_key_exists('num',     $options) ? $options['num']     : 5;
		$lower   = array_key_exists('lower',   $options) ? $options['lower']   : false;
		$upper   = array_key_exists('upper',   $options) ? $options['upper']   : false;
		$numbers = array_key_exists('numbers', $options) ? $options['numbers'] : false;
		$special = array_key_exists('special', $options) ? $options['special'] : false;

		$seed = '';
		if ($lower) { $seed .= 'abcdefghijklmnopqrstuvwxyz'; }
		if ($upper) { $seed .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'; }
		if ($numbers) { $seed .= '0123456789'; }
		if ($special) { $seed .= '!@#$%^&*()'; }

		$seed = str_split($seed);
		shuffle($seed);
		$rand = '';
		$list = array_rand($seed, $num);
		if (!is_array($list)){
			$list = [$list];
		}

		foreach ($list as $k) {
			$rand .= $seed[$k];
		}

		return $rand;
	}

	/**
	 * Render a template from a file or a given template with given parameters
	 *
	 * @param string $path Path to a template file
	 *
	 * @param string $html Template as a string
	 *
	 * @param array $values Key / value pair array to be rendered
	 *
	 * @return string Loaded template with rendered parameters
	 */
	public static function getTemplate(string $path, string $html, array $values): ?string  {
		if ($path!='') {
			if (file_exists($path)) {
				$html = file_get_contents($path);
			}
			else{
				return null;
			}
		}

		foreach ($values as $key => $value) {
			$html = str_ireplace('{{'.$key.'}}', $value, $html);
		}

		return $html;
	}

	/**
	 * Interprets and renders a template from a file with given parameters
	 *
	 * @param string $path Path to a template file
	 *
	 * @param array $values Key / value pair array to be rendered
	 *
	 * @return string Loaded template with rendered parameters
	 */
	public static function getPartial(string $path, array $values): ?string {
		if (file_exists($path)) {
			ob_start();
			include($path);
			$output = ob_get_contents();
			ob_end_clean();

			return $output;
		}
		return null;
	}

	/**
	 * Get a component's content anywhere, even in a template-less execution
	 *
	 * @param string $name Name of the component file that will be loaded
	 *
	 * @param array $values Array of information that will be loaded into the component
	 *
	 * @return string Loaded component with rendered parameters
	 */
	public static function getComponent(string $name, array $values=[]): ?string {
		global $core;
		$component_name = $name;
		if (stripos($component_name, '/')!==false) {
			$component_name = array_pop(explode('/', $component_name));
		}

		$component_file = $core->config->getDir('app_component').$name.'/'.$component_name.'Component.php';
		$output = self::getPartial($component_file, $values);

		if (is_null($output)) {
			$output = 'ERROR: File '.$name.' not found';
		}

		return $output;
	}

	/**
	 * Function to get a model object's JSON representstion
	 *
	 * @param any $obj Model object
	 *
	 * @param array $exclude List of fields to be excluded
	 *
	 * @param array $empty List of fields to be returned empty
	 *
	 * @return string JSON string representation of the object or null if given object was null or not a model object
	 */
	public static function getModelComponent($obj, array $exclude=[], array $empty=[]): string {
		return (!is_null($obj) && method_exists($obj, 'generate')) ? $obj->generate('json', $exclude, $empty) : 'null';
	}

	/**
	 * Get a files content as a Base64 string
	 *
	 * @param string $filename Route of the filename to be loaded
	 *
	 * @return string Content of the file as a Base64 string
	 */
	public static function fileToBase64(string $filename): ?string {
		if (file_exists($filename)) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$filebinary = (filesize($filename)>0) ? fread(fopen($filename, 'r'), filesize($filename)) : '';
			return 'data:' . finfo_file($finfo, $filename) . ';base64,' . base64_encode($filebinary);
		}
		return null;
	}

	/**
	 * Save a Base64 string back to a file
	 *
	 * @param string $base64_string Base64 string containing a file
	 *
	 * @param string $filename Route to the file to be saved
	 *
	 * @return void
	 */
	public static function base64ToFile(string $base64_string, string $filename): void {
		$ifp = fopen($filename, 'wb');
		$data = explode(',', $base64_string);
		fwrite($ifp, base64_decode($data[1]));
		fclose($ifp);
	}

	/**
	 * Encode data to Base64URL (credit to https://base64.guru/developers/php/examples/base64url)
	 *
	 * @param string $data Data to be encoded
	 *
	 * @return string Data encoded in Base64URL or null if there was an error
	 */
	public static function base64urlEncode(string $data): ?string {
		$b64 = base64_encode($data);

		// Make sure you get a valid result, otherwise, return FALSE, as the base64_encode() function do
		if ($b64 === false) {
			return null;
		}

		// Convert Base64 to Base64URL by replacing “+” with “-” and “/” with “_”
		$url = strtr($b64, '+/', '-_');

		// Remove padding character from the end of line and return the Base64URL result
		return rtrim($url, '=');
	}

	/**
	 * Decode data from Base64URL (credit to https://base64.guru/developers/php/examples/base64url)
	 *
	 * @param string $data Data to be decoded
	 *
	 * @param bool $strict Optional parameter for strict base64_decode
	 *
	 * @return bool|string Data decoded or false if there was an error
	 */
	public static function base64urlDecode(string $data, bool $strict = false) {
		// Convert Base64URL to Base64 by replacing “-” with “+” and “_” with “/”
		$b64 = strtr($data, '-_', '+/');

		// Decode Base64 string and return the original data
		return base64_decode($b64, $strict);
	}

	/**
	 * Parse a string with bbcode tags (i / b / u / img / url / mailto / color)
	 *
	 * @param string $str String to be parsed with bbcodes
	 *
	 * @return string String with parsed bbcodes
	 */
	public static function bbcode(string $str): string {
		$bbcode = [
			"/\<(.*?)>/is",
			"/\[i\](.*?)\[\/i\]/is",
			"/\[b\](.*?)\[\/b\]/is",
			"/\[u\](.*?)\[\/u\]/is",
			"/\[img\](.*?)\[\/img\]/is",
			"/\[url=(.*?)\](.*?)\[\/url\]/is",
			"/\[mailto=(.*?)\](.*?)\[\/mailto\]/is",
			"/\[color=(.*?)\](.*?)\[\/color\]/is"
		];
		$html = [
			"<$1>",
			"<i>$1</i>",
			"<b>$1</b>",
			"<u>$1</u>",
			"<img src=\"$1\" />",
			"<a href=\"$1\" target=\"_blank\">$2</a>",
			"<a href=\"mailto:$1\">$2</a>",
			"<span style=\"color:$1\">$2</span>"
		];
		$str = preg_replace($bbcode, $html, $str);
		return $str;
	}

	/**
	 * Show an error page instead of template (403 / 404 / 500 errors) if user hasn't defined a custom ones
	 *
	 * @param array $res Array containing information about the error
	 *
	 * @param string $mode Error mode (403 / 404 / 500 / module / action)
	 *
	 * @return void
	 */
	public static function showErrorPage(array $res, string $mode): void {
		global $core;
		if (!is_null($core->config->getErrorPage($mode))) {
			header('Location:'.$core->config->getErrorPage($mode));
			exit;
		}

		$params = [
			'mode'    => $mode,
			'version' => self::getVersion(),
			'title'   => $core->config->getDefaultTitle(),
			'message' => array_key_exists('message', $res) ? $res['message'] : '',
			'res'     => $res
		];

		if ($params['title']=='') {
			$params['title'] = 'Osumi Framework';
		}
		$path = $core->config->getDir('ofw_template').'error.php';

		if ($mode=='403') { header($_SERVER['SERVER_PROTOCOL'].' 403 Forbidden'); }
		if ($mode=='404') { header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found'); }
		if ($mode=='500') { header($_SERVER['SERVER_PROTOCOL'].' 500 Internal Server Error'); }

		echo self::getPartial($path, $params);
		exit;
	}

	/**
	 * Get a framework specific localized message
	 *
	 * @param string $key Key code of the message
	 *
	 * @param array $params Key / value array with parameters to be rendered on the message
	 *
	 * @return string Localized message with parameters rendered
	 */
	public static function getMessage(string $key, array $params=null): string {
		global $core;

		$translation = $core->translate->getTranslation($key);
		if (is_null($translation)) {
			return null;
		}

		$translation = str_ireplace("\\n", "\n", $translation);

		if (is_null($params)){
			return $translation;
		}
		else{
			return vsprintf($translation, $params);
		}
	}

	/**
	 * Performs a curl request to an outside URL with the given method and data
	 *
	 * @param string $method Method of the request (get / post / delete)
	 *
	 * @param string $url URL to be called
	 *
	 * @param array $data Key / value array with parameters to be sent
	 *
	 * @return string|false Result of the curl request or false if the execution failed
	 */
	public static function curlRequest(string $method, string $url, array $data): string|false {
		$ch = curl_init();
		if ($method=='get') {
			$url .= '?';
			$params = [];
			foreach ($data as $key => $value) {
				array_push($params, $key.'='.$value);
			}
			$url .= implode('&', $params);
		}
		if ($method=='post') {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
		if ($method=='delete') {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		$result = curl_exec($ch);
		curl_close($ch);

		return $result;
	}

	/**
	 * Creates a slug (safe-text-string) from a given string (word or sentence)
	 *
	 * @param string $text Text to be slugified
	 *
	 * @param string $separator Character used to split words in case of a sentence is given
	 *
	 * @return string Slug of the given text
	 */
	public static function slugify(string $text, string $separator = '-'): string {
		$bad = [
			'À','à','Á','á','Â','â','Ã','ã','Ä','ä','Å','å','Ă','ă','Ą','ą',
			'Ć','ć','Č','č','Ç','ç',
			'Ď','ď','Đ','đ',
			'È','è','É','é','Ê','ê','Ë','ë','Ě','ě','Ę','ę',
			'Ğ','ğ',
			'Ì','ì','Í','í','Î','î','Ï','ï',
			'Ĺ','ĺ','Ľ','ľ','Ł','ł',
			'Ñ','ñ','Ň','ň','Ń','ń',
			'Ò','ò','Ó','ó','Ô','ô','Õ','õ','Ö','ö','Ø','ø','ő',
			'Ř','ř','Ŕ','ŕ',
			'Š','š','Ş','ş','Ś','ś',
			'Ť','ť','Ť','ť','Ţ','ţ',
			'Ù','ù','Ú','ú','Û','û','Ü','ü','Ů','ů',
			'Ÿ','ÿ','ý','Ý',
			'Ž','ž','Ź','ź','Ż','ż',
			'Þ','þ','Ð','ð','ß','Œ','œ','Æ','æ','µ',
			'”','“','‘','’',"'","\n","\r",'_','º','ª','¿'];

		$good = [
			'A','a','A','a','A','a','A','a','Ae','ae','A','a','A','a','A','a',
			'C','c','C','c','C','c',
			'D','d','D','d',
			'E','e','E','e','E','e','E','e','E','e','E','e',
			'G','g',
			'I','i','I','i','I','i','I','i',
			'L','l','L','l','L','l',
			'N','n','N','n','N','n',
			'O','o','O','o','O','o','O','o','Oe','oe','O','o','o',
			'R','r','R','r',
			'S','s','S','s','S','s',
			'T','t','T','t','T','t',
			'U','u','U','u','U','u','Ue','ue','U','u',
			'Y','y','Y','y',
			'Z','z','Z','z','Z','z',
			'TH','th','DH','dh','ss','OE','oe','AE','ae','u',
			'','','','','','','','-','','',''];

		// Convert special characters
		$text = str_replace($bad, $good, $text);

		// Convert special characters
		mb_convert_encoding($text, 'UTF-8', mb_list_encodings());
		$text = htmlentities($text);
		$text = preg_replace('/&([a-zA-Z])(uml|acute|grave|circ|tilde);/', '$1', $text);
		$text = html_entity_decode($text);

		$text = strtolower($text);

		// Strip all non word chars
		$text = preg_replace('/\W/', ' ', $text);

		// Replace all white space sections with a separator
		$text = preg_replace('/\ +/', $separator, $text);

		// Trim separators
		$text = trim($text, $separator);

		return $text;
	}

	/**
	 * Checks if "ofw" dir exists, creates otherwise, and checks if given subdir exists
	 *
	 * @param string $name Check of the subfolder to be checked
	 *
	 * @return void
	 */
	public static function checkOfw(string $name): void {
		global $core;
		$ofw_path = $core->config->getDir('ofw');
		if (!is_dir($ofw_path)) {
			mkdir($ofw_path);
		}
		$check_path = $core->config->getDir('ofw_'.$name);
		if (!is_dir($check_path)) {
			mkdir($check_path);
		}
	}

	/**
	 * Run a user defined task (app/task)
	 *
	 * @param string $task_name Name of the task
	 *
	 * @param array $params Array of parameters passed to the task
	 *
	 * @return bool Returns true after the task is complete or false if task file doesn't exist
	 */
	public static function runTask(string $task_name, array $params=[]): bool {
		global $core;
		$task_file = $core->config->getDir('app_task').$task_name.'.task.php';
		if (!file_exists($task_file)) {
			return false;
		}

		require_once $task_file;
		$task_name = "\\OsumiFramework\\App\\Task\\".$task_name."Task";
		$task = new $task_name;
		$task->loadTask();
		$task->run($params);

		return true;
	}

	/**
	 * Run a Framework specific task (ofw/task)
	 *
	 * @param string $task_name Name of the task
	 *
	 * @param array $params Array of parameters passed to the task
	 *
	 * @param bool $return Lets the task echo or captures everything and returns it
	 *
	 * @return array Returns the status ok/error if task was run and it's return messages if $return is set to true
	 */
	public static function runOFWTask(string $task_name, array $params=[], bool $return=false): array {
		global $core;
		$ret = [
			'status' => 'ok',
			'return' => ''
		];
		$task_file = $core->config->getDir('ofw_task').$task_name.'.task.php';
		if (!file_exists($task_file)) {
			$ret['status'] = 'error';
			return $ret;
		}

		require_once $task_file;
		$task_name = "\\OsumiFramework\\OFW\\Task\\".$task_name."Task";
		$task = new $task_name();
		$task->loadTask();
		if (!$return) {
			$task->run($params);
		}
		else {
			ob_start();
			$task->run($params);
			$ret['return'] = ob_get_contents();
			ob_end_clean();
		}

		return $ret;
	}

	/**
	 * Return version number of the Framework
	 *
	 * @return string Version number of the Framework (eg 5.0.0)
	 */
	public static function getVersion(): string {
		global $core;
		$version_file = $core->config->getDir('ofw_base').'composer.json';
		$version = json_decode( file_get_contents($version_file), true );
		return $version['version'];
	}

	/**
	 * Returns current versions information message
	 *
	 * @return string Current versions information message
	 */
	public static function getVersionInformation(): string {
		global $core;
		$version_file = $core->config->getDir('ofw_base').'composer.json';
		$version = json_decode( file_get_contents($version_file), true );
		return $version['extra']['version-description'];
	}

	/**
	 * Get user's IP address
	 *
	 * @return string User's IP address
	 */
	public static function getIPAddress(): string {
		// Whether ip is from the share internet
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		}
		// Whether ip is from the proxy
		elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		// Whether ip is from the remote address
		else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}

	/**
	 * Convert underscore notation (snake case) to camel case (eg id_user -> idUser)
	 *
	 * @param string $string Text string to convert
	 *
	 * @param bool $capitalizeFirstCharacter Should first letter be capitalized or not, defaults to no
	 *
	 * @return string Converted text string
	 */
	public static function underscoresToCamelCase(string $string, bool $capitalizeFirstCharacter = false): string {
		$str = str_replace('_', '', ucwords($string, '_'));

		if (!$capitalizeFirstCharacter) {
			$str = lcfirst($str);
		}

		return $str;
	}

	/**
	 * Convert camel case (idUser) or Pascal case (IdUser) notation to snake case (eg IdUser -> id_user)
	 *
	 * @param string $string Text string to convert
	 *
	 * @param string $glue Character to use between words, defaults to underscore (_)
	 *
	 * @return string Converted text string
	 */
	public static function toSnakeCase(string $str, string $glue = '_'): string {
		return ltrim(preg_replace_callback('/[A-Z]/', fn($matches) => $glue . strtolower($matches[0]), $str), $glue);
	}
}
