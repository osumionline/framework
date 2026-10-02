<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Migrations\Util;

use ParseError;
use PhpToken;
use Osumi\OsumiFramework\Migrations\ValueObject\LegacyFilterDefinition;

final class LegacyRouteTransformer {
    /**
     * @var array<string, LegacyFilterDefinition>
     */
    private array $definitions_by_fqcn = [];

    /**
     * Create a route transformer for discovered legacy Filters.
     *
     * @param list<LegacyFilterDefinition> $definitions Legacy Filter definitions.
     */
    public function __construct(array $definitions) {
        foreach ($definitions as $definition) {
            $this->definitions_by_fqcn[$definition->filter_fqcn] = $definition;
        }
    }

    /**
     * Transform legacy ORoute Filter arguments into before middlewares.
     *
     * Only literal Filter class lists are migrated automatically. Dynamic
     * expressions are rejected because their runtime contents cannot be known
     * safely without executing application code.
     *
     * @param string $relative_path Project-relative route file path.
     * @param string $source Route PHP source.
     *
     * @return string Migrated route source.
     *
     * @throws \RuntimeException If the source is invalid or a route Filter
     *                           expression cannot be migrated safely.
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
        $token_count = count(
            $tokens
        );

        for (
            $index = 0;
            $index < $token_count;
            $index++
        ) {
            if (!$this->isORouteToken(
                $tokens[$index],
                $imports
            )) {
                continue;
            }

            $double_colon_index = $this->nextSignificantIndex(
                $tokens,
                $index + 1
            );

            if (
                $double_colon_index === null ||
                $tokens[$double_colon_index]->id !== T_DOUBLE_COLON
            ) {
                continue;
            }

            $method_index = $this->nextSignificantIndex(
                $tokens,
                $double_colon_index + 1
            );

            if (
                $method_index === null ||
                $tokens[$method_index]->id !== T_STRING ||
                !in_array(
                    $tokens[$method_index]->text,
                    [
                        'get',
                        'post',
                        'put',
                        'delete',
                        'view',
                        'addRoute'
                    ],
                    true
                )
            ) {
                continue;
            }

            $open_index = $this->nextSignificantIndex(
                $tokens,
                $method_index + 1
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
                ')',
                $relative_path,
                $tokens[$method_index]->line
            );

            $arguments = $this->splitArguments(
                $tokens,
                $open_index + 1,
                $close_index - 1
            );

            $replacement = $this->buildRouteReplacement(
                $relative_path,
                $tokens[$method_index]->text,
                $tokens[$method_index]->line,
                $tokens,
                $arguments,
                $imports,
                $source
            );

            if ($replacement !== null) {
                $replacements[] = $replacement;
            }

            $index = $close_index;
        }

        return $this->applyReplacements(
            $source,
            $replacements
        );
    }

    /**
     * Build the replacement for one ORoute Filter argument.
     *
     * @param string $relative_path Project-relative route file path.
     * @param string $method ORoute method name.
     * @param int $line Route call line.
     * @param list<PhpToken> $tokens PHP tokens.
     * @param list<array{start: int, end: int}> $arguments Route argument token ranges.
     * @param array<string, string> $imports Imported class aliases to FQCNs.
     * @param string $source Route source.
     *
     * @return array{start: int, length: int, text: string}|null Source replacement or null.
     *
     * @throws \RuntimeException If a legacy Filter expression is unsupported.
     */
    private function buildRouteReplacement(
        string $relative_path,
        string $method,
        int $line,
        array $tokens,
        array $arguments,
        array $imports,
        string $source
    ): ?array {
        $named_filters = null;
        $named_middlewares = null;
        $has_named_arguments = false;

        foreach ($arguments as $argument_index => $argument) {
            $name = $this->getNamedArgumentName(
                $tokens,
                $argument
            );

            if ($name === null) {
                continue;
            }

            $has_named_arguments = true;

            if ($name === 'filters') {
                $named_filters = $argument_index;
            }

            if ($name === 'middlewares') {
                $named_middlewares = $argument_index;
            }
        }

        if ($named_middlewares !== null) {
            return null;
        }

        if ($named_filters !== null) {
            $argument = $arguments[$named_filters];
            $value_range = $this->getNamedArgumentValueRange(
                $tokens,
                $argument
            );

            $value = $this->buildMiddlewareArgumentValue(
                $relative_path,
                $line,
                $tokens,
                $value_range,
                $imports,
                $source
            );

            [$start, $end] = $this->getTrimmedCharacterRange(
                $tokens,
                $argument
            );

            return [
                'start' => $start,
                'length' => $end - $start,
                'text' => 'middlewares: ' . $value
            ];
        }

        if ($has_named_arguments) {
            return null;
        }

        $target_index = $method === 'addRoute'
            ? 3
            : 2;

        if (!array_key_exists(
            $target_index,
            $arguments
        )) {
            return null;
        }

        $argument = $arguments[$target_index];

        if ($this->isEmptyArrayArgument(
            $tokens,
            $argument
        )) {
            return null;
        }

        if ($this->isMiddlewareMapArgument(
            $tokens,
            $argument,
            $imports
        )) {
            return null;
        }

        $value = $this->buildMiddlewareArgumentValue(
            $relative_path,
            $line,
            $tokens,
            $argument,
            $imports,
            $source
        );

        [$start, $end] = $this->getTrimmedCharacterRange(
            $tokens,
            $argument
        );

        return [
            'start' => $start,
            'length' => $end - $start,
            'text' => $value
        ];
    }

