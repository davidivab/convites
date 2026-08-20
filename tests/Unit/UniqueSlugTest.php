<?php

namespace Tests\Unit;

use App\Support\UniqueSlug;
use PHPUnit\Framework\TestCase;

class UniqueSlugTest extends TestCase
{
    public function test_returns_base_slug_when_available(): void
    {
        $taken = fn (string $slug): bool => false;

        $this->assertSame('casa', UniqueSlug::make('Casa', $taken, 'fallback'));
    }

    public function test_appends_incrementing_suffix_on_collision(): void
    {
        $existing = ['casa' => true, 'casa-1' => true];
        $taken = fn (string $slug): bool => $existing[$slug] ?? false;

        $this->assertSame('casa-2', UniqueSlug::make('Casa', $taken, 'fallback'));
    }

    public function test_third_collision_advances_suffix_again(): void
    {
        $existing = ['casa' => true, 'casa-1' => true, 'casa-2' => true];
        $taken = fn (string $slug): bool => $existing[$slug] ?? false;

        $this->assertSame('casa-3', UniqueSlug::make('Casa', $taken, 'fallback'));
    }

    public function test_falls_back_when_title_produces_empty_slug(): void
    {
        $taken = fn (string $slug): bool => false;

        $this->assertSame('fallback', UniqueSlug::make('!!!', $taken, 'fallback'));
    }

    public function test_truncates_title_to_140_characters_before_slugifying(): void
    {
        $long = str_repeat('a', 200);
        $taken = fn (string $slug): bool => false;

        $this->assertSame(str_repeat('a', 140), UniqueSlug::make($long, $taken, 'fallback'));
    }
}
