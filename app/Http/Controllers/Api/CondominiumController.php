<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Rules\Cnpj;
use App\Services\CondominiumService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CondominiumController extends Controller
{
    public function __construct(private CondominiumService $condominiums) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->condominiums->availableTo($request->user()));
    }

    /**
     * Exibe os detalhes de um condomínio específico
     *
     * @param Condominium $condominium
     * @return JsonResponse
     */
    public function show(Condominium $condominium): JsonResponse
    {
        Gate::authorize('view', $condominium);

        return response()->json($condominium->only(['id', 'name', 'document', 'status', 'unity_qtd']));
    }

    /**
     * Cria um novo condomínio
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Condominium::class);
        $condominium = $this->condominiums->create($this->validated($request) + ['unity_qtd' => null]);

        return response()->json($condominium, 201);
    }

    /**
     * Atualiza os detalhes de um condomínio específico
     *
     * @param Request $request
     * @param Condominium $condominium
     * @return JsonResponse
     */
    public function update(Request $request, Condominium $condominium): JsonResponse
    {
        Gate::authorize('update', $condominium);

        return response()->json($this->condominiums->update($condominium, $this->validated($request)));
    }

    /**
     * Altera o status de um condomínio específico
     *
     * @param Request $request
     * @param Condominium $condominium
     * @return JsonResponse
     */
    public function changeStatus(Request $request, Condominium $condominium): JsonResponse
    {
        Gate::authorize('changeStatus', $condominium);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'inactive'])]]);

        return response()->json($this->condominiums->changeStatus($condominium, $data['status']));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document' => ['required', 'string', new Cnpj],
            'unity_qtd' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:2147483647'],
        ]);
    }
}