    /**
     * Convert one legacy Filter list into a middleware phase map.
     *
     * @param string $relative_path Project-relative route file path.
     * @param int $line Route call line.
     * @param list<PhpToken> $tokens PHP tokens.
     * @param array{start: int, end: int} $range Filter argument token range.
     * @param array<string, string> $imports Imported class aliases to FQCNs.
     * @param string $source Route source.
     *
     * @return string Middleware phase map PHP source.
     *
     * @throws \RuntimeException If the argument is not a supported literal class list.
     */
    private function buildMiddlewareArgumentValue(
        string $relative_path,
        int $line,
        array $tokens,
        array $range,
        array $imports,
        string $source
    ): string {
        if ($this->isEmptyArrayArgument(
            $tokens,
            $range
        )) {
            return '[]';
        }

        if ($this->isMiddlewareMapArgument(
            $tokens,
            $range,
            $imports
        )) {
            [$start, $end] = $this->getTrimmedCharacterRange(
                $tokens,
                $range
            );

            return substr(
                $source,
                $start,
                $end - $start
            );
        }

        $class_names = $this->extractLegacyFilterClasses(
            $relative_path,
            $line,
            $tokens,
            $range,
            $imports
        );

        $middleware_classes = [];

        foreach ($class_names as $class_name) {
            $definition = $this->definitions_by_fqcn[$class_name]
                ?? null;

            if ($definition === null) {
                throw new \RuntimeException(
                    "Legacy route '{$relative_path}:{$line}' references Filter class '{$class_name}' but no migratable Filter definition was found."
                );
            }

            $middleware_classes[] = '\\'
                . $definition->middleware_fqcn
                . '::class';
        }

        return "['before' => ["
            . implode(
                ', ',
                $middleware_classes
            )
            . ']]';
    }

    /**
     * Extract legacy Filter class names from a literal array argument.
     *
     * @param string $relative_path Project-relative route file path.
     * @param int $line Route call line.
     * @param list<PhpToken> $tokens PHP tokens.
     * @param array{start: int, end: int} $range Filter argument token range.
     * @param array<string, string> $imports Imported class aliases to FQCNs.
     *
     * @return list<string> Legacy Filter FQCNs.
     *
     * @throws \RuntimeException If the expression is not a literal list of class constants.
     */
    private function extractLegacyFilterClasses(
        string $relative_path,
        int $line,
        array $tokens,
        array $range,
        array $imports
    ): array {
        $array_bounds = $this->getArrayContentBounds(
            $tokens,
            $range
        );

        if ($array_bounds === null) {
            throw new \RuntimeException(
                "Legacy route '{$relative_path}:{$line}' uses a dynamic Filter expression. Only literal Filter class arrays can be migrated automatically."
            );
        }

        [$content_start, $content_end] = $array_bounds;

        $entries = $this->splitArguments(
            $tokens,
            $content_start,
            $content_end
        );

        $classes = [];

        foreach ($entries as $entry) {
            $significant = $this->getSignificantIndexes(
                $tokens,
                $entry
            );

            if ($significant === []) {
                continue;
            }

            if (count($significant) !== 3) {
                throw new \RuntimeException(
                    "Legacy route '{$relative_path}:{$line}' contains a Filter expression that cannot be migrated automatically. Use only ClassName::class entries."
                );
            }

            [$class_index, $separator_index, $class_constant_index] = $significant;

            if (
                $tokens[$separator_index]->id !== T_DOUBLE_COLON ||
                $tokens[$class_constant_index]->id !== T_STRING ||
                strtolower(
                    $tokens[$class_constant_index]->text
                ) !== 'class'
            ) {
                throw new \RuntimeException(
                    "Legacy route '{$relative_path}:{$line}' contains a Filter expression that cannot be migrated automatically. Use only ClassName::class entries."
                );
            }

            $class_token = $tokens[$class_index];

            if (
                !in_array(
                    $class_token->id,
                    [
                        T_STRING,
                        T_NAME_QUALIFIED,
                        T_NAME_FULLY_QUALIFIED
                    ],
                    true
                )
            ) {
                throw new \RuntimeException(
                    "Legacy route '{$relative_path}:{$line}' contains an unsupported Filter class reference."
                );
            }

            $classes[] = $this->resolveClassName(
                $class_token->text,
                $imports,
                $relative_path,
                $line
            );
        }

        return $classes;
    }

