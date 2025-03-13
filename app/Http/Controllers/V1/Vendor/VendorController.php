<?php

namespace App\Http\Controllers\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorModel;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index()
    {
        $gymId = auth()->user()->gym_id;
        $vendors = VendorModel::where('gym_id', $gymId)
            ->get()
            ->sortBy(
                'updated_at',
                SORT_REGULAR,
                true
            )->values();
        return response()->json(['vendors' => $vendors]);
    }

    public function show($id)
    {
        $gymId = auth()->user()->gym_id;

        $vendor = VendorModel::where('gym_id', $gymId)->
        findOrFail($id)->load('purchases');

        return response()->json(['vendor' => $vendor]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
        ]);

        $data = $request->all();
        $data['gym_id'] = auth()->user()->gym_id;

        $vendor = VendorModel::create($data);
        return response()->json($vendor, 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20',
        ]);
        $gymId = auth()->user()->gym_id;

        $vendor = VendorModel::where('gym_id', $gymId)->findOrFail($id);
        $vendor->update([
            'name' => $request->name,
            'phone' => $request->phone,
        ]);
        return response()->json($vendor);
    }

    public function destroy($id)
    {
        $gymId = auth()->user()->gym_id;
        $vendor = VendorModel::where('gym_id', $gymId)->findOrFail($id);
        $vendor->delete();
        return response()->json(null, 204);
    }
}
