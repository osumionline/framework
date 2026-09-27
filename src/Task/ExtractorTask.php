<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Task;

use Osumi\OsumiFramework\Core\OTask;
use Osumi\OsumiFramework\Tools\OTools;

/**
 * Function to export an application with all its files to a single self-extracting PHP file.
 */
class ExtractorTask extends OTask {
	public function __toString() {
		return $this->getColors()->getColoredString(
			'extractor',
			'light_green'
		) . ': ' . OTools::getMessage('TASK_EXTRACTOR');
	}

	private ?string $base_dir = null;

	private array $folder_list = [
		'ofw',
		'public',
		'src',
		'vendor'
	];

	/**
	 * Scan a path recursively and return its regular files.
	 *
	 * Symbolic links are ignored to prevent exporting files located outside
	 * the application directory.
	 *
	 * @param string $path Path to scan.
	 * @param string[] $name List of found file names.
	 *
	 * @return string[] List of found file names.
	 *
	 * @throws \RuntimeException If a directory cannot be scanned.
	 */
	private function scanFileNameRecursivly(
		string $path = '',
		array &$name = []
	): array {
		if ($path === '') {
			if ($this->base_dir === null) {
				throw new \RuntimeException(
					'Extractor base directory has not been initialized.'
				);
			}

			$path = $this->base_dir;
		}

		$lists = scandir($path);

		if ($lists === false) {
			throw new \RuntimeException(
				"Could not scan directory '{$path}'."
			);
		}

		foreach ($lists as $file) {
			if ($file === '.' || $file === '..') {
				continue;
			}

			$item = $path . DIRECTORY_SEPARATOR . $file;

			if (is_link($item)) {
				continue;
			}

			if (is_dir($item)) {
				$this->scanFileNameRecursivly(
					$item,
					$name
				);
			} elseif (is_file($item)) {
				$name[] = $item;
			}
		}

		return $name;
	}

