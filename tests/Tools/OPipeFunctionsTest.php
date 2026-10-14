<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Tools;

use Osumi\OsumiFramework\Tools\OPipeFunctions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OPipeFunctionsTest extends TestCase {
    /**
     * Test strict DATE and DATETIME formatting.
     *
     * @param string|null $value Input value.
     * @param string|null $mask Optional output mask.
     * @param string $expected Expected pipe result.
     *
     * @return void
     */
    #[DataProvider('dateValueProvider')]
    public function testDateValuesAreFormatted(
        ?string $value,
        ?string $mask,
        string $expected
    ): void {
        self::assertSame(
            $expected,
            OPipeFunctions::getDateValue(
                $value,
                $mask
            )
        );
    }

    /**
     * Provide DATE and DATETIME values.
     *
     * @return array<string, array{
     *     string|null,
     *     string|null,
     *     string
     * }>
     */
    public static function dateValueProvider(): array {
        return [
            'null' => [
                null,
                null,
                'null'
            ],
            'date default format' => [
                '2026-10-14',
                null,
                '"14/10/2026"'
            ],
            'datetime default format' => [
                '2026-10-14 13:45:30',
                null,
                '"14/10/2026 13:45:30"'
            ],
            'date custom format' => [
                '2026-10-14',
                'Ymd',
                '"20261014"'
            ],
            'datetime custom format' => [
                '2026-10-14 13:45:30',
                'd-m-Y H:i',
                '"14-10-2026 13:45"'
            ],
            'invalid calendar date' => [
                '2026-02-30',
                null,
                'null'
            ],
            'invalid datetime' => [
                '2026-10-14 25:00:00',
                null,
                'null'
            ],
            'unsupported iso datetime' => [
                '2026-10-14T13:45:30',
                null,
                'null'
            ]
        ];
    }
}
