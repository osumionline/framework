<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Migrations\Util;

use ParseError;
use PhpToken;

final class LegacyPhpSourceTransformer {
    /**
     * Transform legacy DTO and ORequest usages in one PHP source file.
     *
     * @param string $relative_path Project-relative source path.
     * @param string $source PHP source.
     *
     * @return string Migrated PHP source.
     *
     * @throws \RuntimeException If the source is invalid or contains a legacy
     *                           ORequest mutation API that cannot be migrated safely.
     */
    public function transform(
        string $relative_path,
        string $source
    ): string {
        $tokens = $this->tokenize(
            $relative_path,
            $source
        );

        $imports = $this->extractImports(
            $tokens,
            $relative_path
        );

        $replacements = [];

        foreach ($tokens as $index => $token) {
            if ($token->id !== T_OBJECT_OPERATOR) {
                continue;
            }

            $method_index = $this->nextSignificantIndex(
                $tokens,
                $index + 1
            );

            if ($method_index === null) {
                continue;
            }

            $method_token = $tokens[$method_index];

            if ($method_token->id !== T_STRING) {
                continue;
            }

            if (
                in_array(
                    $method_token->text,
                    [
                        'getFilters',
                        'setFilter',
                        'setFilters'
                    ],
                    true
                )
            ) {
                throw new \RuntimeException(
                    "Legacy ORequest method '{$method_token->text}()' in '{$relative_path}:{$method_token->line}' cannot be migrated automatically."
                );
            }

            if ($method_token->text === 'getFilter') {
                $replacements[] = [
                    'start' => $method_token->pos,
                    'length' => strlen(
                        $method_token->text
                    ),
                    'text' => 'getMiddleware'
                ];
            }
        }

        $this->collectDtoFieldReplacements(
            $tokens,
            $imports,
            $replacements
        );

        return $this->applyReplacements(
            $source,
            $replacements
        );
    }

    /**
     * Collect named ODTOField argument replacements.
     *
     * @param list<PhpToken> $tokens PHP tokens.
     * @param array<string, string> $imports Imported class aliases to FQCNs.
     * @param list<array{start: int, length: int, text: string}> $replacements Collected replacements.
     *
     * @return void
     */
    private function collectDtoFieldReplacements(
        array $tokens,
        array $imports,
        array &$replacements
    ): void {
        $token_count = count(
            $tokens
        );

        for (
            $index = 0;
            $index < $token_count;
            $index++
        ) {
            if ($tokens[$index]->id !== T_ATTRIBUTE) {
                continue;
            }

            $attribute_index = $this->nextSignificantIndex(
                $tokens,
                $index + 1
            );

            if ($attribute_index === null) {
                continue;
            }

            $attribute_token = $tokens[$attribute_index];

            if (!$this->isDtoFieldName(
                $attribute_token,
                $imports
            )) {
                continue;
            }

            $open_index = $this->nextSignificantIndex(
                $tokens,
                $attribute_index + 1
            );

            if (
                $open_index === null ||
                $tokens[$open_index]->text !== '('
            ) {
                continue;
            }

            $close_index = $this->findMatchingToken(
                $tokens,
                $open_index,
                '(',
                ')'
            );

            $parentheses_depth = 0;
            $bracket_depth = 0;
            $brace_depth = 0;

            for (
                $position = $open_index + 1;
                $position < $close_index;
                $position++
            ) {
                $token = $tokens[$position];

                if ($token->text === '(') {
                    $parentheses_depth++;
                    continue;
                }

                if ($token->text === ')') {
                    $parentheses_depth--;
                    continue;
                }

                if ($token->text === '[') {
                    $bracket_depth++;
                    continue;
                }

                if ($token->text === ']') {
                    $bracket_depth--;
                    continue;
                }

                if ($token->text === '{') {
                    $brace_depth++;
                    continue;
                }

                if ($token->text === '}') {
                    $brace_depth--;
                    continue;
                }

                if (
                    $parentheses_depth !== 0 ||
                    $bracket_depth !== 0 ||
                    $brace_depth !== 0 ||
                    $token->id !== T_STRING ||
                    !in_array(
                        $token->text,
                        [
                            'filter',
                            'filterProperty'
                        ],
                        true
                    )
                ) {
                    continue;
                }

                $colon_index = $this->nextSignificantIndex(
                    $tokens,
                    $position + 1
                );

                if (
                    $colon_index === null ||
                    $tokens[$colon_index]->text !== ':'
                ) {
                    continue;
                }

                $replacements[] = [
                    'start' => $token->pos,
                    'length' => strlen(
                        $token->text
                    ),
                    'text' => $token->text === 'filter'
                        ? 'middleware'
                        : 'middlewareProperty'
                ];
            }

            $index = $close_index;
        }
    }

