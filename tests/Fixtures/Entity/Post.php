<?php

declare(strict_types=1);

namespace Pkly\QueryHelper\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'posts')]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private int|null $id = null; // @phpstan-ignore-line property.unusedType

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $author;

    public function __construct(
        string $title,
        User $author
    ) {
        $this->title = $title;
        $this->author = $author;
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }
}
