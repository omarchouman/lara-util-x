<?php

namespace LaraUtilX\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeCrud extends Command
{
    protected $signature = 'make:crud
        {name : The model name (e.g. Post, UserProfile)}
        {--fields= : Comma-separated fields as column:type:validation (e.g. title:string:required,body:text:nullable,price:decimal:required|min:0)}
        {--belongs-to=* : BelongsTo relationship (e.g. --belongs-to=User)}
        {--has-many=* : HasMany relationship (e.g. --has-many=Comment)}
        {--has-one=* : HasOne relationship (e.g. --has-one=Profile)}
        {--belongs-to-many=* : BelongsToMany relationship (e.g. --belongs-to-many=Tag)}
        {--soft-deletes : Enable soft delete support}
        {--searchable= : Comma-separated fields to enable search on (e.g. title,body)}
        {--per-page=15 : Default items per page for pagination}
        {--register-routes : Automatically append the apiResource route to routes/api.php}
        {--migrate : Run php artisan migrate after generating the migration}
        {--force : Overwrite existing files}';

    protected $description = 'Generate a full API CRUD scaffold: Model, Controller, and Migration';

    private string $modelName;
    private string $modelVar;
    private string $tableName;
    private array $fields = [];

    public function handle(): int
    {
        $this->modelName = Str::studly($this->argument('name'));
        $this->modelVar  = Str::camel($this->modelName);
        $this->tableName = Str::snake(Str::plural($this->modelName));
        $this->fields    = $this->parseFields($this->option('fields') ?? '');

        $this->components->info("Generating CRUD for [{$this->modelName}]...");

        $this->generateMigration();
        $this->generateModel();
        $this->generateController();

        $this->newLine();
        $this->components->info('CRUD scaffold generated successfully.');
        $this->newLine();

        if ($this->option('migrate')) {
            $this->call('migrate');
        }

        if ($this->option('register-routes')) {
            $this->registerRoute();
        } else {
            $routeUri = Str::kebab(Str::plural($this->modelName));
            $this->line('  <fg=gray>Add to</> <fg=yellow>routes/api.php</>:');
            $this->line("  <fg=cyan>Route::apiResource</>(<fg=green>'{$routeUri}'</>, \\App\\Http\\Controllers\\{$this->modelName}Controller::class);");
        }

        $this->warnAboutRelationships();

        return self::SUCCESS;
    }

    // -----------------------------------------------------------------------
    // Route Registration
    // -----------------------------------------------------------------------

    private function registerRoute(): void
    {
        $apiRoutesPath = base_path('routes/api.php');

        if (! file_exists($apiRoutesPath)) {
            $this->components->error('routes/api.php not found.');
            return;
        }

        $routeUri  = Str::kebab(Str::plural($this->modelName));
        $routeLine = "Route::apiResource('{$routeUri}', \\App\\Http\\Controllers\\{$this->modelName}Controller::class);";

        $contents = file_get_contents($apiRoutesPath);

        if (str_contains($contents, $routeLine)) {
            $this->components->warn("Route for [{$this->modelName}] is already registered in routes/api.php.");
            return;
        }

        // Append after the last line, with a trailing newline
        $contents = rtrim($contents) . "\n\n" . $routeLine . "\n";

        file_put_contents($apiRoutesPath, $contents);

        $this->components->info("Registered route in <fg=cyan>routes/api.php</>: <fg=green>{$routeLine}</>");
    }

    // -----------------------------------------------------------------------
    // Stub Loading
    // -----------------------------------------------------------------------

    private function loadStub(string $name): string
    {
        // Allow users to override stubs by publishing them
        $published = base_path("stubs/vendor/lara-util-x/{$name}");
        $default   = __DIR__ . '/../../../stubs/' . $name;

        $path = file_exists($published) ? $published : $default;

        return file_get_contents($path);
    }

    private function fillStub(string $stub, array $replacements): string
    {
        foreach ($replacements as $placeholder => $value) {
            $stub = str_replace('{{ ' . $placeholder . ' }}', $value, $stub);
        }
        return $stub;
    }

    // -----------------------------------------------------------------------
    // Field Parsing
    // -----------------------------------------------------------------------

    private function parseFields(string $raw): array
    {
        if (empty(trim($raw))) {
            return [];
        }

        $fields = [];
        foreach (explode(',', $raw) as $def) {
            $def = trim($def);
            if ($def === '') {
                continue;
            }
            // Limit to 3 splits so validation rules containing ':' are preserved
            $parts    = explode(':', $def, 3);
            $fields[] = [
                'name'  => trim($parts[0]),
                'type'  => trim($parts[1] ?? 'string'),
                'rules' => trim($parts[2] ?? ''),
            ];
        }

        return $fields;
    }

