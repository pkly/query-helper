<?php

declare(strict_types=1);

namespace Pkly\QueryHelper\Tests\PHPStan;

use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ModelRowsReturnTypeExtensionTest extends TypeInferenceTestCase
{
    /**
     * @return iterable<mixed>
     */
    public static function provideFileAssertsCases(): iterable
    {
        yield from self::gatherAssertTypes(__DIR__.'/data/model-rows.php');
    }

    #[DataProvider('provideFileAssertsCases')]
    public function testFileAsserts(
        string $assertType,
        string $file,
        mixed ...$args
    ): void {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__.'/../../extension.neon'];
    }
}