    /**
     * Resolve a route class reference through its imports.
     *
     * @param string $name Class token text.
     * @param array<string, string> $imports Imported aliases to FQCNs.
     * @param string $relative_path Project-relative route file path.
     * @param int $line Route call line.
     *
     * @return string Resolved class FQCN without a leading backslash.
     *
     * @throws \RuntimeException If a short class name cannot be resolved.
     */
    private function resolveClassName(
        string $name,
        array $imports,
        string $relative_path,
        int $line
    ): string {
        if (str_starts_with(
            $name,
            '\\'
        )) {
            return ltrim(
                $name,
                '\\'
            );
        }

        if (str_contains(
            $name,
            '\\'
        )) {
            $first_separator = strpos(
                $name,
                '\\'
            );

            if ($first_separator !== false) {
                $alias = substr(
                    $name,
                    0,
                    $first_separator
                );

                if (isset($imports[$alias])) {
                    return $imports[$alias]
                        . substr(
                            $name,
                            $first_separator
                        );
                }
            }

            return ltrim(
                $name,
                '\\'
            );
        }

        if (isset($imports[$name])) {
            return $imports[$name];
        }

        throw new \RuntimeException(
            "Legacy route '{$relative_path}:{$line}' uses unresolved Filter class '{$name}'. Import it explicitly or migrate the route manually."
        );
    }

    /**
     * Extract class imports from a route source file.
     *
     * @param list<PhpToken> $tokens PHP tokens.
     * @param string $relative_path Project-relative route file path.
     *
     * @return array<string, string> Alias to imported FQCN.
     *
     * @throws \RuntimeException If a grouped class import is used.
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

            if ($next_index === null) {
                continue;
            }

            if (
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

            $is_grouped_import = false;
            $statement_text = '';

            for (
                $position = $index + 1;
                $position < $statement_end;
                $position++
            ) {
                $statement_text .= $tokens[$position]->text;

                if ($tokens[$position]->text === '{') {
                    $is_grouped_import = true;
                }
            }

            if ($is_grouped_import) {
                if (str_contains(
                    str_replace(
                        [' ', "\t", "\r", "\n"],
                        '',
                        $statement_text
                    ),
                    'Osumi\\OsumiFramework\\App\\Filter\\{'
                )) {
                    throw new \RuntimeException(
                        "Grouped legacy Filter imports in '{$relative_path}:{$token->line}' are not supported by the automatic migration."
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
     * Check whether a route argument is an empty literal array.
     *
     * @param list<PhpToken> $tokens PHP tokens.
     * @param array{start: int, end: int} $range Argument token range.
     *
     * @return bool Whether the argument is an empty array.
     */
    private function isEmptyArrayArgument(
        array $tokens,
        array $range
    ): bool {
        $bounds = $this->getArrayContentBounds(
            $tokens,
            $range
        );

        if ($bounds === null) {
            return false;
        }

        [$start, $end] = $bounds;

        return $this->getSignificantIndexes(
            $tokens,
            [
                'start' => $start,
                'end' => $end
            ]
        ) === [];
    }