    // -----------------------------------------------------------------------
    // Migration Generation
    // -----------------------------------------------------------------------

    private function generateMigration(): void
    {
        $existing = $this->findExistingMigration();

        if ($existing) {
            $this->components->warn("Migration already exists: [database/migrations/{$existing}]. Skipping.");
            return;
        }

        $timestamp = now()->format('Y_m_d_His');
        $filename  = "{$timestamp}_create_{$this->tableName}_table.php";
        $path      = database_path("migrations/{$filename}");

        $content = $this->fillStub($this->loadStub('crud.migration.stub'), [
            'table'        => $this->tableName,
            'columns'      => $this->buildMigrationColumns(),
            'foreign_keys' => $this->buildForeignKeys(),
            'soft_deletes' => $this->option('soft-deletes') ? "\n            \$table->softDeletes();" : '',
        ]);

        file_put_contents($path, $content);
        $this->components->info("Created migration: <fg=cyan>database/migrations/{$filename}</>");
    }

    private function findExistingMigration(): ?string
    {
        $pattern = database_path("migrations/*_create_{$this->tableName}_table.php");
        $matches = glob($pattern);

        if (empty($matches)) {
            return null;
        }

        return basename($matches[0]);
    }

    private function buildMigrationColumns(): string
    {
        $lines = [];
        foreach ($this->fields as $field) {
            $lines[] = '            ' . $this->migrationColumnLine($field);
        }
        return implode("\n", $lines);
    }

    private function migrationColumnLine(array $field): string
    {
        $name     = $field['name'];
        $type     = strtolower($field['type']);
        $rules    = $field['rules'];
        $nullable = str_contains($rules, 'nullable') ? '->nullable()' : '';
        $unique   = (str_contains($rules, 'unique') && ! str_contains($rules, 'unique:')) ? '->unique()' : '';

        return match (true) {
            in_array($type, ['string', 'varchar'])                     => "\$table->string('{$name}'){$nullable}{$unique};",
            in_array($type, ['text'])                                  => "\$table->text('{$name}'){$nullable};",
            in_array($type, ['longtext'])                              => "\$table->longText('{$name}'){$nullable};",
            in_array($type, ['mediumtext'])                            => "\$table->mediumText('{$name}'){$nullable};",
            in_array($type, ['integer', 'int'])                        => "\$table->integer('{$name}'){$nullable};",
            in_array($type, ['biginteger', 'bigint'])                  => "\$table->bigInteger('{$name}'){$nullable};",
            in_array($type, ['unsignedbiginteger', 'unsignedbigint'])  => "\$table->unsignedBigInteger('{$name}'){$nullable};",
            in_array($type, ['tinyinteger', 'tinyint'])                => "\$table->tinyInteger('{$name}'){$nullable};",
            in_array($type, ['smallinteger', 'smallint'])              => "\$table->smallInteger('{$name}'){$nullable};",
            in_array($type, ['float'])                                 => "\$table->float('{$name}'){$nullable};",
            in_array($type, ['double'])                                => "\$table->double('{$name}'){$nullable};",
            in_array($type, ['decimal'])                               => "\$table->decimal('{$name}', 10, 2){$nullable};",
            in_array($type, ['boolean', 'bool'])                      => "\$table->boolean('{$name}')->default(false);",
            in_array($type, ['date'])                                  => "\$table->date('{$name}'){$nullable};",
            in_array($type, ['datetime'])                              => "\$table->dateTime('{$name}'){$nullable};",
            in_array($type, ['timestamp'])                             => "\$table->timestamp('{$name}'){$nullable};",
            in_array($type, ['json'])                                  => "\$table->json('{$name}'){$nullable};",
            in_array($type, ['uuid'])                                  => "\$table->uuid('{$name}'){$nullable};",
            in_array($type, ['enum'])                                  => "\$table->enum('{$name}', []){$nullable};",
            default                                                    => "\$table->string('{$name}'){$nullable}{$unique};",
        };
    }

    private function buildForeignKeys(): string
    {
        $lines = [];
        foreach ($this->option('belongs-to') as $related) {
            $fk      = Str::snake($related) . '_id';
            $table   = Str::snake(Str::plural($related));
            $lines[] = "\n            \$table->foreignId('{$fk}')->constrained('{$table}')->cascadeOnDelete();";
        }
        return implode('', $lines);
    }

