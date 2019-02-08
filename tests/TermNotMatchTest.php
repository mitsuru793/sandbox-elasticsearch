<?php
declare(strict_types=1);

namespace Php;

use Elastica\Document;

/**
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-term-query.html
 */
final class TermNotMatchTest extends TestBase
{
    /**
     * @throws \Elastica\Exception\InvalidException
     * @throws \Elastica\Exception\ResponseException
     */
    public function createIndex()
    {
        $this->index->create([
            'mappings' => [
                '_doc' => [
                    'properties' => [
                        'full_text' => [
                            'type' => 'text',
                        ],
                        'exact_value' => [
                            'type' => 'keyword',
                        ],
                    ],
                ],
            ],
        ], true);
    }

    public function testMain()
    {
        $this->addDoc([
            'full_text' => 'Quick Foxes!',
            'exact_value' => 'Quick Foxes!',
        ]);

        $query['query']['term']['exact_value'] = 'Quick Foxes!';
        $resultSet = $this->type->search($query);
        $results = $resultSet->getDocuments();
        $this->assertNotEmpty($results);
        unset($query);

        $query['query']['term']['full_text'] = 'Quick Foxes!';
        $resultSet = $this->type->search($query);
        $results = $resultSet->getDocuments();
        $this->assertEmpty($results);
        unset($query);

        $query['query']['term']['full_text'] = 'foxes';
        $resultSet = $this->type->search($query);
        $results = $resultSet->getDocuments();
        $this->assertNotEmpty($results);
        unset($query);

        $query['query']['match']['full_text'] = 'Quick Foxes!';
        $resultSet = $this->type->search($query);
        $results = $resultSet->getDocuments();
        $this->assertNotEmpty($results);
        unset($query);
    }
}