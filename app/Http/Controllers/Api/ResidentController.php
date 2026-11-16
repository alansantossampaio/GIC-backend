<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\UnitResident;
use App\Services\CondominiumManagementAccess;
use App\Services\ResidentService;
use App\Services\StructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResidentController extends Controller
{
    public function __construct(
        private ResidentService $residentService,
        private StructureService $structureService,
        private CondominiumManagementAccess $managementAccess,
    ) {}

    /**
     * Autoriza o acesso ao condomínio para o usuário atual
     *
     * @param Request $request
     * @param Condominium $condominium
     * @return void
     */
    private function authorizeCondominium(Request $request, Condominium $condominium): void
    {
        abort_unless($this->managementAccess->canManage($request->user(), $condominium), 403);
    }

    public function condominiums(Request $request): JsonResponse
    {
        return response()->json($this->managementAccess->availableTo($request->user()));
    }

    /**
     * Exibe os usuários, blocos, unidades e residentes disponíveis para o condomínio
     *
     * @param Request $request
     * @param Condominium $condominium
     * @return JsonResponse
     */
    public function index(Request $request, Condominium $condominium): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);

        return response()->json([
            'users' => $this->residentService->availableUsers($request->user(), $condominium),
            'blocks' => $this->structureService->getBlocks($condominium),
            'units' => $this->structureService->getUnits($condominium),
            'residents' => $this->residentService->getResidents($condominium),
        ]);
    }

    /**
     * Cria um novo bloco para o condomínio
     *
     * @param Request $request
     * @param Condominium $condominium
     * @return JsonResponse
     */
    public function storeBlock(Request $request, Condominium $condominium): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255'],
        ]);

        return response()->json($this->structureService->createBlock($condominium, $data), 201);
    }

    /**
     * Cria uma nova unidade para o condomínio
     *
     * @param Request $request
     * @param Condominium $condominium
     * @return JsonResponse
     */
    public function storeUnit(Request $request, Condominium $condominium): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);
        $data = $request->validate([
            'number' => ['required', 'string', 'max:255'],
            'unit_type' => ['required', 'string', 'max:255'],
            'block_id' => ['nullable', 'integer', Rule::exists('blocks', 'id')->where('condominium_id', $condominium->id)],
        ]);

        return response()->json($this->structureService->createUnit($condominium, $data), 201);
    }

    /**
     * Cria um novo residente para o condomínio
     *
     * @param Request $request
     * @param Condominium $condominium
     * @return JsonResponse
     */
    public function store(Request $request, Condominium $condominium): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('condominium_id', $condominium->id)->where('status', 'active')],
            'document' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'relationship_type' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date_format:Y-m-d'],
        ]);

        return response()->json($this->residentService->createForCondominium($request->user(), $condominium, $data), 201);
    }

    /**
     * Exibe os detalhes de um residente específico do condomínio
     *
     * @param Request $request
     * @param Condominium $condominium
     * @param UnitResident $link
     * @return JsonResponse
     */
    public function show(Request $request, Condominium $condominium, UnitResident $link): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);

        return response()->json($this->residentService->showLink($condominium, $link));
    }

    /**
     * Atualiza os detalhes de um residente específico do condomínio
     *
     * @param Request $request
     * @param Condominium $condominium
     * @param UnitResident $link
     * @return JsonResponse
     */
    public function update(Request $request, Condominium $condominium, UnitResident $link): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);
        $this->residentService->scopedLink($condominium, $link);

        $data = $request->validate([
            'document' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'relationship_type' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date_format:Y-m-d'],
        ]);

        return response()->json($this->residentService->updateLink($link, $data));
    }

    /**
     * Exclui um residente específico do condomínio
     *
     * @param Request $request
     * @param Condominium $condominium
     * @param UnitResident $link
     * @return JsonResponse
     */
    public function destroy(Request $request, Condominium $condominium, UnitResident $link): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);
        $this->residentService->scopedLink($condominium, $link);

        $this->residentService->deleteResident($link);

        return response()->json(['message' => 'Morador excluído.']);
    }
}
