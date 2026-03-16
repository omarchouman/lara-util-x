<?php

namespace LaraUtilX\Tests\Unit\Console;

use LaraUtilX\Tests\TestCase;

class MakeCrudCommandTest extends TestCase
{
    private array $cleanup = [];

    // -----------------------------------------------------------------------
    // Teardown
    // -----------------------------------------------------------------------

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    private function track(string $path): string
    {
        $this->cleanup[] = $path;
        return $path;
    }

    private function trackMigration(string $table): string
    {
        $files = glob(database_path("migrations/*_create_{$table}_table.php"));
        if (! empty($files)) {
            $this->track($files[0]);
            return $files[0];
        }
        return '';
    }

    // -----------------------------------------------------------------------
    // Command Basics
    // -----------------------------------------------------------------------

    public function test_command_is_registered()
    {
        $this->assertTrue(
            collect($this->app['Illuminate\Contracts\Console\Kernel']->all())
                ->has('make:crud')
        );
    }

    public function test_command_exits_successfully_with_minimal_args()
    {
        $this->artisan('make:crud', ['name' => 'Article'])
            ->assertExitCode(0);

        $this->track(app_path('Models/Article.php'));
        $this->track(app_path('Http/Controllers/ArticleController.php'));
        $this->trackMigration('articles');
    }

    public function test_command_outputs_success_message()
    {
        $this->artisan('make:crud', ['name' => 'Article'])
            ->expectsOutputToContain('CRUD scaffold generated successfully')
            ->assertExitCode(0);

        $this->track(app_path('Models/Article.php'));
        $this->track(app_path('Http/Controllers/ArticleController.php'));
        $this->trackMigration('articles');
    }

    public function test_model_name_is_converted_to_studly_case()
    {
        $this->artisan('make:crud', ['name' => 'blog_post'])
            ->assertExitCode(0);

        $this->assertFileExists($this->track(app_path('Models/BlogPost.php')));
        $this->assertFileExists($this->track(app_path('Http/Controllers/BlogPostController.php')));
        $this->trackMigration('blog_posts');
    }

    // -----------------------------------------------------------------------
    // Migration
    // -----------------------------------------------------------------------

    public function test_generates_migration_file()
    {
        $this->artisan('make:crud', ['name' => 'Product'])->assertExitCode(0);

        $path = $this->trackMigration('products');
        $this->assertNotEmpty($path, 'Migration file was not created');
        $this->assertFileExists($path);
    }

    public function test_migration_contains_correct_table_name()
    {
        $this->artisan('make:crud', ['name' => 'Product'])->assertExitCode(0);

        $content = file_get_contents($this->trackMigration('products'));
        $this->assertStringContainsString("Schema::create('products'", $content);
        $this->assertStringContainsString("Schema::dropIfExists('products'", $content);
    }

    public function test_migration_contains_string_column()
    {
        $this->artisan('make:crud', [
            'name'     => 'Product',
            '--fields' => 'name:string:required',
        ])->assertExitCode(0);

        $content = file_get_contents($this->trackMigration('products'));
        $this->assertStringContainsString("\$table->string('name')", $content);
    }

    public function test_migration_contains_nullable_modifier_when_rule_is_nullable()
    {
        $this->artisan('make:crud', [
            'name'     => 'Product',
            '--fields' => 'description:text:nullable',
        ])->assertExitCode(0);

        $content = file_get_contents($this->trackMigration('products'));
        $this->assertStringContainsString("\$table->text('description')->nullable()", $content);
    }

    public function test_migration_maps_all_field_types_correctly()
    {
        $this->artisan('make:crud', [
            'name'     => 'Product',
            '--fields' => 'name:string,bio:text,qty:integer,price:decimal,active:boolean,born:date,created:datetime,meta:json',
        ])->assertExitCode(0);

        $content = file_get_contents($this->trackMigration('products'));
        $this->assertStringContainsString("\$table->string('name')", $content);
        $this->assertStringContainsString("\$table->text('bio')", $content);
        $this->assertStringContainsString("\$table->integer('qty')", $content);
        $this->assertStringContainsString("\$table->decimal('price', 10, 2)", $content);
        $this->assertStringContainsString("\$table->boolean('active')", $content);
        $this->assertStringContainsString("\$table->date('born')", $content);
        $this->assertStringContainsString("\$table->dateTime('created')", $content);
        $this->assertStringContainsString("\$table->json('meta')", $content);
    }