    /**
     * Check whether an argument already contains a Middleware phase map.
     *
     * Both literal phase names and OMiddleware phase constants are supported.
     *
     * @param list<PhpToken> $tokens PHP tokens.
     * @param array{start: int, end: int} $range Argument token range.
     * @param array<string, string> $imports Imported class aliases to FQCNs.
     *
     * @return bool Whether the argument is already a Middleware map.
     */
    private function isMiddlewareMapArgument(
        array $tokens,
        array $range,
        array $imports
    ): bool {
        $bounds = $this->getArrayContentBounds(
            $tokens,
            $range
        );

        if ($bounds === null) {
            return false;
        }

        [$start, $end] = $bounds;

        $entries = $this->splitArguments(
            $tokens,
            $start,
            $end
        );

        $phase_names = [
            'before',
            'afterRender',
            'afterResponse'
        ];

        $phase_constants = [
            'PHASE_BEFORE',
            'PHASE_AFTER_RENDER',
            'PHASE_AFTER_RESPONSE'
        ];

        foreach ($entries as $entry) {
            $significant = $this->getSignificantIndexes(
                $tokens,
                $entry
            );

            if (count($significant) < 3) {
                continue;
            }

            $key_token = $tokens[$significant[0]];

            // Literal syntax:
            // 'before' => [...]
            if (
                $tokens[$significant[1]]->id === T_DOUBLE_ARROW &&
                $key_token->id === T_CONSTANT_ENCAPSED_STRING
            ) {
                $key = trim(
                    $key_token->text,
                    "'\""
                );

                if (in_array(
                    $key,
                    $phase_names,
                    true
                )) {
                    return true;
                }

                continue;
            }

            // Constant syntax:
            // OMiddleware::PHASE_BEFORE => [...]
            if (count($significant) < 4) {
                continue;
            }

            $separator_token = $tokens[$significant[1]];
            $constant_token = $tokens[$significant[2]];
            $arrow_token = $tokens[$significant[3]];

            if (
                $separator_token->id !== T_DOUBLE_COLON ||
                $constant_token->id !== T_STRING ||
                $arrow_token->id !== T_DOUBLE_ARROW ||
                !in_array(
                    $constant_token->text,
                    $phase_constants,
                    true
                )
            ) {
                continue;
            }

            if (!in_array(
                $key_token->id,
                [
                    T_STRING,
                    T_NAME_QUALIFIED,
                    T_NAME_FULLY_QUALIFIED
                ],
                true
            )) {
                continue;
            }

            $class_name = ltrim(
                $key_token->text,
                '\\'
            );

            if (isset($imports[$class_name])) {
                $class_name = $imports[$class_name];
            }

            if (
                $class_name ===
                'Osumi\\OsumiFramework\\Core\\OMiddleware'
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the inner token bounds for a literal [] or array() expression.
     *
     * @param list<PhpToken> $tokens PHP tokens.
     * @param array{start: int, end: int} $range Argument token range.
     *
     * @return array{0: int, 1: int}|null Inner start/end token indexes or null.
     */
    private function getArrayContentBounds(
        array $tokens,
        array $range
    ): ?array {
        $significant = $this->getSignificantIndexes(
            $tokens,
            $range
        );

        if (count($significant) < 2) {
            return null;
        }

        $first = $significant[0];
        $last = $significant[array_key_last(
            $significant
        )];

        if (
            $tokens[$first]->text === '[' &&
            $tokens[$last]->text === ']'
        ) {
            return [
                $first + 1,
                $last - 1
            ];
        }

        if (
            $tokens[$first]->id === T_ARRAY
        ) {
            $open_index = $this->nextSignificantIndex(
                $tokens,
                $first + 1
            );

            if (
                $open_index !== null &&
                $tokens[$open_index]->text === '(' &&
                $tokens[$last]->text === ')'
            ) {
                return [
                    $open_index + 1,
                    $last - 1
                ];
            }
        }

        return null;
    }

    /**
     * Get a named argument's name.
     *
     * @param list<PhpToken> $tokens PHP tokens.
     * @param array{start: int, end: int} $range Argument token range.
     *
     * @return string|null Named argument name or null.
     */
    private function getNamedArgumentName(
        array $tokens,
        array $range
    ): ?string {
        $significant = $this->getSignificantIndexes(
            $tokens,
            $range
        );

        if (count($significant) < 2) {
            return null;
        }

        $name_token = $tokens[$significant[0]];
        $colon_token = $tokens[$significant[1]];

        if (
            $name_token->id === T_STRING &&
            $colon_token->text === ':'
        ) {
            return $name_token->text;
        }

        return null;
    }

    /**
     * Get the value token range of a named argument.
     *
     * @param list<PhpToken> $tokens PHP tokens.
     * @param array{start: int, end: int} $range Argument token range.
     *
     * @return array{start: int, end: int} Named argument value range.
     *
     * @throws \RuntimeException If the named argument has no value.
     */
    private function getNamedArgumentValueRange(
        array $tokens,
        array $range
    ): array {
        $significant = $this->getSignificantIndexes(
            $tokens,
            $range
        );

        if (count($significant) < 3) {
            throw new \RuntimeException(
                'Named route argument does not contain a value.'
            );
        }

        return [
            'start' => $significant[2],
            'end' => $range['end']
        ];
    }

    /**
     * Split a token range on top-level commas.
     *
     * @param list<PhpToken> $tokens PHP tokens.
     * @param int $start Start token index.
     * @param int $end End token index.
     *
     * @return list<array{start: int, end: int}> Argument ranges.
     */
    private function splitArguments(
        array $tokens,
        int $start,
        int $end
    ): array {
        if ($end < $start) {
            return [];
        }

        $arguments = [];
        $argument_start = $start;
        $parentheses = 0;
        $brackets = 0;
        $braces = 0;

        for (
            $index = $start;
            $index <= $end;
            $index++
        ) {
            $text = $tokens[$index]->text;

            if ($text === '(') {
                $parentheses++;
                continue;
            }

            if ($text === ')') {
                $parentheses--;
                continue;
            }

            if ($text === '[') {
                $brackets++;
                continue;
            }

            if ($text === ']') {
                $brackets--;
                continue;
            }

            if ($text === '{') {
                $braces++;
                continue;
            }

            if ($text === '}') {
                $braces--;
                continue;
            }

            if (
                $text === ',' &&
                $parentheses === 0 &&
                $brackets === 0 &&
                $braces === 0
            ) {
                $arguments[] = [
                    'start' => $argument_start,
                    'end' => $index - 1
                ];

                $argument_start = $index + 1;
            }
        }

        $arguments[] = [
            'start' => $argument_start,
            'end' => $end
        ];

        return array_values(
            array_filter(
                $arguments,
                fn(array $range): bool => $this->getSignificantIndexes(
                    $tokens,
                    $range
                ) !== []
            )
        );
    }

    /**
     * Get significant token indexes in a token range.
     *
     * @param list<PhpToken> $tokens PHP tokens.
     * @param array{start: int, end: int} $range Token range.
     *
     * @return list<int> Significant token indexes.
     */
    private function getSignificantIndexes(
        array $tokens,
        array $range
    ): array {
        $indexes = [];

        for (
            $index = $range['start'];
            $index <= $range['end'];
            $index++
        ) {
            if (!$tokens[$index]->isIgnorable()) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    /**
     * Convert a token range to trimmed character offsets.
     *
     * @param list<PhpToken> $tokens PHP tokens.
     * @param array{start: int, end: int} $range Token range.
     *
     * @return array{0: int, 1: int} Start offset and exclusive end offset.
     */
    private function getTrimmedCharacterRange(
        array $tokens,
        array $range
    ): array {
        $significant = $this->getSignificantIndexes(
            $tokens,
            $range
        );

        $first = $significant[0];
        $last = $significant[array_key_last(
            $significant
        )];

        return [
            $tokens[$first]->pos,
            $tokens[$last]->pos
                + strlen(
                    $tokens[$last]->text
                )
        ];
    }

    /**
     * Check whether a token can identify ORoute.
     *
     * @param PhpToken $token PHP token.
     * @param array<string, string> $imports Imported class aliases to FQCNs.
     *
     * @return bool Whether the token identifies ORoute.
     */
    private function isORouteToken(
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
            $name === 'ORoute' ||
            str_ends_with(
                $name,
                '\\ORoute'
            )
        ) {
            return true;
        }

        return $token->id === T_STRING &&
            ($imports[$token->text] ?? null) === 'Osumi\\OsumiFramework\\Routing\\ORoute';
    }

    /**
     * Find the next significant token index.
     *
     * @param list<PhpToken> $tokens PHP tokens.
     * @param int $start Starting token index.
     *
     * @return int|null Token index or null.
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
     * Find a matching closing delimiter.
     *
     * @param list<PhpToken> $tokens PHP tokens.
     * @param int $open_index Opening token index.
     * @param string $open Opening delimiter.
     * @param string $close Closing delimiter.
     * @param string $relative_path Source path.
     * @param int $line Source line.
     *
     * @return int Closing token index.
     *
     * @throws \RuntimeException If no matching delimiter exists.
     */
    private function findMatchingToken(
        array $tokens,
        int $open_index,
        string $open,
        string $close,
        string $relative_path,
        int $line
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
            "Unmatched '{$open}' delimiter in '{$relative_path}:{$line}'."
        );
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
     * Tokenize PHP source.
     *
     * @param string $relative_path Source path.
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
     * Apply source replacements from the end of the file.
     *
     * @param string $source Original source.
     * @param list<array{start: int, length: int, text: string}> $replacements Replacements.
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
