<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Core;

/**
 * Utility class to internationalize an application
 */
class OTranslate {
	private ?string $path = null;
	private ?string $lang = null;
	private array $headers = [];
	private array $translations = [];

	/**
	 * Set path to the PO file
	 *
	 * @param string $path Path to the PO file
	 *
	 * @return void
	 */
	public function setPath(string $path): void {
		$this->path = $path;
	}

	/**
	 * Get path to the PO file
	 *
	 * @return string Path to the PO file
	 */
	public function getPath(): string {
		return $this->path;
	}

	/**
	 * Set language code of the PO file
	 *
	 * @param string $lang Language code of the PO file (eg: en/es/eu)
	 *
	 * @return void
	 */
	public function setLang(string $lang): void {
		$this->lang = $lang;
		$this->headers['Language'] = $lang;
	}

	/**
	 * Get language code of the PO file
	 *
	 * @return string Language code of the PO file (eg: en/es/eu)
	 */
	public function getLang(): string {
		return $this->lang;
	}

	/**
	 * Get a translation by key.
	 *
	 * @param string $key Translation key.
	 *
	 * @return ?string Translation value or null if the key does not exist.
	 */
	public function getTranslation(string $key): ?string {
		$key = trim($key);

		return array_key_exists(
			$key,
			$this->translations
		)
			? $this->translations[$key]
			: null;
	}

	/**
	 * Set list of translations strings
	 *
	 * @param array $t List of translation strings
	 *
	 * @return void
	 */
	public function setTranslations(array $t): void {
		$this->translations = $t;
	}

	/**
	 * Get list of translation strings
	 *
	 * @return array List of translation strings
	 */
	public function getTranslations(): array {
		return $this->translations;
	}

	/**
	 * Set list of PO file headers
	 *
	 * @param array $h List of headers
	 *
	 * @return void
	 */
	public function setHeaders(array $h): void {
		$this->headers = $h;
	}

	/**
	 * Get list of PO file headers
	 *
	 * @return array List of headers
	 */
	public function getHeaders(): array {
		return $this->headers;
	}

	/**
	 * Create or edit a header
	 *
	 * @param string $key Name of the header
	 *
	 * @param string $header Value of the header
	 *
	 * @return void
	 */
	public function setHeader(string $key, string $header): void {
		$this->headers[$key] = $header;
	}

	/**
	 * Create or edit a translation
	 *
	 * @param string $key Text to be translated
	 *
	 * @param string $translation Translated text
	 *
	 * @return void
	 */
	public function setTranslation(string $key, string $translation): void {
		$this->translations[$key] = $translation;
	}

