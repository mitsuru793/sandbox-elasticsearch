<?php
declare(strict_types=1);

namespace Php\Aggregation\Bucket;

use Php\TestBase;
use Tightenco\Collect\Support\Collection;

final class TermAggTest extends TestBase
{
    public function testMain()
    {
        $this->createMapping([
            'title' => [
                'type' => 'text',
                'fielddata' => true,
            ],
            'tags' => [
                'type' => 'keyword',
            ],
        ]);
        $this->addDocs([
            [
                'title' => 'My today news.',
                'tags' => ['news', 'diary'],
            ],
            [
                'title' => 'Today is Sunday',
                'tags' => ['diary'],
            ],
            [
                'title' => 'I will host a game party.',
                'tags' => ['news', 'diary', 'game'],
            ],
        ]);

        $query['aggs']['tags']['terms']['field'] = 'tags';
        $tags = $this->searchAndGetBucket($query);
        $this->assertSame(['key' => 'game', 'doc_count' => 1], $tags[0]);

        $tagNames = $tags->pluck('key');
        $this->assertContains('game', $tagNames);
        $this->assertContains('news', $tagNames);
        $this->assertContains('diary', $tagNames);

        $query['query']['match']['title'] = 'today';
        $tags = $this->searchAndGetBucket($query);
        $tagNames = $tags->pluck('key');
        $this->assertNotContains('game', $tagNames);
        $this->assertContains('news', $tagNames);
        $this->assertContains('diary', $tagNames);
    }

    private function searchAndGetBucket($query): Collection
    {
        $result = $this->type->search($query);
        $tags = $result->getAggregation('tags')['buckets'];
        return collect($tags)->sortBy('doc_count')->values();
    }
}