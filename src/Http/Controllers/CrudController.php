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
    protected array $relationships = [];
    protected int $perPage = 15;

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

        if ($request->has('sort_by')) {
            $direction = $request->get('sort_direction', 'asc');
            $query->orderBy($request->get('sort_by'), $direction);
        }

        $records = $query->paginate($request->get('per_page', $this->perPage));

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
        ], 204);
    }


    protected function validateRequest(Request $request, $id = null): array
    {
        if (empty($this->validationRules)) {
            return $request->all();
        }

        $rules = $this->validationRules;
        
        if ($id) {
            foreach ($rules as $field => $rule) {
                if (is_string($rule) && str_contains($rule, 'unique:')) {
                    $rules[$field] = $rule . ',' . $id;
                }
            }
        }

        return $request->validate($rules);
    }
}