    /**
     * Check whether a token identifies ODTOField.
     *
     * @param PhpToken $token PHP token.
     * @param array<string, string> $imports Imported class aliases to FQCNs.
     *
     * @return bool Whether the token identifies ODTOField.
     */
    private function isDtoFieldName(
        PhpToken $token,
        array $imports
    ): bool {
        if (
            !in_array(
                $token->id,
                [
                    T_STRING,
                    T_NAME_QUALIFIED,
                    T_NAME_FULLY_QUALIFIED
                ],
                true
            )
        ) {
            return false;
        }

        $name = ltrim(
            $token->text,
            '\\'
        );

        if (
            $name === 'ODTOField' ||
            str_ends_with(
                $name,
                '\\ODTOField'
            )
        ) {
            return true;
        }

        return $token->id === T_STRING &&
            ($imports[$token->text] ?? null) === 'Osumi\\OsumiFramework\\DTO\\ODTOField';
    }

    /**
     * Extract class imports from PHP source.
     *
     * @param list<PhpToken> $tokens PHP tokens.
     * @param string $relative_path Project-relative source path.
     *
     * @return array<string, string> Alias to imported FQCN.
     *
     * @throws \RuntimeException If a grouped ODTOField import is used.
     */
    private function extractImports(
        array $tokens,
        string $relative_path
    ): array {
        $imports = [];
        $brace_depth = 0;
        $token_count = count(
            $tokens
        );

        for (
            $index = 0;
            $index < $token_count;
            $index++
        ) {
            $token = $tokens[$index];

            if ($token->text === '{') {
                $brace_depth++;
                continue;
            }

            if ($token->text === '}') {
                $brace_depth--;
                continue;
            }

            if (
                $brace_depth !== 0 ||
                $token->id !== T_USE
            ) {
                continue;
            }

            $next_index = $this->nextSignificantIndex(
                $tokens,
                $index + 1
            );

            if (
                $next_index === null ||
                $tokens[$next_index]->text === '(' ||
                in_array(
                    $tokens[$next_index]->id,
                    [
                        T_FUNCTION,
                        T_CONST
                    ],
                    true
                )
            ) {
                continue;
            }

            $statement_end = $this->findStatementEnd(
                $tokens,
                $index,
                $relative_path
            );

            $statement_text = '';
            $is_grouped = false;

            for (
                $position = $index + 1;
                $position < $statement_end;
                $position++
            ) {
                $statement_text .= $tokens[$position]->text;
                $is_grouped = $is_grouped ||
                    $tokens[$position]->text === '{';
            }

            if ($is_grouped) {
                $normalized_statement = str_replace(
                    [' ', "\t", "\r", "\n"],
                    '',
                    $statement_text
                );

                if (
                    str_contains(
                        $normalized_statement,
                        'Osumi\\OsumiFramework\\DTO\\{'
                    ) &&
                    str_contains(
                        $normalized_statement,
                        'ODTOField'
                    )
                ) {
                    throw new \RuntimeException(
                        "Grouped ODTOField imports in '{$relative_path}:{$token->line}' are not supported by the automatic migration."
                    );
                }

                $index = $statement_end;
                continue;
            }

            $name_index = $this->nextSignificantIndex(
                $tokens,
                $index + 1
            );

            if ($name_index === null) {
                continue;
            }

            $name_token = $tokens[$name_index];

            if (
                !in_array(
                    $name_token->id,
                    [
                        T_NAME_QUALIFIED,
                        T_NAME_FULLY_QUALIFIED,
                        T_STRING
                    ],
                    true
                )
            ) {
                continue;
            }

            $fqcn = ltrim(
                $name_token->text,
                '\\'
            );

            $alias = null;

            for (
                $position = $name_index + 1;
                $position < $statement_end;
                $position++
            ) {
                if ($tokens[$position]->id !== T_AS) {
                    continue;
                }

                $alias_index = $this->nextSignificantIndex(
                    $tokens,
                    $position + 1
                );

                if (
                    $alias_index !== null &&
                    $alias_index < $statement_end &&
                    $tokens[$alias_index]->id === T_STRING
                ) {
                    $alias = $tokens[$alias_index]->text;
                }

                break;
            }

            if ($alias === null) {
                $last_separator = strrpos(
                    $fqcn,
                    '\\'
                );

                $alias = $last_separator === false
                    ? $fqcn
                    : substr(
                        $fqcn,
                        $last_separator + 1
                    );
            }

            $imports[$alias] = $fqcn;
            $index = $statement_end;
        }

        return $imports;
    }

