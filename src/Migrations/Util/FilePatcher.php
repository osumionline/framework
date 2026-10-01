<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Migrations\Util;

use Closure;

final class FilePatcher {
    private string $project_root;
    private string $tmp_directory;
    private string $backup_root;

    /**
     * @var array<string, array{existed: bool, backup: string|null}>
     */
    private array $original_files = [];

    /**
     * @var list<string>
     */
    private array $change_order = [];

    /**
     * @var list<string>
     */
    private array $created_directories = [];

    private bool $finished = false;

    /**
     * Create a transactional project file patcher.
     *
     * @param string $project_root Absolute project root path.
     * @param string $tmp_directory OFW temporary directory used for backups.
     * @param bool $dry_run Whether filesystem changes must be simulated only.
     * @param bool $verbose Whether verbose patch messages are enabled.
     * @param (Closure(string): void)|null $logger Optional patch message writer.
     *
     * @throws \RuntimeException If the project root cannot be resolved or the
     *                           temporary directory is outside the project.
     * @throws \Random\RandomException If the backup transaction identifier cannot be generated.
     */
    public function __construct(
        string $project_root,
        string $tmp_directory,
        private readonly bool $dry_run = false,
        private readonly bool $verbose = false,
        private readonly ?Closure $logger = null
    ) {
        $this->project_root = $this->canonicalizeAbsolutePath(
            $project_root,
            true
        );

        $this->tmp_directory = $this->canonicalizeAbsolutePath(
            $tmp_directory,
            false
        );

        if (!$this->isInsideProject($this->tmp_directory)) {
            throw new \RuntimeException(
                "Migration temporary directory '{$tmp_directory}' must be inside the project root."
            );
        }

        $this->assertNoSymlinkSegments(
            $this->tmp_directory
        );

        $this->backup_root = $this->tmp_directory
            . '/migrations/'
            . date('Ymd-His')
            . '-'
            . bin2hex(
                random_bytes(8)
            );
    }

    /**
     * Check whether a project file exists.
     *
     * @param string $relative_path Project-relative file path.
     *
     * @return bool Whether the file exists.
     *
     * @throws \InvalidArgumentException If the path is unsafe.
     */
    public function exists(string $relative_path): bool {
        return is_file(
            $this->getAbsolutePath(
                $relative_path
            )
        );
    }

    /**
     * Read a project file.
     *
     * @param string $relative_path Project-relative file path.
     *
     * @return string|null File contents or null when the file does not exist.
     *
     * @throws \InvalidArgumentException If the path is unsafe.
     * @throws \RuntimeException If the file cannot be read.
     */
    public function read(string $relative_path): ?string {
        $absolute_path = $this->getAbsolutePath(
            $relative_path
        );

        if (!is_file($absolute_path)) {
            return null;
        }

        $content = file_get_contents(
            $absolute_path
        );

        if ($content === false) {
            throw new \RuntimeException(
                "Unable to read migration file '{$relative_path}'."
            );
        }

        return $content;
    }

    /**
     * Write a project file transactionally.
     *
     * The original file is backed up before its first modification. New contents
     * are written through a temporary file in the destination directory and moved
     * atomically into place. Writing the same contents again is a no-op.
     *
     * @param string $relative_path Project-relative file path.
     * @param string $content New file contents.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If the path is unsafe.
     * @throws \RuntimeException If the file or backup cannot be written.
     */
    public function write(
        string $relative_path,
        string $content
    ): void {
        $relative_path = $this->normalizeRelativePath(
            $relative_path
        );

        $absolute_path = $this->getAbsolutePath(
            $relative_path
        );

        $current_content = $this->read(
            $relative_path
        );

        if ($current_content === $content) {
            return;
        }

        $this->assertOpen();

        if ($this->dry_run) {
            $this->writeLog(
                "[OFW] [dry-run] write {$relative_path}"
            );

            return;
        }

        $this->rememberOriginal(
            $relative_path
        );

        $this->ensureParentDirectory(
            dirname(
                $absolute_path
            )
        );

        $this->writeFileContents(
            $absolute_path,
            $content,
            "Unable to write migration file '{$relative_path}'."
        );

        $this->writeVerbose(
            "[OFW] write {$relative_path}"
        );
    }