    public function test_migration_adds_foreign_key_for_belongs_to()
    {
        $this->artisan('make:crud', [
            'name'         => 'Product',
            '--belongs-to' => ['User'],
        ])->assertExitCode(0);

        $content = file_get_contents($this->trackMigration('products'));
        $this->assertStringContainsString(
            "\$table->foreignId('user_id')->constrained('users')->cascadeOnDelete()",
            $content
        );
    }

    public function test_migration_adds_soft_deletes_column()
    {
        $this->artisan('make:crud', [
            'name'           => 'Product',
            '--soft-deletes' => true,
        ])->assertExitCode(0);

        $content = file_get_contents($this->trackMigration('products'));
        $this->assertStringContainsString("\$table->softDeletes()", $content);
    }

    public function test_migration_is_skipped_if_one_already_exists()
    {
        // Create the migration on the first run
        $this->artisan('make:crud', ['name' => 'Product'])->assertExitCode(0);
        $firstPath = $this->trackMigration('products');

        // Second run should skip and warn
        $this->artisan('make:crud', ['name' => 'Product', '--force' => true])
            ->expectsOutputToContain('Migration already exists')
            ->assertExitCode(0);

        // Only one migration file should exist
        $allFiles = glob(database_path('migrations/*_create_products_table.php'));
        $this->assertCount(1, $allFiles);

        $this->track(app_path('Models/Product.php'));
        $this->track(app_path('Http/Controllers/ProductController.php'));
    }

    // -----------------------------------------------------------------------
    // Model
    // -----------------------------------------------------------------------

    public function test_generates_model_file()
    {
        $this->artisan('make:crud', ['name' => 'Product'])->assertExitCode(0);

        $this->assertFileExists($this->track(app_path('Models/Product.php')));
        $this->track(app_path('Http/Controllers/ProductController.php'));
        $this->trackMigration('products');
    }

    public function test_model_has_correct_namespace_and_class()
    {
        $this->artisan('make:crud', ['name' => 'Product'])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Models/Product.php')));
        $this->assertStringContainsString('namespace App\Models;', $content);
        $this->assertStringContainsString('class Product extends Model', $content);

        $this->track(app_path('Http/Controllers/ProductController.php'));
        $this->trackMigration('products');
    }

    public function test_model_fillable_contains_fields()
    {
        $this->artisan('make:crud', [
            'name'     => 'Product',
            '--fields' => 'name:string:required,price:decimal:required',
        ])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Models/Product.php')));
        $this->assertStringContainsString("'name'", $content);
        $this->assertStringContainsString("'price'", $content);

        $this->track(app_path('Http/Controllers/ProductController.php'));
        $this->trackMigration('products');
    }

    public function test_model_fillable_contains_foreign_keys_from_belongs_to()
    {
        $this->artisan('make:crud', [
            'name'         => 'Product',
            '--belongs-to' => ['User'],
        ])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Models/Product.php')));
        $this->assertStringContainsString("'user_id'", $content);

        $this->track(app_path('Http/Controllers/ProductController.php'));
        $this->trackMigration('products');
    }

    public function test_model_casts_are_generated_for_typed_fields()
    {
        $this->artisan('make:crud', [
            'name'     => 'Product',
            '--fields' => 'price:decimal,active:boolean,meta:json,born:date,launched:datetime,qty:integer',
        ])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Models/Product.php')));
        $this->assertStringContainsString("'price' => 'decimal:2'", $content);
        $this->assertStringContainsString("'active' => 'boolean'", $content);
        $this->assertStringContainsString("'meta' => 'array'", $content);
        $this->assertStringContainsString("'born' => 'date'", $content);
        $this->assertStringContainsString("'launched' => 'datetime'", $content);
        $this->assertStringContainsString("'qty' => 'integer'", $content);

        $this->track(app_path('Http/Controllers/ProductController.php'));
        $this->trackMigration('products');
    }

    public function test_model_includes_soft_deletes_trait()
    {
        $this->artisan('make:crud', [
            'name'           => 'Product',
            '--soft-deletes' => true,
        ])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Models/Product.php')));
        $this->assertStringContainsString('use SoftDeletes;', $content);
        $this->assertStringContainsString('use Illuminate\Database\Eloquent\SoftDeletes;', $content);

        $this->track(app_path('Http/Controllers/ProductController.php'));
        $this->trackMigration('products');
    }

