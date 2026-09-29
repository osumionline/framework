<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tools;

/**
 * OTools - Utility class with auxiliary tools
 */
class OTools {
	/**
	 * Generate a cryptographically secure random string.
	 *
	 * Available options:
	 * - num: Number of characters to generate. Defaults to 5.
	 * - lower: Include lowercase letters.
	 * - upper: Include uppercase letters.
	 * - numbers: Include numbers.
	 * - special: Include special characters.
	 *
	 * @param array $options Random string options.
	 *
	 * @return string Generated random string.
	 *
	 * @throws \InvalidArgumentException If the supplied options are invalid or no
	 *                                   character group has been selected.
	 * @throws \Random\RandomException If a secure random value cannot be generated.
	 */
	public static function getRandomCharacters(array $options): string {
		$num = $options['num'] ?? 5;
		$lower = $options['lower'] ?? false;
		$upper = $options['upper'] ?? false;
		$numbers = $options['numbers'] ?? false;
		$special = $options['special'] ?? false;

		if (
			!is_int($num) ||
			$num < 1
		) {
			throw new \InvalidArgumentException(
				'Random string length must be a positive integer.'
			);
		}

		foreach (
			[
				'lower' => $lower,
				'upper' => $upper,
				'numbers' => $numbers,
				'special' => $special
			] as $option => $value
		) {
			if (!is_bool($value)) {
				throw new \InvalidArgumentException(
					"Random string option '{$option}' must be boolean."
				);
			}
		}

		$characters = '';

		if ($lower) {
			$characters .= 'abcdefghijklmnopqrstuvwxyz';
		}

		if ($upper) {
			$characters .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		}

		if ($numbers) {
			$characters .= '0123456789';
		}

		if ($special) {
			$characters .= '!@#$%^&*()';
		}

		if ($characters === '') {
			throw new \InvalidArgumentException(
				'At least one random string character group must be enabled.'
			);
		}

		$result = '';
		$max_index = strlen($characters) - 1;

		for ($i = 0; $i < $num; $i++) {
			$result .= $characters[random_int(
				0,
				$max_index
			)];
		}

		return $result;
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
	public static function getTemplate(string $path, string $html, array $values): ?string {
		if ($path != '') {
			if (file_exists($path)) {
				$html = file_get_contents($path);
			} else {
				return null;
			}
		}

		foreach ($values as $key => $value) {
			$html = str_ireplace('{{' . $key . '}}', $value, $html);
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
	public static function getPartial(string $path, array $values): string | null {
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
	 * Check whether a slash-separated component path contains only valid PHP
	 * identifiers.
	 *
	 * @param string $name Component path to validate.
	 *
	 * @return bool Whether the component path is valid.
	 */
	private static function isValidComponentPath(string $name): bool {
		if ($name === '') {
			return false;
		}

		$parts = explode(
			'/',
			$name
		);

		foreach ($parts as $part) {
			if (
				preg_match(
					'/^[A-Za-z_][A-Za-z0-9_]*$/D',
					$part
				) !== 1
			) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get a component's content anywhere, even in a template-less execution.
	 *
	 * @param string $name Slash-separated component path.
	 * @param array $values Values passed to the component.
	 *
	 * @return string|null Loaded component content.
	 */
	public static function getComponent(
		string $name,
		array $values = []
	): ?string {
		global $core;

		if (!self::isValidComponentPath($name)) {
			return null;
		}

		$parts = explode(
			'/',
			$name
		);

		$component_name = array_pop($parts);

		if ($component_name === null) {
			return null;
		}

		$component_file = $core->config->getDir('app_component')
			. $name
			. '/'
			. $component_name
			. 'Component.php';

		$output = self::getPartial(
			$component_file,
			$values
		);

		if ($output === null) {
			return 'ERROR: File '
				. $name
				. ' not found';
		}

		return $output;
	}

	/**
	 * Get a model object's JSON representation.
	 *
	 * @param mixed $obj Object to generate.
	 * @param array $exclude Fields to exclude.
	 * @param array $empty Fields to return empty.
	 *
	 * @return string JSON representation or "null" when the object cannot be
	 *                generated.
	 */
	public static function getModelComponent(
		mixed $obj,
		array $exclude = [],
		array $empty = []
	): string {
		return (!is_null($obj) && method_exists($obj, 'generate'))
			? $obj->generate('json', $exclude, $empty)
			: 'null';
	}

	/**
	 * Get a file's content as a Base64 data URI.
	 *
	 * @param string $filename Path of the file to be loaded.
	 *
	 * @return string|null File content as a Base64 data URI or null if the file
	 *                     cannot be read or its MIME type cannot be determined.
	 */
	public static function fileToBase64(string $filename): ?string {
		if (
			$filename === '' ||
			str_contains($filename, "\0") ||
			!is_file($filename) ||
			!is_readable($filename)
		) {
			return null;
		}

		$content = file_get_contents($filename);

		if ($content === false) {
			return null;
		}

		$finfo = finfo_open(FILEINFO_MIME_TYPE);

		if ($finfo === false) {
			return null;
		}

		$mime_type = finfo_file(
			$finfo,
			$filename
		);

		if ($mime_type === false) {
			return null;
		}

		return 'data:'
			. $mime_type
			. ';base64,'
			. base64_encode($content);
	}

	/**
	 * Save a Base64 data URI to a file.
	 *
	 * @param string $base64_string Base64 data URI containing the file content.
	 * @param string $filename Destination file path.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the Base64 data URI is invalid.
	 * @throws \RuntimeException If the destination file cannot be written.
	 */
	public static function base64ToFile(
		string $base64_string,
		string $filename
	): void {
		if (
			$filename === '' ||
			str_contains($filename, "\0")
		) {
			throw new \InvalidArgumentException(
				'Invalid destination filename.'
			);
		}

		$data = explode(
			',',
			$base64_string,
			2
		);

		if (
			count($data) !== 2 ||
			!str_starts_with($data[0], 'data:') ||
			!str_ends_with(
				strtolower($data[0]),
				';base64'
			)
		) {
			throw new \InvalidArgumentException(
				'Invalid Base64 data URI.'
			);
		}

		$content = base64_decode(
			$data[1],
			true
		);

		if ($content === false) {
			throw new \InvalidArgumentException(
				'Invalid Base64 encoded content.'
			);
		}

		if (
			file_put_contents(
				$filename,
				$content,
				LOCK_EX
			) === false
		) {
			throw new \RuntimeException(
				"Could not write Base64 content to file '{$filename}'."
			);
		}
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
	 * Decode Base64URL data.
	 *
	 * @param string $data Data to decode.
	 * @param bool $strict Whether invalid Base64 characters should cause failure.
	 *
	 * @return string|false Decoded data or false if decoding fails.
	 */
	public static function base64urlDecode(
		string $data,
		bool $strict = false
	): string | false {
		$b64 = strtr($data, '-_', '+/');

		return base64_decode($b64, $strict);
	}

	/**
	 * Validate and escape a URL used in generated BBCode HTML.
	 *
	 * Only HTTP, HTTPS and relative URLs are accepted.
	 *
	 * @param string $value URL value.
	 *
	 * @return string|null Escaped safe URL or null if the URL is unsafe.
	 */
	private static function getSafeBBCodeUrl(string $value): ?string {
		$url = html_entity_decode(
			trim($value),
			ENT_QUOTES | ENT_HTML5,
			'UTF-8'
		);

		if (
			$url === '' ||
			preg_match('/[\x00-\x1F\x7F]/', $url) === 1
		) {
			return null;
		}

		$parts = parse_url($url);

		if ($parts === false) {
			return null;
		}

		if (
			array_key_exists('scheme', $parts) &&
			(
				!is_string($parts['scheme']) ||
				!in_array(
					strtolower($parts['scheme']),
					[
						'http',
						'https'
					],
					true
				)
			)
		) {
			return null;
		}

		return htmlspecialchars(
			$url,
			ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
			'UTF-8'
		);
	}

	/**
	 * Validate a CSS color used in generated BBCode HTML.
	 *
	 * Named colors and hexadecimal colors are supported.
	 *
	 * @param string $value Color value.
	 *
	 * @return string|null Safe color or null if the color is invalid.
	 */
	private static function getSafeBBCodeColor(string $value): ?string {
		$color = html_entity_decode(
			trim($value),
			ENT_QUOTES | ENT_HTML5,
			'UTF-8'
		);

		if (
			preg_match(
				'/^(?:#[0-9a-f]{3,4}|#[0-9a-f]{6}|#[0-9a-f]{8}|[a-z]+)$/iD',
				$color
			) !== 1
		) {
			return null;
		}

		return $color;
	}

	/**
	 * Parse a string containing supported BBCode tags.
	 *
	 * Supported tags are i, b, u, img, url, mailto and color. Raw HTML is
	 * escaped before parsing. URLs are restricted to HTTP, HTTPS and relative
	 * URLs.
	 *
	 * @param string $str String containing BBCode.
	 *
	 * @return string Safe HTML generated from the supplied BBCode.
	 *
	 * @throws \RuntimeException If an internal BBCode regular expression fails.
	 */
	public static function bbcode(string $str): string {
		$str = htmlspecialchars(
			$str,
			ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
			'UTF-8'
		);

		$parsed = preg_replace(
			[
				'/\[i\](.*?)\[\/i\]/is',
				'/\[b\](.*?)\[\/b\]/is',
				'/\[u\](.*?)\[\/u\]/is'
			],
			[
				'<i>$1</i>',
				'<b>$1</b>',
				'<u>$1</u>'
			],
			$str
		);

		if ($parsed === null) {
			throw new \RuntimeException(
				'Could not parse BBCode formatting tags.'
			);
		}

		$parsed_url = preg_replace_callback(
			'/\[url=(.*?)\](.*?)\[\/url\]/is',
			static function (array $matches): string {
				$url = self::getSafeBBCodeUrl(
					$matches[1]
				);

				if ($url === null) {
					return $matches[2];
				}

				return '<a href="'
					. $url
					. '" target="_blank" rel="noopener noreferrer">'
					. $matches[2]
					. '</a>';
			},
			$parsed
		);

		if ($parsed_url === null) {
			throw new \RuntimeException(
				'Could not parse BBCode URL tags.'
			);
		}

		$parsed_image = preg_replace_callback(
			'/\[img\](.*?)\[\/img\]/is',
			static function (array $matches): string {
				$url = self::getSafeBBCodeUrl(
					$matches[1]
				);

				if ($url === null) {
					return '';
				}

				return '<img src="'
					. $url
					. '" alt="">';
			},
			$parsed_url
		);

		if ($parsed_image === null) {
			throw new \RuntimeException(
				'Could not parse BBCode image tags.'
			);
		}

		$parsed_mailto = preg_replace_callback(
			'/\[mailto=(.*?)\](.*?)\[\/mailto\]/is',
			static function (array $matches): string {
				$email = html_entity_decode(
					trim($matches[1]),
					ENT_QUOTES | ENT_HTML5,
					'UTF-8'
				);

				if (
					filter_var(
						$email,
						FILTER_VALIDATE_EMAIL
					) === false
				) {
					return $matches[2];
				}

				$safe_email = htmlspecialchars(
					$email,
					ENT_QUOTES
						| ENT_SUBSTITUTE
						| ENT_HTML5,
					'UTF-8'
				);

				return '<a href="mailto:'
					. $safe_email
					. '">'
					. $matches[2]
					. '</a>';
			},
			$parsed_image
		);

		if ($parsed_mailto === null) {
			throw new \RuntimeException(
				'Could not parse BBCode mailto tags.'
			);
		}

		$parsed_color = preg_replace_callback(
			'/\[color=(.*?)\](.*?)\[\/color\]/is',
			static function (array $matches): string {
				$color = self::getSafeBBCodeColor(
					$matches[1]
				);

				if ($color === null) {
					return $matches[2];
				}

				return '<span style="color:'
					. $color
					. '">'
					. $matches[2]
					. '</span>';
			},
			$parsed_mailto
		);

		if ($parsed_color === null) {
			throw new \RuntimeException(
				'Could not parse BBCode color tags.'
			);
		}

		return $parsed_color;
	}

	/**
	 * Show the framework error page if no custom page has been configured.
	 *
	 * @param array<string, mixed> $res Information about the error.
	 * @param string $mode Error mode (403, 404, 405, 500, general or view).
	 *
	 * @return void
	 */
	public static function showErrorPage(array $res, string $mode): void {
		global $core;

		if (!is_null($core->config->getErrorPage($mode))) {
			header('Location:' . $core->config->getErrorPage($mode));
			exit;
		}

		$params = [
			'mode'    => $mode,
			'version' => self::getVersion(),
			'title'   => $core->config->getDefaultTitle(),
			'message' => array_key_exists('message', $res) ? $res['message'] : '',
			'res'     => $res
		];

		if ($params['title'] === '') {
			$params['title'] = 'Osumi Framework';
		}
		$path = $core->config->getDir('ofw_template') . 'error.php';

		header($_SERVER['SERVER_PROTOCOL'] . ' ' . $core->getHttpStatus());
		echo self::getPartial($path, $params);
		exit;
	}

	/**
	 * Get a framework specific localized message
	 *
	 * @param string $key Key code of the message
	 *
	 * @param array | null $params Key / value array with parameters to be rendered on the message
	 *
	 * @return string | null Localized message with parameters rendered or null if message was not found
	 */
	public static function getMessage(string $key, array | null $params = null): string | null {
		global $core;

		$translation = $core->translate->getTranslation($key);
		if (is_null($translation)) {
			return null;
		}

		$translation = str_ireplace("\\n", "\n", $translation);

		if (is_null($params)) {
			return $translation;
		} else {
			return vsprintf($translation, $params);
		}
	}

	/**
	 * Performs a cURL request to an external URL with the given method and data.
	 *
	 * @param string $method HTTP method of the request (get / post / delete).
	 * @param string $url URL to be called.
	 * @param array $data Key/value array with parameters to be sent.
	 *
	 * @return string|false Result of the cURL request or false if the execution failed.
	 */
	public static function curlRequest(string $method, string $url, array $data): string|false {
		$ch = curl_init();

		if ($ch === false) {
			return false;
		}

		if ($method === 'get' && count($data) > 0) {
			$query = http_build_query($data);
			$separator = str_contains($url, '?') ? '&' : '?';
			$url .= $separator . $query;
		}

		if ($method === 'post') {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}

		if ($method === 'delete') {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

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
			'À',
			'à',
			'Á',
			'á',
			'Â',
			'â',
			'Ã',
			'ã',
			'Ä',
			'ä',
			'Å',
			'å',
			'Ă',
			'ă',
			'Ą',
			'ą',
			'Ć',
			'ć',
			'Č',
			'č',
			'Ç',
			'ç',
			'Ď',
			'ď',
			'Đ',
			'đ',
			'È',
			'è',
			'É',
			'é',
			'Ê',
			'ê',
			'Ë',
			'ë',
			'Ě',
			'ě',
			'Ę',
			'ę',
			'Ğ',
			'ğ',
			'Ì',
			'ì',
			'Í',
			'í',
			'Î',
			'î',
			'Ï',
			'ï',
			'Ĺ',
			'ĺ',
			'Ľ',
			'ľ',
			'Ł',
			'ł',
			'Ñ',
			'ñ',
			'Ň',
			'ň',
			'Ń',
			'ń',
			'Ò',
			'ò',
			'Ó',
			'ó',
			'Ô',
			'ô',
			'Õ',
			'õ',
			'Ö',
			'ö',
			'Ø',
			'ø',
			'ő',
			'Ř',
			'ř',
			'Ŕ',
			'ŕ',
			'Š',
			'š',
			'Ş',
			'ş',
			'Ś',
			'ś',
			'Ť',
			'ť',
			'Ť',
			'ť',
			'Ţ',
			'ţ',
			'Ù',
			'ù',
			'Ú',
			'ú',
			'Û',
			'û',
			'Ü',
			'ü',
			'Ů',
			'ů',
			'Ÿ',
			'ÿ',
			'ý',
			'Ý',
			'Ž',
			'ž',
			'Ź',
			'ź',
			'Ż',
			'ż',
			'Þ',
			'þ',
			'Ð',
			'ð',
			'ß',
			'Œ',
			'œ',
			'Æ',
			'æ',
			'µ',
			'”',
			'“',
			'‘',
			'’',
			"'",
			"\n",
			"\r",
			'_',
			'º',
			'ª',
			'¿'
		];

		$good = [
			'A',
			'a',
			'A',
			'a',
			'A',
			'a',
			'A',
			'a',
			'Ae',
			'ae',
			'A',
			'a',
			'A',
			'a',
			'A',
			'a',
			'C',
			'c',
			'C',
			'c',
			'C',
			'c',
			'D',
			'd',
			'D',
			'd',
			'E',
			'e',
			'E',
			'e',
			'E',
			'e',
			'E',
			'e',
			'E',
			'e',
			'E',
			'e',
			'G',
			'g',
			'I',
			'i',
			'I',
			'i',
			'I',
			'i',
			'I',
			'i',
			'L',
			'l',
			'L',
			'l',
			'L',
			'l',
			'N',
			'n',
			'N',
			'n',
			'N',
			'n',
			'O',
			'o',
			'O',
			'o',
			'O',
			'o',
			'O',
			'o',
			'Oe',
			'oe',
			'O',
			'o',
			'o',
			'R',
			'r',
			'R',
			'r',
			'S',
			's',
			'S',
			's',
			'S',
			's',
			'T',
			't',
			'T',
			't',
			'T',
			't',
			'U',
			'u',
			'U',
			'u',
			'U',
			'u',
			'Ue',
			'ue',
			'U',
			'u',
			'Y',
			'y',
			'Y',
			'y',
			'Z',
			'z',
			'Z',
			'z',
			'Z',
			'z',
			'TH',
			'th',
			'DH',
			'dh',
			'ss',
			'OE',
			'oe',
			'AE',
			'ae',
			'u',
			'',
			'',
			'',
			'',
			'',
			'',
			'',
			'-',
			'',
			'',
			''
		];

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
	 * Checks if the "ofw" directory and the requested subdirectory exist,
	 * creating them when necessary.
	 *
	 * @param string $name Name of the OFW subdirectory to be checked.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException If a required directory cannot be created.
	 */
	public static function checkOfw(string $name): void {
		global $core;

		$ofw_path = $core->config->getDir('ofw');

		if (
			!is_dir($ofw_path) &&
			!mkdir($ofw_path, 0755, true)
		) {
			throw new \RuntimeException(
				"Could not create OFW directory '{$ofw_path}'."
			);
		}

		$check_path = $core->config->getDir('ofw_' . $name);

		if (
			!is_dir($check_path) &&
			!mkdir($check_path, 0755, true)
		) {
			throw new \RuntimeException(
				"Could not create OFW directory '{$check_path}'."
			);
		}
	}

	/**
	 * Check whether a task name can safely be used as a PHP class and file name.
	 *
	 * @param string $task_name Task name to validate.
	 *
	 * @return bool Whether the task name is valid.
	 */
	private static function isValidTaskName(string $task_name): bool {
		return preg_match(
			'/^[A-Za-z_][A-Za-z0-9_]*$/D',
			$task_name
		) === 1;
	}

	/**
	 * Run a user-defined application task.
	 *
	 * @param string $task_name Task name.
	 * @param array<array-key, mixed> $params Parameters passed to the task.
	 *
	 * @return bool True if the task was executed, false otherwise.
	 */
	public static function runTask(string $task_name, array $params = []): bool {
		global $core;

		if (!self::isValidTaskName($task_name)) {
			return false;
		}

		$class_name = ucfirst($task_name) . 'Task';
		$task_file = $core->config->getDir('app_task')
			. $class_name
			. '.php';

		if (!is_file($task_file)) {
			return false;
		}

		require_once $task_file;

		$task_class = '\\Osumi\\OsumiFramework\\App\\Task\\'
			. $class_name;

		if (
			!class_exists($task_class) ||
			!is_subclass_of(
				$task_class,
				\Osumi\OsumiFramework\Core\OTask::class
			)
		) {
			return false;
		}

		$task = new $task_class();
		$task->loadTask();
		$task->run($params);

		return true;
	}

	/**
	 * Run a Framework task.
	 *
	 * @param string $task_name Task name.
	 * @param array<string, string|false> $params Parameters passed to the task.
	 * @param bool $return Whether task output should be captured and returned.
	 *
	 * @return array{
	 *     status: string,
	 *     return: string
	 * } Task execution result.
	 */
	public static function runOFWTask(string $task_name, array $params = [], bool $return = false): array {
		global $core;

		$ret = [
			'status' => 'ok',
			'return' => ''
		];

		if (!self::isValidTaskName($task_name)) {
			$ret['status'] = 'error';

			return $ret;
		}

		$class_name = ucfirst($task_name) . 'Task';
		$task_file = $core->config->getDir('ofw_task')
			. $class_name
			. '.php';

		if (!is_file($task_file)) {
			$ret['status'] = 'error';

			return $ret;
		}

		require_once $task_file;

		$task_class = '\\Osumi\\OsumiFramework\\Task\\'
			. $class_name;

		if (
			!class_exists($task_class) ||
			!is_subclass_of(
				$task_class,
				\Osumi\OsumiFramework\Core\OTask::class
			)
		) {
			$ret['status'] = 'error';

			return $ret;
		}

		$task = new $task_class();
		$task->loadTask();

		if (!$return) {
			$task->run($params);

			return $ret;
		}

		ob_start();

		try {
			$task->run($params);

			$output = ob_get_contents();

			if ($output !== false) {
				$ret['return'] = $output;
			}
		} finally {
			ob_end_clean();
		}

		return $ret;
	}

	/**
	 * Load Framework Composer metadata.
	 *
	 * @return array Composer metadata.
	 *
	 * @throws \JsonException If composer.json contains invalid JSON.
	 * @throws \RuntimeException If composer.json cannot be read.
	 */
	private static function getComposerData(): array {
		global $core;

		$composer_file = $core->config->getDir('ofw_base') . 'composer.json';
		$composer_content = file_get_contents($composer_file);

		if ($composer_content === false) {
			throw new \RuntimeException(
				"Unable to read Composer file '{$composer_file}'."
			);
		}

		$composer_data = json_decode(
			$composer_content,
			true,
			512,
			JSON_THROW_ON_ERROR
		);

		if (!is_array($composer_data)) {
			throw new \RuntimeException(
				"Invalid Composer data in '{$composer_file}'."
			);
		}

		return $composer_data;
	}

	/**
	 * Return version number of the Framework.
	 *
	 * @return string Framework version number.
	 *
	 * @throws \JsonException If composer.json contains invalid JSON.
	 * @throws \RuntimeException If composer.json cannot be read or has an invalid structure.
	 */
	public static function getVersion(): string {
		$composer_data = self::getComposerData();

		if (
			!array_key_exists('version', $composer_data) ||
			!is_string($composer_data['version'])
		) {
			throw new \RuntimeException(
				'Framework version is not defined in composer.json.'
			);
		}

		return $composer_data['version'];
	}

	/**
	 * Return current Framework version information.
	 *
	 * @return string Framework version information.
	 *
	 * @throws \JsonException If composer.json contains invalid JSON.
	 * @throws \RuntimeException If composer.json cannot be read or has an invalid structure.
	 */
	public static function getVersionInformation(): string {
		$composer_data = self::getComposerData();

		if (
			!array_key_exists('extra', $composer_data) ||
			!is_array($composer_data['extra']) ||
			!array_key_exists('version-description', $composer_data['extra']) ||
			!is_string($composer_data['extra']['version-description'])
		) {
			throw new \RuntimeException(
				'Framework version information is not defined in composer.json.'
			);
		}

		return $composer_data['extra']['version-description'];
	}

	/**
	 * Get the remote IP address of the current connection.
	 *
	 * Forwarded client IP headers are deliberately ignored because they cannot be
	 * trusted unless the request comes through an explicitly configured trusted
	 * proxy.
	 *
	 * @return string Valid remote IP address or an empty string if it cannot be
	 *                determined.
	 */
	public static function getIPAddress(): string {
		$remote_address = $_SERVER['REMOTE_ADDR'] ?? '';

		if (
			!is_string($remote_address) ||
			filter_var(
				$remote_address,
				FILTER_VALIDATE_IP
			) === false
		) {
			return '';
		}

		return $remote_address;
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