    // -----------------------------------------------------------------------
    // Model Generation
    // -----------------------------------------------------------------------

    private function generateModel(): void
    {
        $path = app_path("Models/{$this->modelName}.php");

        if (file_exists($path) && ! $this->option('force')) {
            $this->components->warn("Model [{$this->modelName}] already exists. Use --force to overwrite.");
            return;
        }

        $softDeletes = $this->option('soft-deletes');

        $content = $this->fillStub($this->loadStub('crud.model.stub'), [
            'namespace'          => 'App\\Models',
            'class'              => $this->modelName,
            'soft_delete_import' => $softDeletes ? "use Illuminate\\Database\\Eloquent\\SoftDeletes;\n" : '',
            'soft_delete_trait'  => $softDeletes ? "\n    use SoftDeletes;" : '',
            'fillable'           => $this->buildFillable(),
            'casts_block'        => $this->buildCastsBlock(),
            'relationships'      => $this->buildModelRelationships(),
        ]);

        $this->ensureDirectoryExists(app_path('Models'));
        file_put_contents($path, $content);
        $this->components->info("Created model: <fg=cyan>app/Models/{$this->modelName}.php</>");
    }

    private function buildFillable(): string
    {
        $items = array_map(fn($f) => "        '{$f['name']}'", $this->fields);

        foreach ($this->option('belongs-to') as $related) {
            $items[] = "        '" . Str::snake($related) . "_id'";
        }

        if (empty($items)) {
            return '';
        }

        return "\n" . implode(",\n", $items) . ',';
    }

    private function buildCastsBlock(): string
    {
        $casts = [];
        foreach ($this->fields as $field) {
            $cast = $this->resolveCast($field['type']);
            if ($cast !== null) {
                $casts[] = "        '{$field['name']}' => '{$cast}'";
            }
        }

        if (empty($casts)) {
            return '';
        }

        return "    protected \$casts = [\n" . implode(",\n", $casts) . ",\n    ];\n";
    }

    private function resolveCast(string $type): ?string
    {
        $type = strtolower($type);
        return match (true) {
            in_array($type, ['integer', 'int', 'biginteger', 'bigint', 'tinyinteger', 'smallinteger']) => 'integer',
            in_array($type, ['float', 'double'])                                                        => 'float',
            in_array($type, ['decimal'])                                                                => 'decimal:2',
            in_array($type, ['boolean', 'bool'])                                                        => 'boolean',
            in_array($type, ['json'])                                                                   => 'array',
            in_array($type, ['date'])                                                                   => 'date',
            in_array($type, ['datetime', 'timestamp'])                                                  => 'datetime',
            default                                                                                     => null,
        };
    }

    private function buildModelRelationships(): string
    {
        $methods = [];

        foreach ($this->option('belongs-to') as $related) {
            $related   = Str::studly($related);
            $methods[] = $this->relationshipMethod(Str::camel($related), 'BelongsTo', "belongsTo(\\App\\Models\\{$related}::class)");
        }

        foreach ($this->option('has-many') as $related) {
            $related   = Str::studly($related);
            $methods[] = $this->relationshipMethod(Str::camel(Str::plural($related)), 'HasMany', "hasMany(\\App\\Models\\{$related}::class)");
        }

        foreach ($this->option('has-one') as $related) {
            $related   = Str::studly($related);
            $methods[] = $this->relationshipMethod(Str::camel($related), 'HasOne', "hasOne(\\App\\Models\\{$related}::class)");
        }

        foreach ($this->option('belongs-to-many') as $related) {
            $related   = Str::studly($related);
            $methods[] = $this->relationshipMethod(Str::camel(Str::plural($related)), 'BelongsToMany', "belongsToMany(\\App\\Models\\{$related}::class)");
        }

        return $methods ? implode("\n\n", $methods) . "\n" : '';
    }

    private function relationshipMethod(string $method, string $returnType, string $call): string
    {
        return <<<PHP
    public function {$method}(): \Illuminate\Database\Eloquent\Relations\\{$returnType}
    {
        return \$this->{$call};
    }
PHP;
    }

    // -----------------------------------------------------------------------
    // Controller Generation
    // -----------------------------------------------------------------------

