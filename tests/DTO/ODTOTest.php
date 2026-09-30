<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\DTO;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;
use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\TestCase;

final class RequiredDTO extends ODTO {
    #[ODTOField(
        required: true
    )]
    public ?string $name = null;
}

final class ConditionalDTO extends ODTO {
    #[ODTOField]
    public ?bool $trigger = null;

    #[ODTOField(
        requiredIf: 'trigger'
    )]
    public ?string $value = null;
}

final class UnknownDependencyDTO extends ODTO {
    #[ODTOField(
        requiredIf: 'unknown'
    )]
    public ?string $value = null;
}

final class SelfDependencyDTO extends ODTO {
    #[ODTOField(
        requiredIf: 'value'
    )]
    public ?string $value = null;
}

final class HeaderDTO extends ODTO {
    #[ODTOField(
        required: true,
        header: 'Authorization'
    )]
    public ?string $authorization = null;
}

final class IntegerDTO extends ODTO {
    #[ODTOField(
        required: true
    )]
    public ?int $id = null;
}

final class ODTOTest extends TestCase {
    /**
     * Create a request for DTO testing.
     *
     * @param array<string, mixed> $params Request parameters.
     * @param array<string, string> $headers Request headers.
     *
     * @return ORequest Request instance.
     */
    private function createRequest(
        array $params = [],
        array $headers = []
    ): ORequest {
        return new ORequest(
            [
                'method' => 'POST',
                'headers' => $headers,
                'params' => $params
            ],
            []
        );
    }

    /**
     * Test that a missing required property produces a validation error.
     *
     * @return void
     */
    public function testRequiredFieldProducesValidationError(): void {
        $dto = new RequiredDTO(
            $this->createRequest()
        );

        self::assertFalse(
            $dto->isValid()
        );

        self::assertSame(
            [
                "The property 'name' is required."
            ],
            $dto->getValidationErrors()
        );
    }

    /**
     * Test that requiredIf does nothing when the dependency has no value.
     *
     * @return void
     */
    public function testRequiredIfAllowsMissingFieldWithoutDependencyValue(): void {
        $dto = new ConditionalDTO(
            $this->createRequest()
        );

        self::assertTrue(
            $dto->isValid()
        );
    }

    /**
     * Test that requiredIf requires the field when the dependency has a value.
     *
     * False is deliberately a value because DTO absence is represented by null.
     *
     * @return void
     */
    public function testRequiredIfUsesNonNullDependencySemantics(): void {
        $dto = new ConditionalDTO(
            $this->createRequest(
                [
                    'trigger' => false
                ]
            )
        );

        self::assertFalse(
            $dto->isValid()
        );

        self::assertSame(
            [
                "The property 'value' is required when 'trigger' has a value."
            ],
            $dto->getValidationErrors()
        );
    }

    /**
     * Test that requiredIf accepts the field when both values are provided.
     *
     * @return void
     */
    public function testRequiredIfAcceptsProvidedValue(): void {
        $dto = new ConditionalDTO(
            $this->createRequest(
                [
                    'trigger' => true,
                    'value' => 'test'
                ]
            )
        );

        self::assertTrue(
            $dto->isValid()
        );

        self::assertSame(
            'test',
            $dto->value
        );
    }

    /**
     * Test that requiredIf rejects an unknown dependency.
     *
     * @return void
     */
    public function testRequiredIfRejectsUnknownDependency(): void {
        $this->expectException(
            \InvalidArgumentException::class
        );

        new UnknownDependencyDTO(
            $this->createRequest()
        );
    }

    /**
     * Test that a DTO field cannot depend on itself.
     *
     * @return void
     */
    public function testRequiredIfRejectsSelfDependency(): void {
        $this->expectException(
            \InvalidArgumentException::class
        );

        new SelfDependencyDTO(
            $this->createRequest()
        );
    }

    /**
     * Test that header-backed fields use case-insensitive header lookup.
     *
     * @return void
     */
    public function testHeaderFieldUsesCaseInsensitiveLookup(): void {
        $dto = new HeaderDTO(
            $this->createRequest(
                [],
                [
                    'authorization' => 'Bearer test'
                ]
            )
        );

        self::assertTrue(
            $dto->isValid()
        );

        self::assertSame(
            'Bearer test',
            $dto->authorization
        );
    }

    /**
     * Test conversion of a valid integer DTO field.
     *
     * @return void
     */
    public function testIntegerFieldIsNormalized(): void {
        $dto = new IntegerDTO(
            $this->createRequest(
                [
                    'id' => '25'
                ]
            )
        );

        self::assertTrue(
            $dto->isValid()
        );

        self::assertSame(
            25,
            $dto->id
        );
    }

    /**
     * Test that an invalid required integer becomes a validation error.
     *
     * @return void
     */
    public function testInvalidRequiredIntegerIsRejected(): void {
        $dto = new IntegerDTO(
            $this->createRequest(
                [
                    'id' => '25.5'
                ]
            )
        );

        self::assertFalse(
            $dto->isValid()
        );

        self::assertNull(
            $dto->id
        );
    }
}