    public function test_model_generates_belongs_to_relationship()
    {
        $this->artisan('make:crud', [
            'name'         => 'Product',
            '--belongs-to' => ['User'],
        ])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Models/Product.php')));
        $this->assertStringContainsString('public function user()', $content);
        $this->assertStringContainsString('Relations\BelongsTo', $content);
        $this->assertStringContainsString('$this->belongsTo(\App\Models\User::class)', $content);

        $this->track(app_path('Http/Controllers/ProductController.php'));
        $this->trackMigration('products');
    }

    public function test_model_generates_has_many_relationship()
    {
        $this->artisan('make:crud', [
            'name'       => 'Product',
            '--has-many' => ['Review'],
        ])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Models/Product.php')));
        $this->assertStringContainsString('public function reviews()', $content);
        $this->assertStringContainsString('Relations\HasMany', $content);
        $this->assertStringContainsString('$this->hasMany(\App\Models\Review::class)', $content);

        $this->track(app_path('Http/Controllers/ProductController.php'));
        $this->trackMigration('products');
    }

    public function test_model_generates_has_one_relationship()
    {
        $this->artisan('make:crud', [
            'name'      => 'Product',
            '--has-one' => ['Image'],
        ])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Models/Product.php')));
        $this->assertStringContainsString('public function image()', $content);
        $this->assertStringContainsString('Relations\HasOne', $content);
        $this->assertStringContainsString('$this->hasOne(\App\Models\Image::class)', $content);

        $this->track(app_path('Http/Controllers/ProductController.php'));
        $this->trackMigration('products');
    }

    public function test_model_generates_belongs_to_many_relationship()
    {
        $this->artisan('make:crud', [
            'name'              => 'Product',
            '--belongs-to-many' => ['Tag'],
        ])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Models/Product.php')));
        $this->assertStringContainsString('public function tags()', $content);
        $this->assertStringContainsString('Relations\BelongsToMany', $content);
        $this->assertStringContainsString('$this->belongsToMany(\App\Models\Tag::class)', $content);

        $this->track(app_path('Http/Controllers/ProductController.php'));
        $this->trackMigration('products');
    }

    public function test_model_is_not_overwritten_without_force_flag()
    {
        $this->artisan('make:crud', ['name' => 'Product'])->assertExitCode(0);
        $path = $this->track(app_path('Models/Product.php'));

        file_put_contents($path, '<?php // sentinel');

        $this->artisan('make:crud', ['name' => 'Product'])
            ->expectsOutputToContain('already exists')
            ->assertExitCode(0);

        $this->assertStringContainsString('sentinel', file_get_contents($path));

        $this->track(app_path('Http/Controllers/ProductController.php'));
        $this->trackMigration('products');
    }

    public function test_model_is_overwritten_with_force_flag()
    {
        $this->artisan('make:crud', ['name' => 'Product'])->assertExitCode(0);
        $path = $this->track(app_path('Models/Product.php'));

        file_put_contents($path, '<?php // sentinel');

        $this->artisan('make:crud', ['name' => 'Product', '--force' => true])->assertExitCode(0);

        $this->assertStringNotContainsString('sentinel', file_get_contents($path));

        $this->track(app_path('Http/Controllers/ProductController.php'));
        $this->trackMigration('products');
    }

    // -----------------------------------------------------------------------
    // Controller
    // -----------------------------------------------------------------------

    public function test_generates_controller_file()
    {
        $this->artisan('make:crud', ['name' => 'Product'])->assertExitCode(0);

        $this->assertFileExists($this->track(app_path('Http/Controllers/ProductController.php')));
        $this->track(app_path('Models/Product.php'));
        $this->trackMigration('products');
    }

    public function test_controller_has_correct_namespace_and_class()
    {
        $this->artisan('make:crud', ['name' => 'Product'])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Http/Controllers/ProductController.php')));
        $this->assertStringContainsString('namespace App\Http\Controllers;', $content);
        $this->assertStringContainsString('class ProductController extends Controller', $content);
        $this->assertStringContainsString('use App\Models\Product;', $content);

        $this->track(app_path('Models/Product.php'));
        $this->trackMigration('products');
    }

    public function test_controller_has_all_crud_methods()
    {
        $this->artisan('make:crud', ['name' => 'Product'])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Http/Controllers/ProductController.php')));
        $this->assertStringContainsString('public function index(', $content);
        $this->assertStringContainsString('public function show(', $content);
        $this->assertStringContainsString('public function store(', $content);
        $this->assertStringContainsString('public function update(', $content);
        $this->assertStringContainsString('public function destroy(', $content);

        $this->track(app_path('Models/Product.php'));
        $this->trackMigration('products');
    }

