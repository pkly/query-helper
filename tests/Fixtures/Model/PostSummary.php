<?php

declare(strict_types=1);

namespace Pkly\QueryHelper\Tests\Fixtures\Model;

final class PostSummary
{
    public function __construct(
        private readonly int $authorId,
        private readonly string $title,
    ) {
    }

    public function getAuthorId(): int
    {
        return $this->authorId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