    private function generateController(): void
    {
        $path = app_path("Http/Controllers/{$this->modelName}Controller.php");

        if (file_exists($path) && ! $this->option('force')) {
            $this->components->warn("Controller [{$this->modelName}Controller] already exists. Use --force to overwrite.");
            return;
        }

        $relationships = $this->getRelationshipsList();
        $eagerLoad     = $relationships ? $this->buildEagerLoad($relationships) : '';

        $content = $this->fillStub($this->loadStub('crud.controller.stub'), [
            'namespace'        => 'App\\Http\\Controllers',
            'model_namespace'  => 'App\\Models',
            'class'            => $this->modelName,
            'model_var'        => $this->modelVar,
            'index_body'       => $this->buildIndexBody($relationships),
            'show_eager_load'  => $eagerLoad,
            'store_validation' => $this->buildStoreValidation(),
            'store_eager_load' => $eagerLoad,
            'update_validation' => $this->buildUpdateValidation(),
            'update_eager_load' => $eagerLoad,
        ]);

        $this->ensureDirectoryExists(app_path('Http/Controllers'));
        file_put_contents($path, $content);
        $this->components->info("Created controller: <fg=cyan>app/Http/Controllers/{$this->modelName}Controller.php</>");
    }

    private function buildIndexBody(array $relationships): string
    {
        $searchable = $this->getSearchableFields();
        $perPage    = (int) ($this->option('per-page') ?? 15);
        $lines      = [];

        $lines[] = "        \$query = {$this->modelName}::query();";
        $lines[] = '';

        if ($searchable) {
            $searchStr = "['" . implode("', '", $searchable) . "']";
            $lines[]   = "        if (\$request->filled('search')) {";
            $lines[]   = "            \$term = \$request->input('search');";
            $lines[]   = "            \$query->where(function (\$q) use (\$term) {";
            $lines[]   = "                foreach ({$searchStr} as \$field) {";
            $lines[]   = "                    \$q->orWhere(\$field, 'LIKE', \"%{\$term}%\");";
            $lines[]   = "                }";
            $lines[]   = "            });";
            $lines[]   = "        }";
            $lines[]   = '';
        }

        if ($relationships) {
            $relStr  = "['" . implode("', '", $relationships) . "']";
            $lines[] = "        \$query->with({$relStr});";
            $lines[] = '';
        }

        $lines[] = "        if (\$request->filled('sort_by')) {";
        $lines[] = "            \$query->orderBy(\$request->input('sort_by'), \$request->input('sort_direction', 'asc'));";
        $lines[] = "        }";
        $lines[] = '';
        $lines[] = "        \$records = \$query->paginate(\$request->input('per_page', {$perPage}));";
        $lines[] = '';
        $lines[] = "        return response()->json([";
        $lines[] = "            'data' => \$records->items(),";
        $lines[] = "            'meta' => [";
        $lines[] = "                'current_page' => \$records->currentPage(),";
        $lines[] = "                'last_page'    => \$records->lastPage(),";
        $lines[] = "                'per_page'     => \$records->perPage(),";
        $lines[] = "                'total'        => \$records->total(),";
        $lines[] = "            ],";
        $lines[] = "        ]);";

        return implode("\n", $lines);
    }

    private function buildEagerLoad(array $relationships): string
    {
        $relStr = "['" . implode("', '", $relationships) . "']";
        return "        \${$this->modelVar}->load({$relStr});\n";
    }

    private function buildStoreValidation(): string
    {
        $rules    = $this->collectValidationRules(false);
        $messages = $this->collectValidationMessages($rules);

        if (empty($rules)) {
            return "        \$validated = \$request->all();";
        }

        $lines = ["        \$validated = \$request->validate(["];
        foreach ($rules as $field => $rule) {
            $lines[] = "            '{$field}' => '{$rule}',";
        }

        if ($messages) {
            $lines[] = "        ], [";
            foreach ($messages as $key => $message) {
                $lines[] = "            '{$key}' => '{$message}',";
            }
            $lines[] = "        ]);";
        } else {
            $lines[] = "        ]);";
        }

        return implode("\n", $lines);
    }

    private function buildUpdateValidation(): string
    {
        $rules    = $this->collectValidationRules(true);
        $messages = $this->collectValidationMessages($rules);

        if (empty($rules)) {
            return "        \$validated = \$request->all();";
        }

        $lines = ["        \$validated = \$request->validate(["];
        foreach ($rules as $field => $rule) {
            $lines[] = $this->buildUpdateRuleLine($field, $rule);
        }

        if ($messages) {
            $lines[] = "        ], [";
            foreach ($messages as $key => $message) {
                $lines[] = "            '{$key}' => '{$message}',";
            }
            $lines[] = "        ]);";
        } else {
            $lines[] = "        ]);";
        }

        return implode("\n", $lines);
    }