    public function test_controller_store_contains_validation_rules()
    {
        $this->artisan('make:crud', [
            'name'     => 'Product',
            '--fields' => 'name:string:required|max:100,price:decimal:required|min:0',
        ])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Http/Controllers/ProductController.php')));
        $this->assertStringContainsString("'name' => 'required|max:100'", $content);
        $this->assertStringContainsString("'price' => 'required|min:0'", $content);

        $this->track(app_path('Models/Product.php'));
        $this->trackMigration('products');
    }

    public function test_controller_update_modifies_unique_rule_to_ignore_current_record()
    {
        $this->artisan('make:crud', [
            'name'     => 'Product',
            '--fields' => 'slug:string:required|unique:products',
        ])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Http/Controllers/ProductController.php')));
        $this->assertStringContainsString("unique:products,slug,' . \$product->id", $content);

        $this->track(app_path('Models/Product.php'));
        $this->trackMigration('products');
    }

    public function test_controller_store_has_exists_validation_for_belongs_to()
    {
        $this->artisan('make:crud', [
            'name'         => 'Product',
            '--belongs-to' => ['User'],
        ])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Http/Controllers/ProductController.php')));
        $this->assertStringContainsString("'user_id' => 'required|integer|exists:users,id'", $content);

        $this->track(app_path('Models/Product.php'));
        $this->trackMigration('products');
    }

    public function test_controller_update_uses_sometimes_for_belongs_to()
    {
        $this->artisan('make:crud', [
            'name'         => 'Product',
            '--belongs-to' => ['User'],
        ])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Http/Controllers/ProductController.php')));
        $this->assertStringContainsString("'user_id' => 'sometimes|integer|exists:users,id'", $content);

        $this->track(app_path('Models/Product.php'));
        $this->trackMigration('products');
    }

    public function test_controller_generates_custom_message_for_exists_rule()
    {
        $this->artisan('make:crud', [
            'name'         => 'Product',
            '--belongs-to' => ['User'],
        ])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Http/Controllers/ProductController.php')));
        $this->assertStringContainsString("'user_id.exists'", $content);
        $this->assertStringContainsString('does not exist in the users table', $content);

        $this->track(app_path('Models/Product.php'));
        $this->trackMigration('products');
    }

    public function test_controller_generates_custom_message_for_unique_rule()
    {
        $this->artisan('make:crud', [
            'name'     => 'Product',
            '--fields' => 'slug:string:required|unique:products',
        ])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Http/Controllers/ProductController.php')));
        $this->assertStringContainsString("'slug.unique'", $content);
        $this->assertStringContainsString('has already been taken', $content);

        $this->track(app_path('Models/Product.php'));
        $this->trackMigration('products');
    }

    public function test_controller_index_contains_search_logic_when_searchable_provided()
    {
        $this->artisan('make:crud', [
            'name'         => 'Product',
            '--fields'     => 'name:string,description:text',
            '--searchable' => 'name,description',
        ])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Http/Controllers/ProductController.php')));
        $this->assertStringContainsString("request->filled('search')", $content);
        $this->assertStringContainsString("['name', 'description']", $content);
        $this->assertStringContainsString('LIKE', $content);

        $this->track(app_path('Models/Product.php'));
        $this->trackMigration('products');
    }

    public function test_controller_index_has_no_search_logic_without_searchable()
    {
        $this->artisan('make:crud', ['name' => 'Product'])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Http/Controllers/ProductController.php')));
        $this->assertStringNotContainsString("request->filled('search')", $content);

        $this->track(app_path('Models/Product.php'));
        $this->trackMigration('products');
    }

    public function test_controller_index_uses_custom_per_page()
    {
        $this->artisan('make:crud', [
            'name'       => 'Product',
            '--per-page' => '25',
        ])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Http/Controllers/ProductController.php')));
        $this->assertStringContainsString("'per_page', 25)", $content);

        $this->track(app_path('Models/Product.php'));
        $this->trackMigration('products');
    }

    public function test_controller_eager_loads_relationships()
    {
        $this->artisan('make:crud', [
            'name'         => 'Product',
            '--belongs-to' => ['User'],
            '--has-many'   => ['Review'],
        ])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Http/Controllers/ProductController.php')));
        $this->assertStringContainsString("['user', 'reviews']", $content);

        $this->track(app_path('Models/Product.php'));
        $this->trackMigration('products');
    }