	/**
	 * Run the task.
	 *
	 * @param array $params Task parameters. The "silent" option suppresses
	 *                      informational output when set to "true".
	 *
	 * @return void
	 *
	 * @throws \RuntimeException If a source file cannot be read, a directory
	 *                           cannot be scanned or the extractor file cannot
	 *                           be generated.
	 */
	public function run(array $params = []): void {
		$silent = (
			array_key_exists('silent', $params) &&
			$params['silent'] === 'true'
		);

		OTools::checkOfw('export');

		$base_dir = $this->getConfig()->getDir('base');
		$export_dir = $this->getConfig()->getDir('ofw_export');
		$template_dir = $this->getConfig()->getDir('ofw_template');

		if (!is_string($base_dir) || $base_dir === '') {
			throw new \RuntimeException(
				'Invalid application base directory.'
			);
		}

		if (!is_string($export_dir) || $export_dir === '') {
			throw new \RuntimeException(
				'Invalid OFW export directory.'
			);
		}

		if (!is_string($template_dir) || $template_dir === '') {
			throw new \RuntimeException(
				'Invalid OFW template directory.'
			);
		}

		$this->base_dir = rtrim(
			$base_dir,
			'/\\'
		) . DIRECTORY_SEPARATOR;

		$destination = $export_dir . 'ofw_extractor.php';

		$path = $template_dir . 'extractor/extractor.php';

		$values = [
			'colors'      => $this->getColors(),
			'file_exists' => file_exists($destination),
			'num_files'   => 0,
			'num_folders' => 0
		];

		/*
		 * Remove a previously generated extractor before scanning "ofw".
		 *
		 * The new extractor is not created until all source files have been
		 * scanned, preventing the extractor from including itself.
		 */
		if (
			$values['file_exists'] &&
			!unlink($destination)
		) {
			throw new \RuntimeException(
				"Could not remove previous extractor file '{$destination}'."
			);
		}

		$folders = [];
		$files = [];

		/*
		 * Files located directly in the application root.
		 *
		 * Symbolic links are deliberately ignored so the extractor never
		 * follows a link to a file located outside the project.
		 */
		$base_files = [
			'of',
			'.gitignore',
			'composer.json',
			'composer.lock',
			'LICENSE',
			'README.md'
		];

		foreach ($base_files as $file) {
			$file_path = $this->base_dir . $file;

			if (
				!is_file($file_path) ||
				is_link($file_path)
			) {
				continue;
			}

			$content = OTools::fileToBase64($file_path);

			if ($content === null) {
				throw new \RuntimeException(
					"Could not read file '{$file_path}' while generating the extractor."
				);
			}

			$files[$file] = $content;
		}

		/*
		 * Traverse application folders.
		 */
		foreach ($this->folder_list as $folder) {
			$folder_path = $this->base_dir . $folder;

			/*
			 * Missing folders are simply ignored, preserving the historical
			 * extractor behavior.
			 *
			 * Top-level symbolic links are also ignored.
			 */
			if (
				!is_dir($folder_path) ||
				is_link($folder_path)
			) {
				continue;
			}

			$file_names = [];

			$this->scanFileNameRecursivly(
				$folder_path,
				$file_names
			);

			foreach ($file_names as $file_name) {
				/*
				 * Generate a relative and portable path.
				 */
				$key = substr(
					$file_name,
					strlen($this->base_dir)
				);

				$key = str_replace(
					DIRECTORY_SEPARATOR,
					'/',
					$key
				);

				$content = OTools::fileToBase64($file_name);

				if ($content === null) {
					throw new \RuntimeException(
						"Could not read file '{$file_name}' while generating the extractor."
					);
				}

				$files[$key] = $content;

				/*
				 * Build the complete list of directories required by the
				 * extracted file.
				 *
				 * Example:
				 *
				 * src/Component/Admin/User.php
				 *
				 * Produces:
				 *
				 * src
				 * src/Component
				 * src/Component/Admin
				 */
				$folder_name = dirname($key);

				if ($folder_name === '.') {
					continue;
				}

				$parts = explode(
					'/',
					$folder_name
				);

				$current_folder = '';

				foreach ($parts as $part) {
					$current_folder = $current_folder === ''
						? $part
						: $current_folder . '/' . $part;

					if (
						!in_array(
							$current_folder,
							$folders,
							true
						)
					) {
						$folders[] = $current_folder;
					}
				}
			}
		}

		$values['num_files'] = count($files);
		$values['num_folders'] = count($folders);

		/*
		 * var_export() is deliberately used instead of manually generating
		 * quoted PHP strings. This guarantees that file and directory names
		 * are correctly escaped in the generated PHP file.
		 */
		$extractor_content = "<?php\n\n";
		$extractor_content .= "declare(strict_types=1);\n\n";
		$extractor_content .= '$files = '
			. var_export($files, true)
			. ";\n\n";
		$extractor_content .= '$folders = '
			. var_export($folders, true)
			. ";\n\n";

		/*
		 * The self-extracting PHP code.
		 */
		$extractor_content .= <<<'PHP'
/**
 * Build a safe destination path inside the extraction directory.
 *
 * Relative traversal components and existing symbolic links are rejected.
 *
 * @param string $base_dir Extraction base directory.
 * @param string $relative_path Relative file or directory path.
 *
 * @return string Safe absolute destination path.
 *
 * @throws RuntimeException If the path is unsafe.
 */
function getSafePath(
	string $base_dir,
	string $relative_path
): string {
	$relative_path = str_replace(
		'\\',
		'/',
		$relative_path
	);

	$parts = explode(
		'/',
		$relative_path
	);

	$current_path = $base_dir;

	foreach ($parts as $part) {
		if ($part === '' || $part === '.') {
			continue;
		}

		if ($part === '..') {
			throw new RuntimeException(
				"Unsafe relative path '{$relative_path}'."
			);
		}

		$current_path .= DIRECTORY_SEPARATOR . $part;

		if (is_link($current_path)) {
			throw new RuntimeException(
				"Refusing to extract through symbolic link '{$current_path}'."
			);
		}
	}

	return $current_path;
}

/**
 * Decode a Base64 data URI and save it to a file.
 *
 * @param string $base64_string Base64 data URI.
 * @param string $filename Destination file.
 *
 * @return void
 *
 * @throws RuntimeException If the data is invalid or the file cannot be written.
 */
function base64ToFile(
	string $base64_string,
	string $filename
): void {
	$data = explode(
		',',
		$base64_string,
		2
	);

	if (count($data) !== 2) {
		throw new RuntimeException(
			"Invalid Base64 data for file '{$filename}'."
		);
	}

	$content = base64_decode(
		$data[1],
		true
	);

	if ($content === false) {
		throw new RuntimeException(
			"Could not decode Base64 data for file '{$filename}'."
		);
	}

	if (
		file_put_contents(
			$filename,
			$content
		) === false
	) {
		throw new RuntimeException(
			"Could not write extracted file '{$filename}'."
		);
	}
}

$basedir = realpath(__DIR__);

if ($basedir === false) {
	throw new RuntimeException(
		'Could not resolve extractor base directory.'
	);
}


PHP;

		/*
		 * Keep user-facing extractor messages translated using OFW locale
		 * strings. var_export() safely converts them to PHP string literals.
		 */
		$base_folder_message = var_export(
			OTools::getMessage(
				'TASK_EXTRACTOR_BASE_FOLDER'
			),
			true
		);

		$create_folders_message = var_export(
			OTools::getMessage(
				'TASK_EXTRACTOR_CREATE_FOLDERS'
			),
			true
		);

		$create_files_message = var_export(
			OTools::getMessage(
				'TASK_EXTRACTOR_CREATE_FILES'
			),
			true
		);

		$extractor_content .= 'echo '
			. $base_folder_message
			. ' . ": " . $basedir . "\n";'
			. "\n";

		$extractor_content .= 'echo '
			. $create_folders_message
			. ' . " (" . count($folders) . ")\n";'
			. "\n\n";

		$extractor_content .= <<<'PHP'
foreach ($folders as $i => $folder) {
	echo '  '
		. ($i + 1)
		. '/'
		. count($folders)
		. ' - '
		. $folder
		. "\n";

	$directory = getSafePath(
		$basedir,
		$folder
	);

	if (
		!is_dir($directory) &&
		!mkdir(
			$directory,
			0755,
			true
		)
	) {
		throw new RuntimeException(
			"Could not create directory '{$directory}'."
		);
	}
}


PHP;

		$extractor_content .= 'echo '
			. $create_files_message
			. ' . " (" . count($files) . ")\n";'
			. "\n\n";

		$extractor_content .= <<<'PHP'
$cont = 1;

foreach ($files as $key => $file) {
	echo '  '
		. $cont
		. '/'
		. count($files)
		. ' - '
		. $key
		. "\n";

	$filename = getSafePath(
		$basedir,
		$key
	);

	base64ToFile(
		$file,
		$filename
	);

	$cont++;
}

PHP;

		/*
		 * Write the completed extractor only after all source files have been
		 * collected. This prevents the output file from being included in its
		 * own contents.
		 */
		if (
			file_put_contents(
				$destination,
				$extractor_content,
				LOCK_EX
			) === false
		) {
			throw new \RuntimeException(
				"Could not create extractor file '{$destination}'."
			);
		}

		if (!$silent) {
			echo OTools::getPartial(
				$path,
				$values
			);
		}
	}
}
