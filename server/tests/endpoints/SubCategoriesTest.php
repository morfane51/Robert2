<?php
declare(strict_types=1);

namespace Robert2\Tests;

use Fig\Http\Message\StatusCodeInterface as StatusCode;

final class SubCategoriesTest extends ApiTestCase
{
    public static function data(int $id)
    {
        return static::_dataFactory($id, [
            [
                'id' => 1,
                'name' => 'Mixeurs',
                'category_id' => 1,
            ],
            [
                'id' => 2,
                'name' => 'Processeurs',
                'category_id' => 1,
            ],
            [
                'id' => 3,
                'name' => 'Projecteurs',
                'category_id' => 2,
            ],
            [
                'id' => 4,
                'name' => 'Gradateurs',
                'category_id' => 2,
            ],
        ]);
    }

    public function testCreateSubCategoryNoCategoryId()
    {
        $this->client->post('/api/subcategories', ['name' => 'Fail SubCategory']);
        $this->assertApiValidationError([
            'category_id' => [
                "This field is mandatory",
                "This field must contain only numbers",
            ],
        ]);
    }

    public function testCreateSubCategory()
    {
        $this->client->post('/api/subcategories', [
            'name' => 'New SubCategory',
            'category_id' => 1,
        ]);
        $this->assertStatusCode(StatusCode::STATUS_CREATED);
        $this->assertResponseData([
            'id' => 5,
            'name' => 'New SubCategory',
            'category_id' => 1,
        ]);
    }

    public function testUpdateSubCategoryNoData()
    {
        $this->client->put('/api/subcategories/1', []);
        $this->assertStatusCode(StatusCode::STATUS_BAD_REQUEST);
        $this->assertApiErrorMessage("No data was provided.");
    }

    public function testUpdateSubCategoryNotFound()
    {
        $this->client->put('/api/subcategories/999', [
            'something' => '__inexistant__',
        ]);
        $this->assertStatusCode(StatusCode::STATUS_NOT_FOUND);
    }

    public function testUpdateSubCategory()
    {
        $updatedData = [
            'name' => 'Mixers edited',
        ];
        $this->client->put('/api/subcategories/1', $updatedData);
        $this->assertStatusCode(StatusCode::STATUS_OK);
        $this->assertResponseData(
            array_replace(self::data(1), $updatedData)
        );
    }

    public function testDeleteSubCategory()
    {
        $this->client->delete('/api/subcategories/3');
        $this->assertStatusCode(StatusCode::STATUS_NO_CONTENT);

        $this->client->get('/api/subcategories/3');
        $this->assertStatusCode(StatusCode::STATUS_NOT_FOUND);
    }
}
