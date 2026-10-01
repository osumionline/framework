<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\DTO;

use Osumi\OsumiFramework\Core\OMiddleware;
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

final class MiddlewareDTO extends ODTO {
    #[ODTOField(
        required: true,
        middleware: 'Auth',
        middlewareProperty: 'id'
    )]
    public ?int $userId = null;
}

final class MissingMiddlewarePropertyDTO extends ODTO {
    #[ODTOField(
        middleware: 'Auth'
    )]
    public ?int $userId = null;
}

final class MissingMiddlewareNameDTO extends ODTO {
    #[ODTOField(
        middlewareProperty: 'id'
    )]
    public ?int $userId = null;
}

final class AmbiguousSourceDTO extends ODTO {
    #[ODTOField(
        middleware: 'Auth',
        middlewareProperty: 'id',
        header: 'X-User-Id'
    )]
    public ?int $userId = null;
}

final class AuthMiddleware {
    /**
     * Publish authenticated user test data during the before phase.
     *
     * @param string $phase Middleware phase.
     * @param array<string, mixed> $data Middleware data.
     *
     * @return array<string, mixed> Middleware result.
     */
    public static function handle(
        string $phase,
        array $data
    ): array {
        if ($phase !== OMiddleware::PHASE_BEFORE) {
            return [];
        }

        return [
            'context' => [
                'id' => '25'
            ]
        ];
    }
}

final class ODTOTest extends TestCase {
    /**
     * Reset middleware state before every test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        OMiddleware::setGlobal([]);
        OMiddleware::reset();
    }

    /**
     * Reset middleware state after every test.
     *
     * @return void
     */
    protected function tearDown(): void {
        OMiddleware::setGlobal([]);
        OMiddleware::reset();

        parent::tearDown();
    }

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
            ]
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

    /**
     * Test that middleware context is normalized into the DTO property type.
     *
     * Client input for the same DTO property must not override the explicit
     * middleware source.
     *
     * @return void
     */
    public function testMiddlewareFieldUsesMiddlewareContext(): void {
        OMiddleware::setRoute(
            [
                'before' => [
                    AuthMiddleware::class
                ]
            ]
        );

        OMiddleware::runPhase(
            OMiddleware::PHASE_BEFORE,
            []
        );

        $dto = new MiddlewareDTO(
            $this->createRequest(
                [
                    'userId' => '999'
                ]
            )
        );

        self::assertTrue(
            $dto->isValid()
        );

        self::assertSame(
            25,
            $dto->userId
        );
    }

    /**
     * Test that an explicit middleware source never falls back to request input.
     *
     * @return void
     */
    public function testMissingMiddlewareValueDoesNotFallBackToRequestParameter(): void {
        $dto = new MiddlewareDTO(
            $this->createRequest(
                [
                    'userId' => '999'
                ]
            )
        );

        self::assertFalse(
            $dto->isValid()
        );

        self::assertNull(
            $dto->userId
        );
    }

    /**
     * Test that middleware requires middlewareProperty.
     *
     * @return void
     */
    public function testMiddlewareRequiresMiddlewareProperty(): void {
        $this->expectException(
            \InvalidArgumentException::class
        );

        new MissingMiddlewarePropertyDTO(
            $this->createRequest()
        );
    }

    /**
     * Test that middlewareProperty requires middleware.
     *
     * @return void
     */
    public function testMiddlewarePropertyRequiresMiddleware(): void {
        $this->expectException(
            \InvalidArgumentException::class
        );

        new MissingMiddlewareNameDTO(
            $this->createRequest()
        );
    }

    /**
     * Test that a DTO field cannot define middleware and header sources together.
     *
     * @return void
     */
    public function testMiddlewareAndHeaderSourcesAreMutuallyExclusive(): void {
        $this->expectException(
            \InvalidArgumentException::class
        );

        new AmbiguousSourceDTO(
            $this->createRequest()
        );
    }
}
