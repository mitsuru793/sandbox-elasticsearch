<?php
declare(strict_types=1);

namespace Php;

final class NestdDataTypeTest extends TestBase
{
    public function testHoge()
    {
        $doc = [
            'group' => 'fans',
            'user' => [
                [
                    'first' => 'Taro',
                    'last' => 'Yamada',
                ],
                [
                    'first' => 'Hanako',
                    'last' => 'Suzuki',
                ],
            ],
        ];
        $this->addDoc($doc);

        $query['query']['bool']['must'] = [
            ['match' => ['user.first' => 'Taro']],
            ['match' => ['user.last' => 'Suzuki']],
        ];
        $resultSet = $this->type->search($query);
        $this->assertNotEmpty($resultSet->getDocuments());

        // nestedあり

        $this->createIndex();
        $this->type->setMapping([
            'user' => [
                'type' => 'nested',
            ],
        ]);
        $this->addDoc($doc);
        $this->index->refresh();

        $resultSet = $this->type->search($query);
        $this->assertEmpty($resultSet->getDocuments());

        // 通常のmustではhitしないのは同じ
        unset($query);
        $query['query']['bool']['must'] = [
            ['match' => ['user.first' => 'Taro']],
            ['match' => ['user.last' => 'Yamada']],
        ];
        $resultSet = $this->type->search($query);
        $this->assertEmpty($resultSet->getDocuments());

        // nestedをqueryに指定する
        unset($query);
        $query['query']['nested']['path'] = 'user';
        $query['query']['nested']['query']['bool']['must'] = [
            ['match' => ['user.first' => 'Taro']],
            ['match' => ['user.last' => 'Yamada']],
        ];
        $resultSet = $this->type->search($query);
        $this->assertNotEmpty($resultSet->getDocuments());
    }
}