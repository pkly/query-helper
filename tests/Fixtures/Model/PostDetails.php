<?php

declare(strict_types=1);

namespace Pkly\QueryHelper\Tests\Fixtures\Model;

final class PostDetails
{
    public function __construct(
        private readonly int $id,
        private readonly string $title,
        private readonly UserSummary $author,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAuthor(): UserSummary
    {
        return $this->author;
    }
}
