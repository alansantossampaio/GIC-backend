<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Services\CondominiumManagementAccess;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    public function __construct(
        private CondominiumManagementAccess $access,
        private FinanceService $finance,
    ) {}

    public function categories(Request $request, Condominium $condominium): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);

        return response()->json($this->finance->categories($condominium));
    }

    public function storeCategory(Request $request, Condominium $condominium): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['income', 'expense'])],
        ]);

        return response()->json($this->finance->createCategory($condominium, $data), 201);
    }

    public function transactions(Request $request, Condominium $condominium): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);

        return response()->json($this->finance->transactions($condominium)->map(fn ($transaction) => $this->finance->withBalance($transaction)));
    }

    public function showTransaction(Request $request, Condominium $condominium, int $transaction): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);

        return response()->json($this->finance->withBalance($this->finance->findTransaction($condominium, $transaction)));
    }

    public function storeTransaction(Request $request, Condominium $condominium): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);
        $direction = $request->input('direction');
        $data = $request->validate([
            'direction' => ['required', Rule::in(['income', 'expense'])],
            'category_id' => ['required', 'integer', Rule::exists('financial_categories', 'id')
                ->where('condominium_id', $condominium->id)->where('type', is_string($direction) ? $direction : '')],
            'unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')->where('condominium_id', $condominium->id)],
            'amount' => $this->amountRules(),
            'due_date' => ['required', 'date_format:Y-m-d'],
        ]);

        return response()->json($this->finance->withBalance($this->finance->createTransaction($condominium, $data)), 201);
    }

    public function payments(Request $request, Condominium $condominium, int $transaction): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);

        return response()->json($this->finance->payments($condominium, $transaction));
    }

    public function storePayment(Request $request, Condominium $condominium, int $transaction): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);
        $this->finance->findTransaction($condominium, $transaction);
        $data = $request->validate([
            'amount' => $this->amountRules(),
            'paid_at' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:255'],
        ]);

        return response()->json($this->finance->createPayment($condominium, $transaction, $data), 201);
    }

    private function authorizeCondominium(Request $request, Condominium $condominium): void
    {
        abort_unless($this->access->canManage($request->user(), $condominium), 403);
    }

    private function amountRules(): array
    {
        return ['required', 'string', 'regex:/^(?:0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?$/'];
    }
}
