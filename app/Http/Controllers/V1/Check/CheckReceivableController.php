<?php

namespace App\Http\Controllers\V1\Check;

use App\Enums\CheckStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\CashTransaction;
use App\Models\CheckReceivable;
use Illuminate\Http\Request;

class CheckReceivableController extends Controller
{
    public function index()
    {
        $gym_id = auth()->user()->gym_id;
        $checks = CheckReceivable::where('gym_id', $gym_id)
            ->where('status', "<>", CheckStatusEnum::CLEARED)
            ->orderBy('due_date', 'asc')
            ->get();


        $checks = $checks->map(function ($check) {
            return [
                'id' => $check->id,
                'check_number' => $check->check_number,
                'issuer_name' => $check->issuer_name,
                'amount' => (double)$check->amount,
                'status' => $check->status,
                'due_date' => $check->due_date,
                'bank' => $check->bank,
                'customer' => $check->customer != null ? $check->customer->name : null,
            ];
        });

        $collectable_checks = $checks->filter(function ($check) {
            return $check['due_date'] <= now()->format('Y-m-d') && $check['status'] == CheckStatusEnum::PENDING;
        })->values();

        $checks = $checks->filter(function ($check) {
            return $check;
//            return $check['due_date'] > now()->format('Y-m-d');
        })->values();

        return response()->json(['checks' => $checks,
//                'collectable_checks' => $collectable_checks,
                ]
        );
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|string',]);
        $gym_id = auth()->user()->gym_id;
        $check = CheckReceivable::findOrFail($id);
        $check->status = $request->input('status');
        $check->save();
        if ($check->status == CheckStatusEnum::CLEARED) {
            $cash = new CashTransaction(
                [
                    'check_receivable_id' => $check->id,
                    'amount' => $check->amount,
                    'gym_id' => $gym_id,
                ]
            );
            $cash->save();
        }

        return response()->json(['check' => $check]);
    }

    public function getAvailableChecks()
    {
        $gym_id = auth()->user()->gym_id;
        $availableChecks = CheckReceivable::where('gym_id', $gym_id)
            ->where('status', CheckStatusEnum::PENDING)
            ->orderBy('due_date', 'asc')
            ->get()
            ->map(function ($check) {
                return [
                    'id' => $check->id,
                    'check_number' => $check->check_number,
                    'issuer_name' => $check->issuer_name,
                    'amount' => (double)$check->amount,
                    'due_date' => $check->due_date,
                    'bank' => $check->bank,
                    'customer' => $check->customer != null ? $check->customer->name : null,
                    'status' => $check->status,
                ];
            });

        return response()->json(['available_checks' => $availableChecks]);
    }

    public function updateDueDate(Request $request, $id)
    {
        $request->validate([
            'due_date' => 'required|date_format:Y-m-d',
        ]);

        $check = CheckReceivable::findOrFail($id);
        $check->due_date = $request->input('due_date');
        $check->save();

        return response()->json(['check' => $check]);
    }

}