    private function collectValidationMessages(array $rules): array
    {
        $messages = [];

        foreach ($rules as $field => $rule) {
            $parts = explode('|', $rule);

            foreach ($parts as $part) {
                if (str_starts_with($part, 'exists:')) {
                    $spec    = explode(',', substr($part, 7));
                    $table   = $spec[0];
                    $label   = str_replace('_id', '', $field);
                    $messages["{$field}.exists"] = "The selected {$label} does not exist in the {$table} table.";
                    $messages["{$field}.required"] = "The {$label} field is required.";
                }

                if (str_starts_with($part, 'unique:')) {
                    $messages["{$field}.unique"] = "This {$field} has already been taken.";
                }
            }
        }

        return $messages;
    }

    private function buildUpdateRuleLine(string $field, string $rule): string
    {
        if (! str_contains($rule, 'unique:')) {
            return "            '{$field}' => '{$rule}',";
        }

        // Isolate the unique segment and append model ID so existing records pass validation
        $parts       = explode('|', $rule);
        $nonUnique   = [];
        $uniqueTable = '';
        $uniqueCol   = $field;

        foreach ($parts as $part) {
            if (str_starts_with($part, 'unique:')) {
                $spec        = substr($part, 7);
                $specParts   = explode(',', $spec);
                $uniqueTable = $specParts[0];
                $uniqueCol   = $specParts[1] ?? $field;
            } else {
                $nonUnique[] = $part;
            }
        }

        $base   = implode('|', $nonUnique);
        $prefix = $base ? "{$base}|unique:{$uniqueTable},{$uniqueCol}," : "unique:{$uniqueTable},{$uniqueCol},";

        return "            '{$field}' => '{$prefix}' . \${$this->modelVar}->id,";
    }

    private function collectValidationRules(bool $isUpdate = false): array
    {
        $rules = [];

        foreach ($this->fields as $field) {
            if (! empty($field['rules'])) {
                $rules[$field['name']] = $field['rules'];
            }
        }

        // Add foreign key fields from --belongs-to relationships
        foreach ($this->option('belongs-to') as $related) {
            $fk           = Str::snake($related) . '_id';
            $table        = Str::snake(Str::plural($related));
            $rules[$fk]   = $isUpdate
                ? "sometimes|integer|exists:{$table},id"
                : "required|integer|exists:{$table},id";
        }

        return $rules;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function getSearchableFields(): array
    {
        $raw = $this->option('searchable') ?? '';
        if (empty(trim($raw))) {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function getRelationshipsList(): array
    {
        $rels = [];
        foreach ($this->option('belongs-to') as $r) {
            $rels[] = Str::camel(Str::studly($r));
        }
        foreach ($this->option('has-many') as $r) {
            $rels[] = Str::camel(Str::plural(Str::studly($r)));
        }
        foreach ($this->option('has-one') as $r) {
            $rels[] = Str::camel(Str::studly($r));
        }
        foreach ($this->option('belongs-to-many') as $r) {
            $rels[] = Str::camel(Str::plural(Str::studly($r)));
        }
        return $rels;
    }

    private function warnAboutRelationships(): void
    {
        $missing = [];

        $toCheck = [
            'has-many'         => fn($r) => Str::snake(Str::plural($r)),
            'has-one'          => fn($r) => Str::snake($r),
            'belongs-to-many'  => fn($r) => Str::snake(Str::plural($r)),
        ];

        foreach ($toCheck as $option => $tableNameFn) {
            foreach ($this->option($option) as $related) {
                $table = $tableNameFn($related);
                if (! $this->tableExists($table)) {
                    $missing[] = [
                        'model' => Str::studly($related),
                        'table' => $table,
                    ];
                }
            }
        }

        foreach ($this->option('belongs-to') as $related) {
            $table = Str::snake(Str::plural($related));
            if (! $this->tableExists($table)) {
                $missing[] = [
                    'model' => Str::studly($related),
                    'table' => $table,
                ];
            }
        }

        if (empty($missing)) {
            return;
        }

        $this->newLine();
        $this->components->warn('The following related tables do not exist yet. Eager loading and FK validation will fail until their migrations are run:');

        foreach ($missing as $item) {
            $this->line("  <fg=yellow>  {$item['model']}</> → table <fg=red>{$item['table']}</> not found");
            $this->line("    Run: <fg=cyan>php artisan make:model {$item['model']} -m</>");
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
