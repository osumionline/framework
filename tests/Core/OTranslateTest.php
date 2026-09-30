<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Core;

use Osumi\OsumiFramework\Core\OTranslate;
use Osumi\OsumiFramework\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;

final class OTranslateTest extends TestCase {
    private TemporaryProject $project;

    /**
     * Prepare a temporary translation workspace.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        $this->project = new TemporaryProject();
    }

    /**
     * Remove temporary translation files.
     *
     * @return void
     */
    protected function tearDown(): void {
        $this->project->remove();

        parent::tearDown();
    }

    /**
     * Test creation, saving and loading of a PO document.
     *
     * @return void
     */
    public function testTranslationRoundTrip(): void {
        $path = $this->project->getPath(
            'es.po'
        );

        $translate = new OTranslate();

        self::assertTrue(
            $translate->new(
                $path,
                'es'
            )
        );

        $translate->setHeader(
            'X-Test',
            'value:with:colons'
        );

        $translate->setTranslation(
            'Hello',
            'Hola'
        );

        $translate->setTranslation(
            'Multiline key',
            'Multiline value'
        );

        self::assertTrue(
            $translate->save()
        );

        $loaded = new OTranslate();

        $loaded->load(
            $path
        );

        self::assertSame(
            'es',
            $loaded->getLang()
        );

        self::assertSame(
            'Hola',
            $loaded->getTranslation(
                'Hello'
            )
        );

        self::assertSame(
            'value:with:colons',
            $loaded->getHeaders()['X-Test']
        );
    }

    /**
     * Test that translation lookup trims keys consistently.
     *
     * @return void
     */
    public function testTranslationLookupTrimsKey(): void {
        $translate = new OTranslate();

        $translate->setTranslation(
            'Hello',
            'Hola'
        );

        self::assertSame(
            'Hola',
            $translate->getTranslation(
                '  Hello  '
            )
        );
    }

    /**
     * Test that creating a new PO document resets previous translations.
     *
     * @return void
     */
    public function testNewDocumentResetsTranslations(): void {
        $translate = new OTranslate();

        $translate->setTranslation(
            'Old',
            'Anterior'
        );

        $translate->new(
            $this->project->getPath(
                'new.po'
            ),
            'eu'
        );

        self::assertSame(
            [],
            $translate->getTranslations()
        );

        self::assertSame(
            'eu',
            $translate->getLang()
        );

        self::assertSame(
            'eu',
            $translate->getHeaders()['Language']
        );
    }

    /**
     * Test that an invalid PO load does not destroy existing state.
     *
     * @return void
     *
     * @throws \RuntimeException If the invalid fixture cannot be written.
     */
    public function testInvalidLoadKeepsPreviousState(): void {
        $valid_path = $this->project->getPath(
            'valid.po'
        );

        $invalid_path = $this->project->getPath(
            'invalid.po'
        );

        $translate = new OTranslate();

        $translate->new(
            $valid_path,
            'es'
        );

        $translate->setTranslation(
            'Existing',
            'Existente'
        );

        if (
            file_put_contents(
                $invalid_path,
                "invalid po file\n",
                LOCK_EX
            ) === false
        ) {
            throw new \RuntimeException(
                'Could not write invalid PO fixture.'
            );
        }

        try {
            $translate->load(
                $invalid_path
            );

            self::fail(
                'Invalid PO content was expected to be rejected.'
            );
        } catch (\UnexpectedValueException) {
            self::assertSame(
                $valid_path,
                $translate->getPath()
            );

            self::assertSame(
                'es',
                $translate->getLang()
            );

            self::assertSame(
                'Existente',
                $translate->getTranslation(
                    'Existing'
                )
            );
        }
    }

    /**
     * Test loading a missing PO file.
     *
     * @return void
     */
    public function testMissingFileIsRejected(): void {
        $translate = new OTranslate();

        $this->expectException(
            \RuntimeException::class
        );

        $translate->load(
            $this->project->getPath(
                'missing.po'
            )
        );
    }

    /**
     * Test operations that require a configured path.
     *
     * @return void
     */
    public function testSaveAndNewWithoutPathReturnFalse(): void {
        $translate = new OTranslate();

        self::assertFalse(
            $translate->save()
        );

        self::assertFalse(
            $translate->new()
        );
    }
}
