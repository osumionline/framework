<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Core;

/**
 * Utility class to internationalize an application
 */
class OTranslate {
	private ?string $path = null;
	private ?string $lang = null;
	/**
	 * @var array<string, string>
	 */
	private array $headers = [];

	/**
	 * PHP may convert numeric string keys to integer array keys.
	 *
	 * @var array<array-key, string>
	 */
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
	 * Get the path of the current PO file.
	 *
	 * @return string|null PO file path or null if no file has been configured.
	 */
	public function getPath(): ?string {
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
	 * Get the language code of the current PO file.
	 *
	 * @return string|null Language code or null if it has not been defined.
	 */
	public function getLang(): ?string {
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
	 * Set the translation map.
	 *
	 * @param array<array-key, string> $translations Translation map.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If a translation value is not a string.
	 */
	public function setTranslations(array $translations): void {
		foreach ($translations as $value) {
			if (!is_string($value)) {
				throw new \InvalidArgumentException(
					'Translation values must be strings.'
				);
			}
		}

		$this->translations = $translations;
	}

	/**
	 * Get all translations.
	 *
	 * @return array<array-key, string> Translation map.
	 */
	public function getTranslations(): array {
		return $this->translations;
	}

	/**
	 * Set the PO file headers.
	 *
	 * @param array<string, string> $headers PO header map.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If a header name or value is not a string.
	 */
	public function setHeaders(array $headers): void {
		foreach ($headers as $key => $value) {
			if (
				!is_string($key) ||
				!is_string($value)
			) {
				throw new \InvalidArgumentException(
					'PO headers must contain string names and string values.'
				);
			}
		}

		$this->headers = $headers;
	}

	/**
	 * Get all PO file headers.
	 *
	 * @return array<string, string> PO header map.
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

		$translations = [];
		$headers = [];
		$lang = null;
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
					$lang = $header_value;
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

		$this->path = $path;
		$this->lang = $lang;
		$this->translations = $translations;
		$this->headers = $headers;
	}

	/**
	 * Save the current translations and headers to a PO file.
	 *
	 * If a path is provided, it becomes the current PO file path after a
	 * successful write.
	 *
	 * @param string|null $path Destination path or null to use the current path.
	 *
	 * @return bool True if the file was saved, false if no path is available.
	 *
	 * @throws \RuntimeException If the PO file cannot be written.
	 */
	public function save(?string $path = null): bool {
		$target_path = $path ?? $this->path;

		if ($target_path === null) {
			return false;
		}

		$content = "msgid \"\"\n";
		$content .= "msgstr \"\"\n";

		foreach ($this->headers as $key => $value) {
			$content .= '"'
				. $key
				. ': '
				. $value
				. "\\n\"\n";
		}

		$content .= "\n";

		foreach ($this->translations as $key => $value) {
			$key = (string) $key;

			$content .= 'msgid ';

			foreach (
				explode(
					"\n",
					$key
				) as $key_part
			) {
				$content .= '"'
					. $key_part
					. "\"\n";
			}

			$content .= 'msgstr ';

			foreach (
				explode(
					"\n",
					$value
				) as $value_part
			) {
				$content .= '"'
					. $value_part
					. "\"\n";
			}

			$content .= "\n";
		}

		if (
			file_put_contents(
				$target_path,
				$content,
				LOCK_EX
			) === false
		) {
			throw new \RuntimeException(
				"Unable to write PO file '{$target_path}'."
			);
		}

		$this->path = $target_path;

		return true;
	}

	/**
	 * Initialize a new PO document.
	 *
	 * The method resets the current translations and creates the standard PO
	 * headers for the requested language.
	 *
	 * @param string|null $path Path of the new PO file or null to reuse the current
	 *                          path.
	 * @param string $lang Language code of the PO file.
	 *
	 * @return bool True if the document was initialized, false if no path is
	 *              available.
	 */
	public function new(
		?string $path = null,
		string $lang = 'en'
	): bool {
		$target_path = $path ?? $this->path;

		if ($target_path === null) {
			return false;
		}

		$this->path = $target_path;
		$this->lang = $lang;
		$this->translations = [];

		$this->headers = [
			'Project-Id-Version' => '',
			'POT-Creation-Date' => '',
			'PO-Revision-Date' => '',
			'Last-Translator' => '',
			'Language-Team' => '',
			'Language' => $lang,
			'MIME-Version' => '1.0',
			'Content-Type' => 'text/plain; charset=UTF-8',
			'Content-Transfer-Encoding' => '8bit'
		];

		return true;
	}
}