    /**
     * Delete a project file transactionally.
     *
     * @param string $relative_path Project-relative file path.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If the path is unsafe.
     * @throws \RuntimeException If the file or backup cannot be updated.
     */
    public function delete(string $relative_path): void {
        $relative_path = $this->normalizeRelativePath(
            $relative_path
        );

        $absolute_path = $this->getAbsolutePath(
            $relative_path
        );

        if (!is_file($absolute_path)) {
            return;
        }

        $this->assertOpen();

        if ($this->dry_run) {
            $this->writeLog(
                "[OFW] [dry-run] delete {$relative_path}"
            );

            return;
        }

        $this->rememberOriginal(
            $relative_path
        );

        if (!unlink($absolute_path)) {
            throw new \RuntimeException(
                "Unable to delete migration file '{$relative_path}'."
            );
        }

        $this->writeVerbose(
            "[OFW] delete {$relative_path}"
        );
    }

    /**
     * Commit all file changes and retain their backup transaction on disk.
     *
     * @return void
     *
     * @throws \JsonException If the backup manifest cannot be encoded.
     * @throws \RuntimeException If the backup manifest cannot be written.
     */
    public function commit(): void {
        if (
            $this->dry_run ||
            $this->finished
        ) {
            $this->finished = true;

            return;
        }

        if ($this->change_order !== []) {
            $this->ensureDirectory(
                $this->backup_root
            );

            $manifest = json_encode(
                [
                    'project_root' => $this->project_root,
                    'changed_files' => $this->change_order,
                    'created_directories' => $this->created_directories
                ],
                JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_SLASHES |
                    JSON_THROW_ON_ERROR
            );

            $manifest_file = $this->backup_root
                . '/manifest.json';

            $this->writeFileContents(
                $manifest_file,
                $manifest . "\n",
                "Unable to write migration backup manifest '{$manifest_file}'."
            );
        }

        $this->finished = true;
    }

    /**
     * Roll back all file changes made by this patcher instance.
     *
     * @return void
     *
     * @throws \RuntimeException If an original file cannot be restored.
     */
    public function rollback(): void {
        if ($this->finished) {
            return;
        }

        if ($this->dry_run) {
            $this->finished = true;

            return;
        }

        foreach (
            array_reverse(
                $this->change_order
            ) as $relative_path
        ) {
            $original = $this->original_files[$relative_path];

            $absolute_path = $this->getAbsolutePath(
                $relative_path
            );

            if ($original['existed']) {
                $backup_file = $original['backup'];

                if (
                    $backup_file === null ||
                    !is_file($backup_file)
                ) {
                    throw new \RuntimeException(
                        "Migration backup for '{$relative_path}' is missing."
                    );
                }

                $this->ensureParentDirectory(
                    dirname(
                        $absolute_path
                    )
                );

                if (
                    !copy(
                        $backup_file,
                        $absolute_path
                    )
                ) {
                    throw new \RuntimeException(
                        "Unable to restore migration file '{$relative_path}'."
                    );
                }
            } elseif (
                is_file($absolute_path) &&
                !unlink($absolute_path)
            ) {
                throw new \RuntimeException(
                    "Unable to remove migration-created file '{$relative_path}'."
                );
            }
        }

        foreach (
            array_reverse(
                $this->created_directories
            ) as $directory
        ) {
            if (
                is_dir($directory) &&
                $this->isDirectoryEmpty(
                    $directory
                ) &&
                !rmdir($directory)
            ) {
                throw new \RuntimeException(
                    "Unable to remove migration-created directory '{$directory}'."
                );
            }
        }

        $this->finished = true;

        $this->writeVerbose(
            '[OFW] Migration file changes rolled back.'
        );
    }

    /**
     * Get the backup directory assigned to this patch transaction.
     *
     * @return string Backup directory path.
     */
    public function getBackupRoot(): string {
        return $this->backup_root;
    }

