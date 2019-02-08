<?php
declare(strict_types=1);

namespace Php;

use Elastica\Document;
use Elastica\Index;
use Elastica\Request;

/**
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/docs-reindex.html
 */
final class ReIndexTest extends TestBase
{
    protected function createIndex()
    {
        return;
    }

    public function testCopyDocuments()
    {
        $sourceIndex = $this->initIndex('source_index');
        $destIndex = $this->initIndex('dest_index');

        $sourceType = $sourceIndex->getType('_doc');
        $sourceType->addDocuments([
            new Document(1, ['name' => 'mike']),
            new Document(2, ['name' => 'jane']),
        ]);
        $this->client->refreshAll();

        $q['query']['match_all'] = (object)[];
        $this->assertEquals(2, $sourceIndex->search($q)->getTotalHits());
        $this->assertEquals(0, $destIndex->search($q)->getTotalHits());

        $this->client->request('_reindex', Request::POST, [
            'source' => [
                'index' => 'source_index',
            ],
            'dest' => [
                'index' => 'dest_index',
            ],
        ]);
        $this->client->refreshAll();
        $this->assertEquals(2, $sourceIndex->search($q)->getTotalHits());
        $this->assertEquals(2, $destIndex->search($q)->getTotalHits());

        /** @var Document[] $docs */
        $docs = $destIndex->search($q)->getDocuments();
        $names = [
            $docs[0]->get('name'),
            $docs[1]->get('name'),
        ];
        $this->assertCount(2, $names);
        $this->assertContains('mike', $names);
        $this->assertContains('jane', $names);
    }

    /**
     * @dataProvider versionParamsProvider
     */
    public function testMaintainVersionOfDocument($params, $srcVersion, $destVersion)
    {
        $sourceIndex = $this->initIndex('source_index');
        $destIndex = $this->initIndex('dest_index');

        $sourceType = $sourceIndex->getType('_doc');
        $sourceType->addDocument(new Document(1, ['name' => 'mike']));
        $sourceType->addDocument(new Document(1, ['name' => 'jane']));
        $this->client->refreshAll();

        $this->client->request('_reindex', Request::POST, $params);

        $sourceDoc = $sourceIndex->getType('_doc')->getDocument(1);
        $this->assertEquals($srcVersion, $sourceDoc->getVersion());

        $destDoc = $destIndex->getType('_doc')->getDocument(1);
        $this->assertEquals($destVersion, $destDoc->getVersion());
    }

    public function versionParamsProvider()
    {
        // [parameters, source doc version, dest doc version]
        $patterns = [];

        $params = [
            'source' => [
                'index' => 'source_index',
            ],
            'dest' => [
                'index' => 'dest_index',
            ],
        ];

        $p = $params;
        unset($p['dest']['version_type']);
        $patterns[] = [$p, 2, 1];

        $p = $params;
        $p['dest']['version_type'] = 'internal';
        $patterns[] = [$p, 2, 1];

        $p = $params;
        $p['dest']['version_type'] = 'external';
        $patterns[] = [$p, 2, 2];

        return $patterns;
    }

    private function initIndex(string $index): Index
    {
        $index = $this->client->getIndex($index);

        if ($index->exists()) {
            $index->delete();
        }

        $index->create([
            'settings' => [
                'index.refresh_interval' => -1,
            ],
        ]);
        return $index;
    }
}