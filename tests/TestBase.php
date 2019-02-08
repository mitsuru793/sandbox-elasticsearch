<?php
declare(strict_types=1);

namespace Php;

use Elastica\Client;
use Elastica\Index;
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

    /**
     * @throws \Elastica\Exception\InvalidException
     * @throws \Elastica\Exception\ResponseException
     */
    public function setUp()
    {
        $this->client = new Client();

        $this->index = $this->client->getIndex('test-index');
        $this->clearIndex();
        $this->createIndex();
        $this->type = $this->index->getType('test-type');
    }

    /**
     * @throws \Elastica\Exception\InvalidException
     * @throws \Elastica\Exception\ResponseException
     */
    protected function createIndex()
    {
        $this->index->create([], true);
    }

    protected function clearIndex()
    {
        if ($this->index->exists()) {
            $this->index->delete();
        }
    }
}