    /**
     * Write file contents atomically while converting native filesystem failures
     * into a stable migration exception.
     *
     * Contents are first written to a temporary file located in the same directory
     * as the destination. The temporary file is then renamed over the destination,
     * preventing readers from observing partially written migration files.
     *
     * Existing Unix permissions are preserved. New files receive the permissions
     * that a regular file creation would obtain from the current process umask.
     *
     * @param string $absolute_path Absolute target file path.
     * @param string $content File contents.
     * @param string $error_message Migration-specific error message.
     *
     * @return void
     *
     * @throws \RuntimeException If the temporary file cannot be created, written,
     *                           prepared or atomically moved into place.
     */
    private function writeFileContents(
        string $absolute_path,
        string $content,
        string $error_message
    ): void {
        $directory = dirname(
            $absolute_path
        );

        $temporary_path = null;

        try {
            $temporary_path = tempnam(
                $directory,
                'ofw'
            );

            if ($temporary_path === false) {
                throw new \RuntimeException(
                    $error_message
                );
            }

            $target_directory = realpath(
                $directory
            );

            $temporary_directory = realpath(
                dirname(
                    $temporary_path
                )
            );

            if (
                $target_directory === false ||
                $temporary_directory === false
            ) {
                throw new \RuntimeException(
                    $error_message
                );
            }

            $target_directory = $this->normalizeAbsolutePath(
                $target_directory
            );

            $temporary_directory = $this->normalizeAbsolutePath(
                $temporary_directory
            );

            if (PHP_OS_FAMILY === 'Windows') {
                $target_directory = strtolower(
                    $target_directory
                );

                $temporary_directory = strtolower(
                    $temporary_directory
                );
            }

            if ($temporary_directory !== $target_directory) {
                throw new \RuntimeException(
                    $error_message
                );
            }

            $result = file_put_contents(
                $temporary_path,
                $content,
                LOCK_EX
            );

            if ($result === false) {
                throw new \RuntimeException(
                    $error_message
                );
            }

            if (PHP_OS_FAMILY !== 'Windows') {
                $permissions = is_file(
                    $absolute_path
                )
                    ? fileperms(
                        $absolute_path
                    )
                    : null;

                $target_permissions = $permissions === false ||
                    $permissions === null
                    ? 0666 & ~umask()
                    : $permissions & 0777;

                if (!chmod(
                    $temporary_path,
                    $target_permissions
                )) {
                    throw new \RuntimeException(
                        $error_message
                    );
                }
            }

            if (!@rename(
                $temporary_path,
                $absolute_path
            )) {
                throw new \RuntimeException(
                    $error_message
                );
            }

            $temporary_path = null;
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                $error_message,
                0,
                $exception
            );
        } finally {
            if (
                $temporary_path !== null &&
                is_file($temporary_path)
            ) {
                try {
                    unlink(
                        $temporary_path
                    );
                } catch (\Throwable) {
                    /*
				 * Keep the original migration failure as the authoritative
				 * exception. A leftover temporary file is preferable to
				 * hiding the actual write failure.
				 */
                }
            }
        }
    }

    /**
     * Save the original state of a file before its first modification.
     *
     * @param string $relative_path Project-relative file path.
     *
     * @return void
     *
     * @throws \RuntimeException If the original file cannot be backed up.
     */
    private function rememberOriginal(string $relative_path): void {
        if (
            array_key_exists(
                $relative_path,
                $this->original_files
            )
        ) {
            return;
        }

        $absolute_path = $this->getAbsolutePath(
            $relative_path
        );

        $exists = is_file(
            $absolute_path
        );

        $backup_file = null;

        if ($exists) {
            $backup_file = $this->backup_root
                . '/files/'
                . $relative_path;

            $this->ensureDirectory(
                dirname(
                    $backup_file
                )
            );

            if (
                !copy(
                    $absolute_path,
                    $backup_file
                )
            ) {
                throw new \RuntimeException(
                    "Unable to back up migration file '{$relative_path}'."
                );
            }
        } else {
            $this->ensureDirectory(
                $this->backup_root
            );
        }

        $this->original_files[$relative_path] = [
            'existed' => $exists,
            'backup' => $backup_file
        ];

        $this->change_order[] = $relative_path;
    }

    /**
     * Ensure the patch transaction is still open.
     *
     * @return void
     *
     * @throws \LogicException If the transaction has already finished.
     */
    private function assertOpen(): void {
        if ($this->finished) {
            throw new \LogicException(
                'Migration file patch transaction has already finished.'
            );
        }
    }

    /**
     * Build a safe absolute path inside the project root.
     *
     * @param string $relative_path Project-relative path.
     *
     * @return string Absolute path.
     *
     * @throws \InvalidArgumentException If the path is unsafe.
     */
    private function getAbsolutePath(string $relative_path): string {
        $relative_path = $this->normalizeRelativePath(
            $relative_path
        );

        $absolute_path = $this->project_root
            . '/'
            . $relative_path;

        $this->assertNoSymlinkSegments(
            $absolute_path
        );

        return $absolute_path;
    }

    /**
     * Normalize and validate a project-relative migration path.
     *
     * @param string $relative_path Project-relative path.
     *
     * @return string Normalized path using forward slashes.
     *
     * @throws \InvalidArgumentException If the path is absolute, empty or contains
     *                                   unsafe path segments.
     */
    private function normalizeRelativePath(string $relative_path): string {
        if (
            str_contains(
                $relative_path,
                "\0"
            )
        ) {
            throw new \InvalidArgumentException(
                'Migration path cannot contain null bytes.'
            );
        }

        $relative_path = str_replace(
            '\\',
            '/',
            trim(
                $relative_path
            )
        );

        if (
            $relative_path === '' ||
            str_starts_with(
                $relative_path,
                '/'
            ) ||
            preg_match(
                '/^[A-Za-z]:\//D',
                $relative_path
            ) === 1
        ) {
            throw new \InvalidArgumentException(
                "Migration path '{$relative_path}' must be project-relative."
            );
        }

        $parts = explode(
            '/',
            $relative_path
        );

        $normalized_parts = [];

        foreach ($parts as $part) {
            if (
                $part === '' ||
                $part === '.'
            ) {
                continue;
            }

            if ($part === '..') {
                throw new \InvalidArgumentException(
                    "Migration path '{$relative_path}' contains an unsafe parent segment."
                );
            }

            $normalized_parts[] = $part;
        }

        if ($normalized_parts === []) {
            throw new \InvalidArgumentException(
                'Migration path cannot be empty.'
            );
        }

        return implode(
            '/',
            $normalized_parts
        );
    }

    /**
     * Canonicalize an absolute filesystem path.
     *
     * Existing paths are resolved completely through realpath(). For paths that
     * do not exist yet, the nearest existing parent is resolved and the missing
     * path segments are appended afterwards. This prevents Windows short 8.3
     * paths and long paths from being treated as different locations.
     *
     * @param string $path Absolute filesystem path.
     * @param bool $must_exist Whether the complete path must already exist.
     *
     * @return string Canonical absolute path.
     *
     * @throws \RuntimeException If the path or its nearest existing parent cannot
     *                           be resolved.
     */
    private function canonicalizeAbsolutePath(
        string $path,
        bool $must_exist
    ): string {
        $path = $this->normalizeAbsolutePath(
            $path
        );

        $resolved = realpath(
            $path
        );

        if ($resolved !== false) {
            return $this->normalizeAbsolutePath(
                $resolved
            );
        }

        if ($must_exist) {
            throw new \RuntimeException(
                "Unable to resolve filesystem path '{$path}'."
            );
        }

        $missing_parts = [];
        $current = $path;

        while (!file_exists($current)) {
            $parent = $this->normalizeAbsolutePath(
                dirname(
                    $current
                )
            );

            if ($parent === $current) {
                throw new \RuntimeException(
                    "Unable to resolve existing parent for filesystem path '{$path}'."
                );
            }

            $missing_parts[] = basename(
                $current
            );

            $current = $parent;
        }

        $resolved_parent = realpath(
            $current
        );

        if ($resolved_parent === false) {
            throw new \RuntimeException(
                "Unable to resolve existing parent '{$current}' for filesystem path '{$path}'."
            );
        }

        $canonical = $this->normalizeAbsolutePath(
            $resolved_parent
        );

        foreach (
            array_reverse(
                $missing_parts
            ) as $part
        ) {
            $canonical .= '/'
                . $part;
        }

        return $canonical;
    }

    /**
     * Normalize an absolute filesystem path for cross-platform comparisons.
     *
     * @param string $path Absolute filesystem path.
     *
     * @return string Normalized path.
     */
    private function normalizeAbsolutePath(string $path): string {
        return rtrim(
            str_replace(
                '\\',
                '/',
                $path
            ),
            '/'
        );
    }

    /**
     * Check whether an absolute path belongs to the configured project root.
     *
     * @param string $path Absolute path.
     *
     * @return bool Whether the path belongs to the project.
     */
    private function isInsideProject(string $path): bool {
        $project_root = $this->project_root;
        $candidate = $path;

        if (PHP_OS_FAMILY === 'Windows') {
            $project_root = strtolower(
                $project_root
            );

            $candidate = strtolower(
                $candidate
            );
        }

        return $candidate === $project_root ||
            str_starts_with(
                $candidate . '/',
                $project_root . '/'
            );
    }

    /**
     * Reject paths that traverse symbolic links inside the project.
     *
     * @param string $absolute_path Absolute project path.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If an existing path segment is a symbolic link.
     */
    private function assertNoSymlinkSegments(string $absolute_path): void {
        $relative_path = substr(
            $absolute_path,
            strlen(
                $this->project_root
            )
        );

        $parts = array_values(
            array_filter(
                explode(
                    '/',
                    $relative_path
                ),
                static fn(string $part): bool => $part !== ''
            )
        );

        $current = $this->project_root;

        foreach ($parts as $part) {
            $current .= '/'
                . $part;

            if (is_link($current)) {
                throw new \InvalidArgumentException(
                    "Migration path '{$absolute_path}' traverses symbolic link '{$current}'."
                );
            }

            if (!file_exists($current)) {
                break;
            }
        }
    }

    /**
     * Ensure a parent directory exists and track directories created by the migration.
     *
     * @param string $directory Absolute directory path.
     *
     * @return void
     *
     * @throws \RuntimeException If the directory cannot be created.
     */
    private function ensureParentDirectory(string $directory): void {
        if (is_dir($directory)) {
            return;
        }

        $missing = [];
        $current = $directory;

        while (!is_dir($current)) {
            $missing[] = $current;

            $parent = dirname(
                $current
            );

            if ($parent === $current) {
                throw new \RuntimeException(
                    "Unable to resolve parent directory for '{$directory}'."
                );
            }

            $current = $parent;
        }

        foreach (
            array_reverse(
                $missing
            ) as $missing_directory
        ) {
            if (
                !mkdir(
                    $missing_directory,
                    0755
                ) &&
                !is_dir($missing_directory)
            ) {
                throw new \RuntimeException(
                    "Unable to create migration directory '{$missing_directory}'."
                );
            }

            $this->created_directories[] = $this->normalizeAbsolutePath(
                $missing_directory
            );
        }
    }

    /**
     * Ensure an internal backup directory exists.
     *
     * @param string $directory Absolute backup directory path.
     *
     * @return void
     *
     * @throws \RuntimeException If the directory cannot be created.
     */
    private function ensureDirectory(string $directory): void {
        if (
            !is_dir($directory) &&
            !mkdir(
                $directory,
                0755,
                true
            ) &&
            !is_dir($directory)
        ) {
            throw new \RuntimeException(
                "Unable to create migration backup directory '{$directory}'."
            );
        }
    }

    /**
     * Check whether a directory has no entries other than dot entries.
     *
     * @param string $directory Absolute directory path.
     *
     * @return bool Whether the directory is empty.
     *
     * @throws \RuntimeException If the directory cannot be scanned.
     */
    private function isDirectoryEmpty(string $directory): bool {
        $items = scandir(
            $directory
        );

        if ($items === false) {
            throw new \RuntimeException(
                "Unable to scan migration directory '{$directory}'."
            );
        }

        return count($items) === 2;
    }

    /**
     * Write a patcher message when a logger is available.
     *
     * @param string $message Message to write.
     *
     * @return void
     */
    private function writeLog(string $message): void {
        if ($this->logger !== null) {
            ($this->logger)(
                $message
            );
        }
    }

    /**
     * Write a patcher message only when verbose mode is enabled.
     *
     * @param string $message Message to write.
     *
     * @return void
     */
    private function writeVerbose(string $message): void {
        if ($this->verbose) {
            $this->writeLog(
                $message
            );
        }
    }
}
