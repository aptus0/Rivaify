<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Commerce\Services\Finance\FinanceLedger;

class AdminFinanceController extends Controller
{
    public function show(CurrentStore $currentStore, FinanceLedger $ledger): JsonResponse
    {
        return response()->json(['data' => $ledger->merchantSummary($currentStore->store()->default_currency)]);
    }
}
