<?php

namespace omarchouman\LaraUtilX\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CrudController extends Controller
{
    protected $model;

    public function __construct($model)
    {
        $this->model = $model;
    }

    public function getAllRecords()
    {
        return $this->model::all();
    }

    public function getRecordById($id)
    {
        return $this->model::findOrFail($id);
    }

    public function storeRecord(Request $request)
    {
        return $this->model::create($request->validate());
    }

    public function updateRecord(Request $request, $id)
    {
        $model = $this->model::findOrFail($id);
        $model->update($request->validate());

        return $model;
    }

    public function deleteRecord($id)
    {
        $model = $this->model::findOrFail($id);
        $model->delete();

        return 204;
    }
}
