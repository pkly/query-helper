<?php

declare(strict_types=1);

namespace Pkly\QueryHelper\Tests\PHPStan\Data;

use Pkly\QueryHelper\Model;
use Pkly\QueryHelper\QueryHelper;
use Pkly\QueryHelper\Tests\Fixtures\Entity\Post;
use Pkly\QueryHelper\Tests\Fixtures\Model\PostSummary;
use Pkly\QueryHelper\Tests\Fixtures\Model\UserSummary;

use function PHPStan\Testing\assertType;

/**
 * @param QueryHelper<Post, int>                 $helper
 * @param non-empty-array<string, Model<object>> $dynamic
 */
function test(
    QueryHelper $helper,
    array $dynamic
): void {
    $rows = $helper->modelRows([
        'post' => Model::of(PostSummary::class, ['IDENTITY(entity.author)', 'title']),
        'author' => Model::of(UserSummary::class, ['a.id', 'a.name']),
    ]);

    assertType(
        'Doctrine\Common\Collections\Collection<int, array{post: Pkly\QueryHelper\Tests\Fixtures\Model\PostSummary, author: Pkly\QueryHelper\Tests\Fixtures\Model\UserSummary}>',
        $rows
    );

    $row = $helper->modelRow([
        'post' => Model::of(PostSummary::class, ['IDENTITY(entity.author)', 'title']),
    ]);

    assertType('array{post: Pkly\QueryHelper\Tests\Fixtures\Model\PostSummary}|null', $row);

    assertType('Doctrine\Common\Collections\Collection<int, array<string, object>>', $helper->modelRows($dynamic));
    assertType('array<string, object>|null', $helper->modelRow($dynamic));
}
