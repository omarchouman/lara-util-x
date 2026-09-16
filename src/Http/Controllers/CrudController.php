<?php

namespace LaraUtilX\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

abstract class CrudController extends Controller
{
    protected Model $model;
    protected array $validationRules = [];
    protected array $searchableFields = [];
    protected array $sortableFields = [];
    protected array $relationships = [];
    protected int $perPage = 15;
    protected int $maxPerPage = 100;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function getAllRecords(Request $request): JsonResponse
    {
        $query = $this->model->query();

        if (!empty($this->searchableFields) && $request->has('search')) {
            $searchTerm = $request->get('search');
            $query->where(function ($q) use ($searchTerm) {
                foreach ($this->searchableFields as $field) {
                    $q->orWhere($field, 'LIKE', "%{$searchTerm}%");
                }
            });
        }

        // Load relationships if defined
        if (!empty($this->relationships)) {
            $query->with($this->relationships);
        }

        $this->applySorting($query, $request);

        $records = $query->paginate($this->resolvePerPage($request));

        return response()->json([
            'data' => $records->items(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ]
        ]);
    }


    public function getRecordById($id): JsonResponse
    {
        $query = $this->model->query();
        
        if (!empty($this->relationships)) {
            $query->with($this->relationships);
        }

        $record = $query->findOrFail($id);

        return response()->json(['data' => $record]);
    }


    public function storeRecord(Request $request): JsonResponse
    {
        $validated = $this->validateRequest($request);
        
        $record = $this->model->create($validated);

        if (!empty($this->relationships)) {
            $record->load($this->relationships);
        }

        return response()->json([
            'message' => 'Record created successfully',
            'data' => $record
        ], 201);
    }


    public function updateRecord(Request $request, $id): JsonResponse
    {
        $record = $this->model->findOrFail($id);
        
        $validated = $this->validateRequest($request, $id);
        
        $record->update($validated);

        if (!empty($this->relationships)) {
            $record->load($this->relationships);
        }

        return response()->json([
            'message' => 'Record updated successfully',
            'data' => $record
        ]);
    }


    public function deleteRecord($id): JsonResponse
    {
        $record = $this->model->findOrFail($id);
        $record->delete();

        return response()->json([
            'message' => 'Record deleted successfully'
        ]);
    }


    /**
     * Clamp ?per_page so a caller cannot ask for the entire table in one go.
     */
    protected function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', $this->perPage);

        if ($perPage < 1) {
            return $this->perPage;
        }

        return min($perPage, $this->maxPerPage);
    }

    /**
     * Sorting is restricted to $sortableFields so a request cannot order by
     * columns it should never see, such as password hashes or tokens.
     */
    protected function applySorting($query, Request $request): void
    {
        $column = $request->input('sort_by');

        if (! $column || ! in_array($column, $this->sortableFields, true)) {
            return;
        }

        $direction = strtolower($request->input('sort_direction', 'asc'));

        $query->orderBy($column, $direction === 'desc' ? 'desc' : 'asc');
    }


    protected function validateRequest(Request $request, $id = null): array
    {
        if (empty($this->validationRules)) {
            return $request->all();
        }

        $rules = $this->validationRules;

        if ($id) {
            foreach ($rules as $field => $rule) {
                $rules[$field] = $this->ignoreCurrentRecord($rule, $field, $id);
            }
        }

        return $request->validate($rules);
    }

    /**
     * Rewrite a unique rule so it ignores the record being updated.
     *
     * Appending the id to the whole rule string only works when unique: is the
     * last rule and already names its column, so the segment is rebuilt instead.
     */
    protected function ignoreCurrentRecord(mixed $rule, string $field, mixed $id): mixed
    {
        if (! is_string($rule) || ! str_contains($rule, 'unique:')) {
            return $rule;
        }

        $segments = explode('|', $rule);

        foreach ($segments as $index => $segment) {
            if (! str_starts_with($segment, 'unique:')) {
                continue;
            }

            $parts = explode(',', substr($segment, strlen('unique:')));
            $table = $parts[0] ?? '';
            $column = $parts[1] ?? $field;

            $segments[$index] = 'unique:' . $table . ',' . $column . ',' . $id;
        }

        return implode('|', $segments);
    }
}
