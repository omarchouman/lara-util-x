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
    // Pagination limits
    // -----------------------------------------------------------------------

    public function test_per_page_is_capped()
    {
        $controller = new ProductCrudController(new CrudProduct());

        $response = $controller->getAllRecords(Request::create('/products?per_page=1000000'));

        $this->assertEquals(100, $response->getData(true)['meta']['per_page']);
    }

    public function test_per_page_below_the_cap_is_honoured()
    {
        $controller = new ProductCrudController(new CrudProduct());

        $response = $controller->getAllRecords(Request::create('/products?per_page=2'));

        $this->assertEquals(2, $response->getData(true)['meta']['per_page']);
    }

    public function test_invalid_per_page_falls_back_to_the_default()
    {
        $controller = new ProductCrudController(new CrudProduct());

        $response = $controller->getAllRecords(Request::create('/products?per_page=0'));

        $this->assertEquals(15, $response->getData(true)['meta']['per_page']);
    }

    // -----------------------------------------------------------------------
    // Unique rules on update
    // -----------------------------------------------------------------------

    public function test_unique_rule_is_rewritten_when_it_is_not_the_last_rule()
    {
        $controller = new ProductCrudController(new CrudProduct());

        $rule = $controller->exposeIgnoreCurrentRecord('required|unique:crud_products,name|max:20', 'name', 7);

        $this->assertEquals('required|unique:crud_products,name,7|max:20', $rule);
    }

    public function test_unique_rule_preserves_additional_where_clauses()
    {
        $controller = new ProductCrudController(new CrudProduct());

        // Rebuilding from table and column alone dropped the tenant scope,
        // silently turning per-tenant uniqueness into global uniqueness.
        $rule = $controller->exposeIgnoreCurrentRecord(
            'required|unique:users,email,NULL,id,tenant_id,7',
            'email',
            5
        );

        $this->assertEquals('required|unique:users,email,5,id,tenant_id,7', $rule);
    }

    public function test_unique_rule_replaces_an_existing_ignore_id()
    {
        $controller = new ProductCrudController(new CrudProduct());

        $rule = $controller->exposeIgnoreCurrentRecord('unique:users,email,99', 'email', 5);

        $this->assertEquals('unique:users,email,5', $rule);
    }

    public function test_unique_rule_without_a_column_uses_the_field_name()
    {
        $controller = new ProductCrudController(new CrudProduct());

        $rule = $controller->exposeIgnoreCurrentRecord('unique:crud_products', 'name', 7);

        $this->assertEquals('unique:crud_products,name,7', $rule);
    }

    public function test_non_unique_rules_are_left_alone()
    {
        $controller = new ProductCrudController(new CrudProduct());

        $this->assertEquals('required|max:20', $controller->exposeIgnoreCurrentRecord('required|max:20', 'name', 7));
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

    public function exposeIgnoreCurrentRecord(mixed $rule, string $field, mixed $id): mixed
    {
        return $this->ignoreCurrentRecord($rule, $field, $id);
    }
}

class UnsortableProductCrudController extends CrudController
{
}
