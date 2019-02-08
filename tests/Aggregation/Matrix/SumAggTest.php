<?php
declare(strict_types=1);

namespace Php\Aggregation\Matrix;

use Elastica\ResultSet;
use Php\TestBase;

final class SumAggTest extends TestBase
{
    public function testSumViewsInTagNames()
    {
        $this->initTypeOfTagNames();

        $query['aggs']['tags'] = [
            'terms' => [
                'field' => 'tags',
                'order' => ['total_views' => 'desc'],
            ],
            'aggs' => [
                'total_views' => [
                    'sum' => ['field' => 'views'],
                ],
            ],
        ];

        $result = $this->type->search($query);
        $this->assertSumTotalViewsEachTagNames($result);
    }

    public function testSumScoreInTagNames()
    {
        $this->initTypeOfTagNames();

        $query['query']['match']['title'] = 'is';
        $query['aggs']['tags'] = [
            'terms' => [
                'field' => 'tags',
                'order' => ['total_views' => 'desc'],
            ],
            'aggs' => [
                'total_views' => [
                    'sum' => ['script' => '_score'],
                ],
            ],
        ];
        $result = $this->type->search($query);
        $tagBuckets = $result->getAggregation('tags')['buckets'];
        $tagBuckets = collect($tagBuckets);
        $tagNames = $tagBuckets->pluck('key')->toArray();
        $this->assertEquals(['diary', 'first'], $tagNames);

        $views = $tagBuckets->pluck('total_views.value')->toArray();
        $this->assertEquals([1.0597954094409943, 0.28768208622932434], $views);
    }

    public function testSumViewsInTagObject()
    {
        $this->createMapping([
            'title' => ['type' => 'keyword'],
            'views' => ['type' => 'integer'],
            'tags' => [
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'keyword'],
                ],
            ],
        ]);
        $this->addDocs([
            [
                'title' => 'My name is Mike.',
                'views' => 10,
                'tags' => [
                    ['id' => 1, 'name' => 'first'],
                    ['id' => 2, 'name' => 'diary'],
                ],
            ],
            [
                'title' => 'Today is happy',
                'views' => 5,
                'tags' => [
                    ['id' => 2, 'name' => 'diary'],
                ],
            ],
            [
                'title' => 'Top 5 My Hobbies.',
                'views' => 10,
                'tags' => [
                    ['id' => 3, 'name' => 'hobby'],
                    ['id' => 4, 'name' => 'ranking'],
                ],
            ],
            [
                'title' => 'I watch a Japanese anime.',
                'views' => 25,
                'tags' => [
                    ['id' => 2, 'name' => 'diary'],
                    ['id' => 3, 'name' => 'hobby'],
                ],
            ],
        ]);

        $query['aggs']['tags'] = [
            'terms' => [
                'field' => 'tags.name',
                'order' => ['total_views' => 'desc'],
            ],
            'aggs' => [
                'total_views' => [
                    'sum' => ['field' => 'views'],
                ],
            ],
        ];

        $result = $this->type->search($query);
        $this->assertSumTotalViewsEachTagNames($result);
    }

    private function initTypeOfTagNames()
    {
        $this->createMapping([
            'title' => ['type' => 'text'],
            'views' => ['type' => 'integer'],
            'tags' => ['type' => 'keyword'],
        ]);
        $this->addDocs([
            [
                'title' => 'My name is Mike.',
                'views' => 10,
                'tags' => ['first', 'diary'],
            ],
            [
                'title' => 'Today is happy',
                'views' => 5,
                'tags' => ['diary'],
            ],
            [
                'title' => 'Top 5 My Hobbies.',
                'views' => 10,
                'tags' => ['hobby', 'ranking'],
            ],
            [
                'title' => 'I watch a Japanese anime.',
                'views' => 25,
                'tags' => ['hobby', 'diary'],
            ],
        ]);
    }

    private function assertSumTotalViewsEachTagNames(ResultSet $result)
    {
        $tagBuckets = $result->getAggregation('tags')['buckets'];
        $tagBuckets = collect($tagBuckets);
        $this->assertSame([
            'key' => 'diary',
            'doc_count' => 3,
            'total_views' => ['value' => 40.0],
        ], $tagBuckets[0]);

        $tagNames = $tagBuckets->pluck('key')->toArray();
        $this->assertEquals(['diary', 'hobby', 'first', 'ranking'], $tagNames);

        $views = $tagBuckets->pluck('total_views.value')->toArray();
        $this->assertEquals([40, 35, 10, 10], $views);
    }
}