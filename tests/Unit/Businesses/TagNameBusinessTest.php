<?php

declare(strict_types=1);

namespace Tests\Unit\Businesses;

use app\businesses\TagNameBusiness;
use PHPUnit\Framework\TestCase;

final class TagNameBusinessTest extends TestCase
{
    public function test_it_normalizes_collapses_and_deduplicates_tag_names(): void
    {
        $result = (new TagNameBusiness())->normalizeList(['  Career  Goal ', 'career goal', '重点']);

        self::assertSame([
            ['name' => 'Career Goal', 'normalized_name' => 'career goal'],
            ['name' => '重点', 'normalized_name' => '重点'],
        ], $result);
    }

    public function test_it_rejects_invalid_or_excessive_tags(): void
    {
        self::assertNull((new TagNameBusiness())->normalizeList(['']));
        self::assertNull((new TagNameBusiness())->normalizeList([str_repeat('a', 41)]));
        self::assertNull((new TagNameBusiness())->normalizeList([['not-a-string']]));
        self::assertNull((new TagNameBusiness())->normalizeList(array_fill(0, 21, 'tag')));
    }
}
