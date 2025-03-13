<?php

namespace App\Http\Controllers;

use App\Http\Requests\GymRequest;
use App\Models\GymModel;
use Illuminate\Http\Request;

class GymController extends Controller
{
    public function index()
    {
        $gyms = GymModel::all();
        return response()->json($gyms);
    }

    public function show($id)
    {
        $gym = GymModel::find($id);
        if (!$gym) {
            return response()->json(['message' => 'GymModel not found'], 404);
        }
        return response()->json($gym);
    }

    public function store(GymRequest $request)
    {
        $gym_data = $request->all();
        $gym_data['user_id'] = auth()->user()->id;
        $gym = GymModel::create($gym_data);
        $user = auth()->user();
        $user->gym_id = $gym->id;
        $user->save();
        return response()->json($gym, 200);
    }

    public function update(Request $request, $id)
    {
        $gym = GymModel::find($id);
        if (!$gym) {
            return response()->json(['message' => 'GymModel not found'], 404);
        }
        $gym->update($request->all());
        return response()->json($gym);
    }

    public function destroy($id)
    {
        $gym = GymModel::find($id);
        if (!$gym) {
            return response()->json(['message' => 'GymModel not found'], 404);
        }
        $gym->delete();
        return response()->json(['message' => 'GymModel deleted']);
    }

    public function updateAttributes(Request $request)
    {
        $gym = auth()->user()->gym;
        $gym->product_attributes = json_decode($request->product_attributes);
        $gym->save();
        return response()->json($gym);
    }
}
