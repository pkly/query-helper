<?php

declare(strict_types=1);

namespace Pkly\QueryHelper\Tests;

use Pkly\QueryHelper\QueryHelper;
use Pkly\QueryHelper\Tests\Fixtures\Entity\Tag;

class NonStandardPrimaryKeyTest extends AbstractDoctrineTestCase
{
    public function testIdsReturnsSlugNotId(): void
    {
        $this->makeTag('php', 'PHP');
        $this->makeTag('doctrine', 'Doctrine');

        $ids = $this->tagHelper()->ids();

        static::assertCount(2, $ids);
        static::assertContains('php', $ids->toArray());
        static::assertContains('doctrine', $ids->toArray());
    }

    public function testIdReturnsSingleSlug(): void
    {
        $this->makeTag('php', 'PHP');

        static::assertSame('php', $this->tagHelper()->id());
    }

    public function testCountUsesCorrectIdentifierField(): void
    {
        $this->makeTag('php', 'PHP');
        $this->makeTag('doctrine', 'Doctrine');

        static::assertSame(2, $this->tagHelper()->count());
    }

    public function testDistinctCountUsesCorrectIdentifierField(): void
    {
        $this->makeTag('php', 'PHP');
        $this->makeTag('doctrine', 'Doctrine');

        static::assertSame(2, $this->tagHelper()->distinct()->count());
    }

    public function testExistsWorksWithNonStandardPk(): void
    {
        static::assertFalse($this->tagHelper()->exists());

        $this->makeTag('php', 'PHP');

        static::assertTrue($this->tagHelper()->exists());
    }

    /** @return QueryHelper<Tag, string> */
    private function tagHelper(): QueryHelper
    {
        $qb = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('entity')
            ->from(Tag::class, 'entity');

        return new QueryHelper($qb); // @phpstan-ignore-line
    }

    private function makeTag(
        string $slug,
        string $name
    ): Tag {
        $tag = new Tag($slug, $name);
        $this->persist($tag);

        return $tag;
    }
}
