<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CustomerRequest;
use App\Models\CustomerModel;
use App\Models\Order\OrderModel;
use Maatwebsite\Excel\Excel;

class CustomerController extends Controller
{
    protected $excel;

    public function __construct(Excel $excel)
    {
        $this->excel = $excel;
    }

    public function addCustomer(CustomerRequest $request)
    {
        $customer = new CustomerModel([
            'name' => $request->name,
            'phoneNumber' => $request->phoneNumber,
            'store_id' => auth()->user()->store_id,
            'debt' => 0,
        ]);

        $customer->save();

        return response()->json(['message' => 'Customer added successfully', 'customer' => $customer], 200);
    }

    public function updateCustomer(CustomerRequest $request, $id)
    {
        $customer = CustomerModel::findOrFail($id);
        $customer->update($request->all());

        return response()->json(['message' => 'Customer updated successfully', 'customer' => $customer], 200);
    }

    public function getCustomerById($id)
    {
        $customer = CustomerModel::findOrFail($id);
        return response()->json(['customer' => $customer], 200);
    }

    public function getCustomers()
    {
        $storeId = auth()->user()->store_id;
        $customers = CustomerModel::where('store_id', $storeId)->orderBy('updated_at', 'desc')->get();

        return response()->json(['customers' => $customers], 200);
    }

    public function deleteCustomer($id)
    {
        $customer = CustomerModel::findOrFail($id);
        $customer->delete();

        return response()->json(['message' => 'Customer deleted successfully'], 200);
    }

    public function getCustomerOrders($id)
    {
        $customer = CustomerModel::findOrFail($id);
        $orders = OrderModel::where('customerId', $id)
            ->orderBy('updated_at', 'desc')
            ->with('orderProducts', 'orderTransactions')->get();

        return response()->json(['customer' => $customer, 'orders' => $orders], 200);
    }
}
