<?php
declare(strict_types=1);

namespace Php;

use Elastica\Client;
use Elastica\Document;
use Elastica\Index;
use Elastica\Response;
use Elastica\ResultSet;
use Elastica\Type;
use Elastica\Type\Mapping;
use PHPUnit\Framework\TestCase;
use Tightenco\Collect\Support\Collection;

abstract class TestBase extends TestCase
{
    /** @var Index */
    protected $index;

    /** @var Type */
    protected $type;

    /** @var Client */
    protected $client;

    /** @var int */
    protected $nextId;

    /**
     * @throws \Elastica\Exception\InvalidException
     * @throws \Elastica\Exception\ResponseException
     */
    public function setUp()
    {
        $this->nextId = 1;

        $this->client = new Client();
        $this->index = $this->client->getIndex('test-index');
        $this->createIndex();
        $this->type = $this->index->getType('_doc');
    }

    /**
     * @throws \Elastica\Exception\InvalidException
     * @throws \Elastica\Exception\ResponseException
     */
    protected function createIndex()
    {
        $this->clearIndex();
        $this->index->create([], true);
    }

    protected function clearIndex()
    {
        if ($this->index->exists()) {
            $this->index->delete();
        }
    }

    protected function createMapping(array $properties)
    {
        $mapping = new Mapping($this->type);
        $mapping->setProperties($properties);
        $mapping->send();
    }

    protected function addDoc(array $data): Response
    {
        $doc = new Document($this->nextId, $data);
        $this->nextId++;
        $response = $this->type->addDocument($doc);

        $this->index->refresh();
        return $response;
    }

    protected function addDocs(array $dataList): Response
    {
        $docs = array_map(function (array $data) {
            $doc = new Document($this->nextId, $data);
            $this->nextId++;
            return $doc;
        }, $dataList);
        $response = $this->type->addDocuments($docs);

        $this->index->refresh();
        return $response;
    }

    protected function dump($value): void
    {
        if ($value instanceof ResultSet) {
            $value = $value->getResponse()->getData();
        }

        if ($value instanceof Collection) {
            $value = $value->toArray();
        }

        dump($value);
    }


    protected function dumpDocs(ResultSet $value): void
    {
        $docs = $value->getDocuments();
        $docs = array_map(function (Document $doc) {
            return $doc->toArray();
        }, $docs);
        dump($docs);
    }

    protected function dumpAgg(ResultSet $value, string $name): void
    {
        $agg = $value->getAggregation($name);
        dump($agg);
    }

    protected function dumpAggs(ResultSet $value): void
    {
        $aggs = $value->getAggregations();
        dump($aggs);
    }

    protected function assertCountDocs(int $expected, ResultSet $result)
    {
        $this->assertCount($expected, $result->getDocuments());
    }

    protected function assertEmptyDocs(ResultSet $result)
    {
        $this->assertEmpty($result->getDocuments());
    }
}