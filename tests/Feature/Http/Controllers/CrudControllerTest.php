<?php

namespace LaraUtilX\Tests\Feature\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use LaraUtilX\Http\Controllers\CrudController;
use LaraUtilX\Tests\TestCase;

class CrudControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crud_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('secret_code');
            $table->timestamps();
        });

        // Insertion order is deliberately not alphabetical, so a sorted result
        // is distinguishable from an unsorted one.
        CrudProduct::insert([
            ['id' => 1, 'name' => 'Banana', 'secret_code' => 'ccc'],
            ['id' => 2, 'name' => 'Apple', 'secret_code' => 'bbb'],
            ['id' => 3, 'name' => 'Cherry', 'secret_code' => 'aaa'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('crud_products');

        parent::tearDown();
    }

    private function names(Request $request, ?CrudController $controller = null): array
    {
        $controller = $controller ?: new ProductCrudController(new CrudProduct());

        $response = $controller->getAllRecords($request);

        return array_column($response->getData(true)['data'], 'name');
    }

    // -----------------------------------------------------------------------
    // Sorting
    // -----------------------------------------------------------------------

    public function test_sorts_by_whitelisted_column()
    {
        $names = $this->names(Request::create('/products?sort_by=name'));

        $this->assertEquals(['Apple', 'Banana', 'Cherry'], $names);
    }

    public function test_sorts_descending_when_requested()
    {
        $names = $this->names(Request::create('/products?sort_by=name&sort_direction=desc'));

        $this->assertEquals(['Cherry', 'Banana', 'Apple'], $names);
    }

    public function test_ignores_sort_on_non_whitelisted_column()
    {
        $names = $this->names(Request::create('/products?sort_by=secret_code'));

        // secret_code ordering would be Cherry, Apple, Banana.
        $this->assertEquals(['Banana', 'Apple', 'Cherry'], $names);
    }

    public function test_ignores_sort_when_no_sortable_fields_are_declared()
    {
        $names = $this->names(
            Request::create('/products?sort_by=name'),
            new UnsortableProductCrudController(new CrudProduct())
        );

        $this->assertEquals(['Banana', 'Apple', 'Cherry'], $names);
    }

    public function test_invalid_sort_direction_falls_back_to_ascending()
    {
        $names = $this->names(Request::create('/products?sort_by=name&sort_direction=; DROP TABLE'));

        $this->assertEquals(['Apple', 'Banana', 'Cherry'], $names);
    }

    // -----------------------------------------------------------------------
    // Delete
    // -----------------------------------------------------------------------

    public function test_delete_returns_200_with_a_readable_body()
    {
        $controller = new ProductCrudController(new CrudProduct());

        $response = $controller->deleteRecord(1);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Record deleted successfully', $response->getData(true)['message']);
        $this->assertNull(CrudProduct::find(1));
    }
}

class CrudProduct extends Model
{
    protected $table = 'crud_products';
    protected $guarded = [];
    public $timestamps = false;
}

class ProductCrudController extends CrudController
{
    protected array $sortableFields = ['name'];
}

class UnsortableProductCrudController extends CrudController
{
}
