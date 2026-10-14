<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Migrations\Steps;

use Osumi\OsumiFramework\Migrations\Context\MigrationContext;
use Osumi\OsumiFramework\Migrations\Contract\MigrationStepInterface;

final class V9_12_0 implements MigrationStepInterface {
    private const string VERSION = '9.12.0';
    private const string MODEL_DIRECTORY = 'src/Model';

    /**
     * Get the framework version introduced by this migration.
     *
     * @return string Framework version.
     */
    public function getVersion(): string {
        return self::VERSION;
    }

    /**
     * Get a human-readable description of this migration.
     *
     * @return string Migration description.
     */
    public function getDescription(): string {
        return 'Migrate legacy OField::DATE model fields to OField::DATETIME.';
    }

    /**
     * Migrate DATE fields from their legacy DATETIME semantics.
     *
     * Before OFW 9.12, OField::DATE generated SQL DATETIME columns. Starting
     * with 9.12, OField::DATE represents a real SQL DATE and OField::DATETIME
     * represents SQL DATETIME.
     *
     * Existing OField::DATE declarations therefore become OField::DATETIME so
     * upgrading does not alter their database semantics.
     *
     * @param MigrationContext $context Migration execution context.
     *
     * @return void
     *
     * @throws \RuntimeException If the model directory cannot be inspected.
     */
    public function apply(MigrationContext $context): void {
        $model_files = $this->getModelFiles(
            $context->project_root
        );

        $changes = [];

        /*
         * Preflight every model before writing anything.
         */
        foreach ($model_files as $relative_path) {
            $content = $context->patcher->read(
                $relative_path
            );

            if ($content === null) {
                continue;
            }

            $migrated = $this->migrateModelContent(
                $content
            );

            if ($migrated !== $content) {
                $changes[$relative_path] = $migrated;
            }
        }

        $context->writeVerbose(
            sprintf(
                '[OFW] 9.12.0 preflight complete: %d model file(s), %d file(s) to migrate.',
                count($model_files),
                count($changes)
            )
        );

        foreach ($changes as $relative_path => $content) {
            $context->patcher->write(
                $relative_path,
                $content
            );
        }
    }

    /**
     * Get PHP model files from the standard application model directory.
     *
     * @param string $project_root Absolute project root.
     *
     * @return list<string> Project-relative model paths.
     *
     * @throws \RuntimeException If the directory cannot be read.
     */
    private function getModelFiles(
        string $project_root
    ): array {
        $directory = rtrim(
            $project_root,
            '/\\'
        )
            . '/'
            . self::MODEL_DIRECTORY;

        if (!is_dir($directory)) {
            return [];
        }

        $entries = scandir(
            $directory
        );

        if ($entries === false) {
            throw new \RuntimeException(
                "Unable to inspect model directory '"
                    . self::MODEL_DIRECTORY
                    . "'."
            );
        }

        $files = [];

        foreach ($entries as $entry) {
            if (
                $entry === '.' ||
                $entry === '..' ||
                strtolower(
                    pathinfo(
                        $entry,
                        PATHINFO_EXTENSION
                    )
                ) !== 'php'
            ) {
                continue;
            }

            $absolute_path = $directory
                . '/'
                . $entry;

            if (!is_file($absolute_path)) {
                continue;
            }

            $files[] = self::MODEL_DIRECTORY
                . '/'
                . $entry;
        }

        sort(
            $files
        );

        return $files;
    }

    /**
     * Replace legacy OField::DATE code references with OField::DATETIME.
     *
     * PHP tokenization preserves source formatting and allows comments and
     * string literals containing "OField::DATE" to remain untouched.
     *
     * @param string $content Model source code.
     *
     * @return string Migrated source code.
     */
    private function migrateModelContent(
        string $content
    ): string {
        $tokens = token_get_all(
            $content
        );

        $result = '';

        /**
         * @var list<array{
         *     id: int|null,
         *     text: string
         * }>
         */
        $previous_significant = [];

        foreach ($tokens as $token) {
            if (is_array($token)) {
                $id = $token[0];
                $text = $token[1];
            } else {
                $id = null;
                $text = $token;
            }

            if (
                $id === T_STRING &&
                $text === 'DATE' &&
                count($previous_significant) === 2 &&
                $previous_significant[0]['id'] === T_STRING &&
                $previous_significant[0]['text'] === 'OField' &&
                $previous_significant[1]['id'] === T_DOUBLE_COLON
            ) {
                $text = 'DATETIME';
            }

            $result .= $text;

            if (
                $id !== null &&
                in_array(
                    $id,
                    [
                        T_WHITESPACE,
                        T_COMMENT,
                        T_DOC_COMMENT
                    ],
                    true
                )
            ) {
                continue;
            }

            $previous_significant[] = [
                'id' => $id,
                'text' => $text
            ];

            if (count($previous_significant) > 2) {
                array_shift(
                    $previous_significant
                );
            }
        }

        return $result;
    }
}
