<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Tools;

use Osumi\OsumiFramework\Tools\OColors;
use PHPUnit\Framework\TestCase;

final class OColorsTest extends TestCase {
    /**
     * Test that a string can be generated without foreground or background colors.
     *
     * @return void
     */
    public function testStringWithoutColorsDoesNotUseNullArrayOffsets(): void {
        $colors = new OColors();

        self::assertSame(
            "Test\033[0m",
            $colors->getColoredString(
                'Test'
            )
        );
    }

    /**
     * Test that a foreground color is applied correctly.
     *
     * @return void
     */
    public function testForegroundColorIsApplied(): void {
        $colors = new OColors();

        self::assertSame(
            "\033[0;31mTest\033[0m",
            $colors->getColoredString(
                'Test',
                'red'
            )
        );
    }

    /**
     * Test that a background color is applied correctly without a foreground color.
     *
     * @return void
     */
    public function testBackgroundColorIsAppliedWithoutForegroundColor(): void {
        $colors = new OColors();

        self::assertSame(
            "\033[41mTest\033[0m",
            $colors->getColoredString(
                'Test',
                null,
                'red'
            )
        );
    }

    /**
     * Test that unknown colors are ignored.
     *
     * @return void
     */
    public function testUnknownColorsAreIgnored(): void {
        $colors = new OColors();

        self::assertSame(
            "Test\033[0m",
            $colors->getColoredString(
                'Test',
                'unknown',
                'unknown'
            )
        );
    }
}
