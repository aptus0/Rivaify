<?php

namespace Modules\Commerce\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Commerce\Services\Payments\PayTRGateway;

class CheckoutController extends Controller
{
    public function initialize(Request $request, PayTRGateway $gateway)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer',
            'amount' => 'required|numeric',
        ]);

        $response = $gateway->initializePayment($validated);

        return response()->json($response);
    }

    public function callback(Request $request, PayTRGateway $gateway)
    {
        $isValid = $gateway->verifyCallback($request->all());

        if ($isValid) {
            // Update transaction status
            return response('OK');
        }

        return response('FAIL', 400);
    }

}
