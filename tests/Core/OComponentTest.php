<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Core;

use Osumi\OsumiFramework\Core\OConfig;
use Osumi\OsumiFramework\Web\OStreamResponse;
use Osumi\OsumiFramework\Tests\Fixtures\Component\BasicComponent;
use Osumi\OsumiFramework\Tests\Fixtures\Component\MissingComponent;
use Osumi\OsumiFramework\Tests\Fixtures\Component\PhpComponent;
use Osumi\OsumiFramework\Tests\Fixtures\Component\RunComponent;
use Osumi\OsumiFramework\Tests\Fixtures\Component\ThrowingPhpComponent;
use Osumi\OsumiFramework\Tests\Support\TemporaryProject;
use Osumi\OsumiFramework\Tests\Fixtures\Component\StreamComponent;
use PHPUnit\Framework\TestCase;

final class OComponentTest extends TestCase {
    private TemporaryProject $project;
    private bool $core_existed = false;
    private mixed $previous_core = null;

    /**
     * Prepare the global core required by component logging.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        $this->project = new TemporaryProject();

        $this->core_existed = array_key_exists(
            'core',
            $GLOBALS
        );

        $this->previous_core = $GLOBALS['core']
            ?? null;

        $config = new OConfig(
            $this->project->getBasePath()
        );

        $config->setLog(
            'name',
            'component-test'
        );

        $core = new \stdClass();
        $core->config = $config;

        $GLOBALS['core'] = $core;
    }

    /**
     * Restore global state and remove temporary data.
     *
     * @return void
     */
    protected function tearDown(): void {
        if ($this->core_existed) {
            $GLOBALS['core'] = $this->previous_core;
        } else {
            unset(
                $GLOBALS['core']
            );
        }

        $this->project->remove();

        parent::tearDown();
    }

    /**
     * Test direct values, object properties and template pipes.
     *
     * @return void
     */
    public function testHtmlTemplateSubstitutions(): void {
        $item = new \stdClass();
        $item->title = 'Book';

        $component = new BasicComponent(
            [
                'name' => 'Iñigo',
                'text' => 'hello world',
                'json_text' => 'A "quote"',
                'enabled' => true,
                'item' => $item,
                'unknown' => 'ignored'
            ]
        );

        $result = trim(
            $component->render()
        );

        self::assertStringContainsString(
            'Iñigo',
            $result
        );

        self::assertStringContainsString(
            '"hello+world"',
            $result
        );

        self::assertStringContainsString(
            '"A \\"quote\\""',
            $result
        );

        self::assertStringContainsString(
            '|true|',
            $result
        );

        self::assertStringEndsWith(
            'Book',
            $result
        );
    }

    /**
     * Test rendering of a PHP component template.
     *
     * @return void
     */
    public function testPhpTemplateIsRendered(): void {
        $component = new PhpComponent(
            [
                'name' => 'Iñigo'
            ]
        );

        self::assertSame(
            'PHP:Iñigo',
            trim(
                $component->render()
            )
        );
    }

    /**
     * Test that output buffers are restored when a PHP template throws.
     *
     * @return void
     */
    public function testPhpTemplateRestoresOutputBufferAfterException(): void {
        $component = new ThrowingPhpComponent();

        $buffer_level = ob_get_level();

        try {
            $component->render();

            self::fail(
                'The PHP template was expected to throw.'
            );
        } catch (\RuntimeException $e) {
            self::assertSame(
                'Template exploded.',
                $e->getMessage()
            );
        }

        self::assertSame(
            $buffer_level,
            ob_get_level()
        );
    }

    /**
     * Test that a component run method is executed before rendering.
     *
     * @return void
     */
    public function testRunWithoutArgumentsIsExecuted(): void {
        $component = new RunComponent();

        self::assertSame(
            'after',
            trim(
                $component->render()
            )
        );
    }

    /**
     * Test that a stream-only component can omit its template.
     *
     * @return void
     */
    public function testStreamResponseCanBeReturnedWithoutTemplate(): void {
        $component = new StreamComponent();

        $result = $component->render();

        self::assertInstanceOf(
            OStreamResponse::class,
            $result
        );

        self::assertSame(
            'application/octet-stream',
            $result->getHeaders()['Content-Type']
        );

        $stream = $result->getStream();

        self::assertSame(
            'streamed-content',
            stream_get_contents(
                $stream
            )
        );

        $result->close();
    }

    /**
     * Test that streamed responses cannot be converted to strings.
     *
     * @return void
     */
    public function testStreamResponseCannotBeConvertedToString(): void {
        $component = new StreamComponent();

        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            'OStreamResponse cannot be converted to a string'
        );

        strval(
            $component
        );
    }

    /**
     * Test that a component without a template cannot be initialized.
     *
     * @return void
     */
    public function testMissingTemplateIsRejected(): void {
        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            'No valid template file found'
        );

        new MissingComponent();
    }
}