    public function test_controller_index_returns_paginated_meta()
    {
        $this->artisan('make:crud', ['name' => 'Product'])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Http/Controllers/ProductController.php')));
        $this->assertStringContainsString("'current_page'", $content);
        $this->assertStringContainsString("'last_page'", $content);
        $this->assertStringContainsString("'per_page'", $content);
        $this->assertStringContainsString("'total'", $content);

        $this->track(app_path('Models/Product.php'));
        $this->trackMigration('products');
    }

    public function test_controller_store_returns_201()
    {
        $this->artisan('make:crud', ['name' => 'Product'])->assertExitCode(0);

        $content = file_get_contents($this->track(app_path('Http/Controllers/ProductController.php')));
        $this->assertStringContainsString('], 201)', $content);

        $this->track(app_path('Models/Product.php'));
        $this->trackMigration('products');
    }

    // -----------------------------------------------------------------------
    // --register-routes
    // -----------------------------------------------------------------------

    public function test_register_routes_appends_route_to_api_php()
    {
        $routesPath = base_path('routes/api.php');
        $existed    = file_exists($routesPath);
        $original   = $existed ? file_get_contents($routesPath) : null;

        if (! is_dir(base_path('routes'))) {
            mkdir(base_path('routes'), 0755, true);
        }
        file_put_contents($routesPath, "<?php\n");

        $this->artisan('make:crud', [
            'name'               => 'Product',
            '--register-routes'  => true,
        ])->assertExitCode(0);

        $content = file_get_contents($routesPath);
        $this->assertStringContainsString(
            "Route::apiResource('products', \\App\\Http\\Controllers\\ProductController::class)",
            $content
        );

        // Restore original state
        $existed ? file_put_contents($routesPath, $original) : unlink($routesPath);

        $this->track(app_path('Models/Product.php'));
        $this->track(app_path('Http/Controllers/ProductController.php'));
        $this->trackMigration('products');
    }

    public function test_register_routes_does_not_duplicate_existing_route()
    {
        $routesPath = base_path('routes/api.php');
        $existed    = file_exists($routesPath);
        $original   = $existed ? file_get_contents($routesPath) : null;

        if (! is_dir(base_path('routes'))) {
            mkdir(base_path('routes'), 0755, true);
        }
        file_put_contents($routesPath, "<?php\n");

        // Register once
        $this->artisan('make:crud', ['name' => 'Product', '--register-routes' => true])->assertExitCode(0);

        // Register again — should warn, not duplicate
        $this->artisan('make:crud', ['name' => 'Product', '--register-routes' => true, '--force' => true])
            ->expectsOutputToContain('already registered')
            ->assertExitCode(0);

        $count = substr_count(
            file_get_contents($routesPath),
            "Route::apiResource('products'"
        );
        $this->assertEquals(1, $count);

        $existed ? file_put_contents($routesPath, $original) : unlink($routesPath);

        $this->track(app_path('Models/Product.php'));
        $this->track(app_path('Http/Controllers/ProductController.php'));
        $this->trackMigration('products');
    }

    // -----------------------------------------------------------------------
    // --migrate
    // -----------------------------------------------------------------------

    public function test_migrate_flag_runs_migration()
    {
        $this->artisan('make:crud', [
            'name'      => 'Product',
            '--fields'  => 'name:string:required',
            '--migrate' => true,
        ])->assertExitCode(0);

        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasTable('products'),
            'products table should exist after --migrate'
        );

        // Clean up
        \Illuminate\Support\Facades\Schema::dropIfExists('products');
        $this->track(app_path('Models/Product.php'));
        $this->track(app_path('Http/Controllers/ProductController.php'));
        $this->trackMigration('products');
    }

    // -----------------------------------------------------------------------
    // Relationship Warnings
    // -----------------------------------------------------------------------

    public function test_warns_when_related_table_does_not_exist()
    {
        $this->artisan('make:crud', [
            'name'       => 'Product',
            '--has-many' => ['NonExistentModel'],
        ])
            ->expectsOutputToContain('do not exist yet')
            ->assertExitCode(0);

        $this->track(app_path('Models/Product.php'));
        $this->track(app_path('Http/Controllers/ProductController.php'));
        $this->trackMigration('products');
    }

    public function test_no_relationship_warning_when_all_tables_exist()
    {
        // users table exists (loaded via loadLaravelMigrations in TestCase)
        $this->artisan('make:crud', [
            'name'         => 'Product',
            '--belongs-to' => ['User'],
        ])
            ->doesntExpectOutputToContain('do not exist yet')
            ->assertExitCode(0);

        $this->track(app_path('Models/Product.php'));
        $this->track(app_path('Http/Controllers/ProductController.php'));
        $this->trackMigration('products');
    }
}
