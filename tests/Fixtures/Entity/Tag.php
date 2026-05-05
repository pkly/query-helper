<?php

declare(strict_types=1);

namespace Pkly\QueryHelper\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tags')]
class Tag
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 100)]
    private string $slug;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    public function __construct(
        string $slug,
        string $name
    ) {
        $this->slug = $slug;
        $this->name = $name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
