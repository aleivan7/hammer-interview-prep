<?php

namespace Tests\Unit;

use App\Support\CatalogNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class CatalogNormalizerTest extends TestCase
{
    #[TestDox('Normalizes merchant names with case and whitespace')]
    public function test_normalizes_names(): void
    {
        $this->assertSame('netflix', CatalogNormalizer::name('  NetFlix  '));
        $this->assertSame('coffee treats', CatalogNormalizer::name("Coffee   Treats\t"));
    }

    #[TestDox('Normalizes descriptors by uppercasing and stripping punctuation')]
    #[DataProvider('descriptors')]
    public function test_normalizes_descriptors(string $input, string $expected): void
    {
        $this->assertSame($expected, CatalogNormalizer::descriptor($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function descriptors(): array
    {
        return [
            'hyphen city' => ['NETFLIX-NY', 'NETFLIX NY'],
            'domain and digits' => ['NETFLIX.COM 408724', 'NETFLIX COM 408724'],
            'spotify id' => ['spotify-94820349857', 'SPOTIFY 94820349857'],
            'repeated spaces' => ['SPOTIFY   USA', 'SPOTIFY USA'],
        ];
    }
}
