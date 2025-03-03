<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRequest;
use App\Models\StoreModel;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function index()
    {
        $stores = StoreModel::all();
        return response()->json($stores);
    }

    public function show($id)
    {
        $store = StoreModel::find($id);
        if (!$store) {
            return response()->json(['message' => 'StoreModel not found'], 404);
        }
        return response()->json($store);
    }

    public function store(StoreRequest $request)
    {
        $store_data = $request->all();
        $store_data['user_id'] = auth()->user()->id;
        $store = StoreModel::create($store_data);
        $user = auth()->user();
        $user->store_id = $store->id;
        $user->save();
        return response()->json($store, 200);
    }

    public function update(Request $request, $id)
    {
        $store = StoreModel::find($id);
        if (!$store) {
            return response()->json(['message' => 'StoreModel not found'], 404);
        }
        $store->update($request->all());
        return response()->json($store);
    }

    public function destroy($id)
    {
        $store = StoreModel::find($id);
        if (!$store) {
            return response()->json(['message' => 'StoreModel not found'], 404);
        }
        $store->delete();
        return response()->json(['message' => 'StoreModel deleted']);
    }

    public function updateAttributes(Request $request)
    {
        $store = auth()->user()->store;
        $store->product_attributes = json_decode($request->product_attributes);
        $store->save();
        return response()->json($store);
    }
}