    /**
     * Find the semicolon ending a top-level use statement.
     *
     * @param list<PhpToken> $tokens PHP tokens.
     * @param int $start Use token index.
     * @param string $relative_path Source path.
     *
     * @return int Statement-ending semicolon token index.
     *
     * @throws \RuntimeException If no semicolon exists.
     */
    private function findStatementEnd(
        array $tokens,
        int $start,
        string $relative_path
    ): int {
        $token_count = count(
            $tokens
        );

        for (
            $index = $start + 1;
            $index < $token_count;
            $index++
        ) {
            if ($tokens[$index]->text === ';') {
                return $index;
            }
        }

        throw new \RuntimeException(
            "Unterminated use statement in '{$relative_path}:{$tokens[$start]->line}'."
        );
    }

    /**
     * Tokenize PHP source while converting parse failures into migration errors.
     *
     * @param string $relative_path Project-relative source path.
     * @param string $source PHP source.
     *
     * @return list<PhpToken> PHP tokens.
     *
     * @throws \RuntimeException If the source contains invalid PHP syntax.
     */
    private function tokenize(
        string $relative_path,
        string $source
    ): array {
        try {
            return PhpToken::tokenize(
                $source,
                TOKEN_PARSE
            );
        } catch (ParseError $exception) {
            throw new \RuntimeException(
                "PHP source '{$relative_path}' contains invalid syntax.",
                0,
                $exception
            );
        }
    }

    /**
     * Find the next token excluding whitespace and comments.
     *
     * @param list<PhpToken> $tokens PHP tokens.
     * @param int $start Starting token index.
     *
     * @return int|null Significant token index or null.
     */
    private function nextSignificantIndex(
        array $tokens,
        int $start
    ): ?int {
        $token_count = count(
            $tokens
        );

        for (
            $index = $start;
            $index < $token_count;
            $index++
        ) {
            if (!$tokens[$index]->isIgnorable()) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Find the closing token matching an opening delimiter.
     *
     * @param list<PhpToken> $tokens PHP tokens.
     * @param int $open_index Opening token index.
     * @param string $open Opening delimiter.
     * @param string $close Closing delimiter.
     *
     * @return int Matching closing token index.
     *
     * @throws \RuntimeException If no matching delimiter exists.
     */
    private function findMatchingToken(
        array $tokens,
        int $open_index,
        string $open,
        string $close
    ): int {
        $depth = 0;
        $token_count = count(
            $tokens
        );

        for (
            $index = $open_index;
            $index < $token_count;
            $index++
        ) {
            if ($tokens[$index]->text === $open) {
                $depth++;
                continue;
            }

            if ($tokens[$index]->text !== $close) {
                continue;
            }

            $depth--;

            if ($depth === 0) {
                return $index;
            }
        }

        throw new \RuntimeException(
            "Unmatched '{$open}' delimiter in PHP source."
        );
    }

    /**
     * Apply non-overlapping source replacements from the end of the file.
     *
     * @param string $source Original source.
     * @param list<array{start: int, length: int, text: string}> $replacements Source replacements.
     *
     * @return string Updated source.
     */
    private function applyReplacements(
        string $source,
        array $replacements
    ): string {
        usort(
            $replacements,
            static fn(array $left, array $right): int => $right['start'] <=> $left['start']
        );

        foreach ($replacements as $replacement) {
            $source = substr_replace(
                $source,
                $replacement['text'],
                $replacement['start'],
                $replacement['length']
            );
        }

        return $source;
    }
}
