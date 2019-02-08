<?php
declare(strict_types=1);

namespace Php;

use Elastica\Document;
use Elastica\Result;
use Elastica\Util;

final class EsExampleTest extends TestBase
{
    /**
     * https://www.elastic.co/guide/en/elasticsearch/guide/current/slop.html
     * @dataProvider slopProvider
     */
    public function testSlop($slop, $expected)
    {
        $doc = new Document(1, ['title' => 'quick 1 2 fox']);
        $this->type->addDocument($doc);
        $this->index->refresh();

        $resultSet = $this->type->search([
            'query' => [
                'match_phrase' => [
                    'title' => [
                        'query' => 'quick fox',
                        'slop' => $slop,
                    ],
                ],
            ],
        ]);
        $this->assertSame($expected, $resultSet->getTotalHits());
    }

    public function slopProvider()
    {
        return [
            [0, 0],
            [1, 0],
            [2, 1],
            [3, 1],
        ];
    }

    public function testSort()
    {
        $mapping = new \Elastica\Type\Mapping();
        $mapping->setType($this->type);
        $mapping->setProperties([
            'name' => ['type' => 'keyword'],
            'age' => ['type' => 'integer'],
        ]);
        $mapping->send();

        $docs = [];
        $docs[] = new Document(1, ['name' => 'a', 'age' => 18]);
        $docs[] = new Document(2, ['name' => 'b', 'age' => 20]);
        $docs[] = new Document(3, ['name' => 'c', 'age' => 25]);
        $docs[] = new Document(4, ['name' => 'e', 'age' => 18]);
        $docs[] = new Document(5, ['name' => 'f', 'age' => 20]);
        $this->type->addDocuments($docs);
        $this->index->refresh();

        // without order
        $resultSet = $this->type->search([
            'query' => ['match_all' => (object)[]],
            'sort' => ['_id'],
        ]);
        $results = $resultSet->getResults();
        $ids = array_map(function (Result $result) {
            return $result->getId();
        }, $results);
        $this->assertSame(['1', '2', '3', '4', '5'], $ids);

        // with desc
        $resultSet = $this->type->search([
            'query' => ['match_all' => (object)[]],
            'sort' => [['_id' => 'desc']],
        ]);
        $results = $resultSet->getResults();
        $ids = array_map(function (Result $result) {
            return $result->getId();
        }, $results);
        $this->assertSame(['5', '4', '3', '2', '1'], $ids);

        // two fields
        $resultSet = $this->type->search([
            'query' => ['match_all' => (object)[]],
            'sort' => [
                ['age' => 'desc'],
                ['name' => 'desc'],
            ],
        ]);
        $results = $resultSet->getDocuments();
        $this->assertSame(['name' => 'c', 'age' => 25], $results[0]->getData());
        $this->assertSame(['name' => 'f', 'age' => 20], $results[1]->getData());
        $this->assertSame(['name' => 'b', 'age' => 20], $results[2]->getData());
        $this->assertSame(['name' => 'e', 'age' => 18], $results[3]->getData());
        $this->assertSame(['name' => 'a', 'age' => 18], $results[4]->getData());
    }

    public function testAndOrOperator()
    {
        $docs = [];
        $docs[] = new Document(1, ['name' => 'Yamada Taro', 'sex' => 'man']);
        $docs[] = new Document(2, ['name' => 'Yamada Hanako', 'sex' => 'woman']);
        $docs[] = new Document(3, ['name' => 'Suzuki Koji', 'sex' => 'man']);
        $this->type->addDocuments($docs);
        $this->index->refresh();

        $query = [
            'query' => [
                'match' => [
                    'name' => [],
                ],
            ],
            'sort' => [['_id' => 'asc']],
        ];

        $query['query']['match']['name'] = [
            'query' => 'yamada suzuki',
            'operator' => 'or',
        ];
        $resultSet = $this->type->search($query);
        $results = $resultSet->getDocuments();
        $this->assertCount(3, $results);
        $this->assertSame('Yamada Taro', $results[0]->getData()['name']);
        $this->assertSame('Yamada Hanako', $results[1]->getData()['name']);
        $this->assertSame('Suzuki Koji', $results[2]->getData()['name']);

        $query['query']['match']['name'] = [
            'query' => 'yamada hanako',
            'operator' => 'and',
        ];
        $docs = $this->type->search($query)->getDocuments();
        $this->assertCount(1, $docs);
        $this->assertSame('Yamada Hanako', $docs[0]->getData()['name']);

        // ANDは意味がないため、query_stringを使う
        $query['query']['match']['name'] = [
            'query' => 'yamada AND hanako',
        ];
        $docs = $this->type->search($query)->getDocuments();
        $this->assertCount(2, $docs);
        $this->assertSame('Yamada Taro', $docs[0]->getData()['name']);
        $this->assertSame('Yamada Hanako', $docs[1]->getData()['name']);

        // ANDは意味がないため、query_stringを使う
        $query['query']['match']['name'] = [
            'query' => 'yamada \&& hanako',
        ];
        $docs = $this->type->search($query)->getDocuments();
        $this->assertCount(2, $docs);
        $this->assertSame('Yamada Taro', $docs[0]->getData()['name']);
        $this->assertSame('Yamada Hanako', $docs[1]->getData()['name']);
    }

    /**
     * @dataProvider  utilEscapeProvider
     */
    public function testUtilEscape($input, $expected)
    {
        $actual = Util::replaceBooleanWordsAndEscapeTerm($input);
        $this->assertSame($expected, $actual);
    }

    public function utilEscapeProvider()
    {
        return [
            ['a AND b', 'a \&& b'],
            ['a OR b', 'a \|| b'],
            ['a && b', 'a \&& b'],
        ];
    }

    public function testMultiMatchWithPhrase()
    {
        $this->type->addDocuments([
            new Document(1, ['name' => 'Yamada Taro', 'sex' => 'man']),
            new Document(2, ['name' => 'Yamada Hanako', 'sex' => 'woman']),
            new Document(3, ['name' => 'Suzuki Koji', 'sex' => 'man']),
        ]);
        $this->index->refresh();

        $query = [
            'query' => [
                'term' => [],
            ],
            'sort' => [['_id' => 'asc']],
        ];

        $query['query']['term'] = [
            'name' => 'yamada Taro',
        ];
        $resultSet = $this->type->search($query);
        $results = $resultSet->getDocuments();
        $this->assertEmpty($results);

        $query['query']['term'] = [
            'name' => 'yamada',
        ];
        $results = $resultSet->getDocuments();
        $this->assertEmpty($results);

        $query['query']['term'] = (object)[
            'name' => 'Yamada',
        ];
        $resultSet = $this->type->search($query);
        $results = $resultSet->getDocuments();
        $this->assertEmpty($results);
    }
}

