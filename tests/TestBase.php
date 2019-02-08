<?php
declare(strict_types=1);

namespace Php;

use Elastica\Client;
use Elastica\Document;
use Elastica\Index;
use Elastica\Response;
use Elastica\Type;
use PHPUnit\Framework\TestCase;

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
}