<?php

declare(strict_types=1);

namespace Pkly\QueryHelper\Tests;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Pkly\QueryHelper\Tests\Fixtures\Entity\Post;
use Pkly\QueryHelper\Tests\Fixtures\Entity\Tag;
use Pkly\QueryHelper\Tests\Fixtures\Entity\User;

abstract class AbstractDoctrineTestCase extends TestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__.'/Fixtures/Entity']));
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $this->entityManager = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema([
            $this->entityManager->getClassMetadata(User::class),
            $this->entityManager->getClassMetadata(Post::class),
            $this->entityManager->getClassMetadata(Tag::class),
        ]);
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();

        parent::tearDown();
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    protected function persist(
        object ...$entities
    ): void {
        foreach ($entities as $entity) {
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
    }
}
