<?php

namespace App\Http\Controllers\V1\Check;

use App\Enums\CheckStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\CashTransaction;
use App\Models\CheckPayable;
use Illuminate\Http\Request;

class CheckPayableController extends Controller
{
    public function index()
    {
        $gym_id = auth()->user()->gym_id;
        $checks = CheckPayable::where('gym_id', $gym_id)
            ->orderBy('due_date', 'asc')
            ->get();

        $payable_amount = 0;
        $checks = $checks->map(function ($check) use (&$payable_amount) {
            if ($check->status == CheckStatusEnum::PENDING) {
                $payable_amount += $check->amount;
            }
            return [
                'id' => $check->id,
                'check_number' => $check->check_number,
                'amount' => (double)$check->amount,
                'status' => $check->status,
                'due_date' => $check->due_date,
                'bank' => $check->bank,
                'vendor' => $check->vendor != null ? $check->vendor->name : null,
            ];
        });

        return response()->json(['checks' => $checks, 'payable_amount' => $payable_amount]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|string']);
        $check = CheckPayable::findOrFail($id);
        $check->status = $request->input('status');
        if ($check->status == CheckStatusEnum::CLEARED) {
            $cash = new CashTransaction(
                [
                    'check_payable_id' => $check->id,
                    'amount' => -$check->amount,
                    'gym_id' => auth()->user()->gym_id,
                ]
            );
            $cash->save();
        }

        $check->save();

        return response()->json(['check' => $check]);
    }

    public function updateDueDate(Request $request, $id)
    {
        $request->validate([
            'due_date' => 'required|date_format:Y-m-d',
        ]);

        $check = CheckPayable::findOrFail($id);
        $check->due_date = $request->input('due_date');
        $check->save();

        return response()->json(['check' => $check]);
    }
}