	/**
	 * Load translations from a PO file.
	 *
	 * The file must contain the standard PO header followed by translation
	 * entries. Header values are split only on the first colon so values may
	 * contain additional colons.
	 *
	 * @param string $path Path to the PO file.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException If the PO file does not exist or cannot be read.
	 * @throws \UnexpectedValueException If the PO file has an invalid structure.
	 */
	public function load(string $path): void {
		if (!is_file($path)) {
			throw new \RuntimeException(
				"PO file '{$path}' does not exist."
			);
		}

		$po = file(
			$path,
			FILE_IGNORE_NEW_LINES
		);

		if ($po === false) {
			throw new \RuntimeException(
				"Unable to read PO file '{$path}'."
			);
		}

		if (
			count($po) < 2 ||
			trim($po[0]) !== 'msgid ""' ||
			trim($po[1]) !== 'msgstr ""'
		) {
			throw new \UnexpectedValueException(
				"PO file '{$path}' has an invalid header."
			);
		}

		$this->path = $path;

		$translations = [];
		$headers = [];
		$current = [];
		$translation = [];
		$doing_keys = false;
		$doing_translations = false;

		foreach (
			array_slice(
				$po,
				2
			) as $line
		) {
			$trimmed_line = trim($line);

			if ($trimmed_line === '') {
				continue;
			}

			if (str_starts_with(
				$trimmed_line,
				'#'
			)) {
				continue;
			}

			if (str_starts_with(
				$trimmed_line,
				'"'
			)) {
				$value = substr(
					$trimmed_line,
					1,
					-1
				);

				if ($doing_keys) {
					$current[] = $value;

					continue;
				}

				if ($doing_translations) {
					$translation[] = $value;

					continue;
				}

				$header = explode(
					':',
					$value,
					2
				);

				if (count($header) !== 2) {
					throw new \UnexpectedValueException(
						"Invalid PO header line '{$value}' in '{$path}'."
					);
				}

				$header_name = trim($header[0]);
				$header_value = trim(
					str_replace(
						'\\n',
						'',
						$header[1]
					)
				);

				if ($header_name === '') {
					throw new \UnexpectedValueException(
						"PO file '{$path}' contains an empty header name."
					);
				}

				$headers[$header_name] = $header_value;

				if ($header_name === 'Language') {
					$this->lang = $header_value;
				}

				continue;
			}

			if (str_starts_with(
				$trimmed_line,
				'msgid'
			)) {
				if (
					$current !== [] &&
					$translation !== []
				) {
					$translations[implode(
						"\n",
						$current
					)] = implode(
						"\n",
						$translation
					);
				}

				$doing_keys = true;
				$doing_translations = false;

				$key = trim(
					substr(
						$trimmed_line,
						5
					)
				);

				$key = trim(
					$key,
					'"'
				);

				$current = [];
				$translation = [];

				if ($key !== '') {
					$current[] = $key;
				}

				continue;
			}

			if (str_starts_with(
				$trimmed_line,
				'msgstr'
			)) {
				$doing_keys = false;
				$doing_translations = true;

				$value = trim(
					substr(
						$trimmed_line,
						6
					)
				);

				$value = trim(
					$value,
					'"'
				);

				$translation = [];

				if ($value !== '') {
					$translation[] = $value;
				}
			}
		}

		if (
			$current !== [] &&
			$translation !== []
		) {
			$translations[implode(
				"\n",
				$current
			)] = implode(
				"\n",
				$translation
			);
		}

		$this->translations = $translations;
		$this->headers = $headers;
	}

	/**
	 * Save current loaded translations and headers into a file
	 *
	 * @param string | null $path Path of the file
	 *
	 * @return bool Returns if save operation was successful or not
	 */
	public function save(string | null $path = null): bool {
		if (is_null($path)) {
			$path = $this->path;
		}
		if (is_null($path)) {
			return false;
		}

		$str = "msgid \"\"\n";
		$str .= "msgstr \"\"\n";
		foreach ($this->headers as $key => $value) {
			$str .= "\"" . $key . ": " . $value . "\"\n";
		}
		$str .= "\n";
		foreach ($this->translations as $key => $value) {
			$str .= "msgid ";
			foreach (explode("\n", $key) as $key_part) {
				$str .= "\"" . $key_part . "\"\n";
			}
			$str .= "msgstr ";
			foreach (explode("\n", $value) as $value_part) {
				$str .= "\"" . $value_part . "\"\n";
			}
			$str .= "\n";
		}

		if (file_exists($path)) {
			unlink($path);
		}
		file_put_contents($path, $str);
		return true;
	}

	/**
	 * Create a new PO file
	 *
	 * @param string | null $path Path to the new PO file
	 *
	 * @param string $lang Language code of the new PO file (eg: en/es/eu)
	 *
	 * @return bool Returns if create operation was successful or not
	 */
	public function new(string | null $path = null, string $lang = 'en'): bool {
		if (is_null($path)) {
			$path = $this->path;
		}
		if (is_null($path)) {
			return false;
		}
		$this->path = $path;
		$this->lang = $lang;

		$this->headers = [
			'Project-Id-Version' => '',
			'POT-Creation-Date' => '',
			'PO-Revision-Date' => '',
			'Last-Translator' => '',
			'Language-Team' => '',
			'Language' => $this->lang,
			'MIME-Version: 1.0' => '',
			'Content-Type: text/plain; charset=UTF-8' => '',
			'Content-Transfer-Encoding: 8bit' => ''
		];

		return true;
	}
}
