<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\UnitResident;
use App\Models\User;
use App\Services\ResidentService;
use App\Services\StructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ResidentController extends Controller
{
    public function __construct(
        private ResidentService $residentService,
        private StructureService $structureService,
    ) {}

    private function canManage(User $user, Condominium $condominium): bool
    {
        if ($condominium->status !== 'active') {
            return false;
        }

        if ($user->can('manage-access')) {
            return true;
        }

        return $user->condominiumUsers()->current()
            ->where('condominium_id', $condominium->id)
            ->with('role')
            ->get()
            ->contains(fn ($membership) => in_array(mb_strtolower(trim($membership->role->name)), [
                'admin', 'administrador', 'síndico', 'sindico',
            ], true));
    }

    private function authorizeCondominium(Request $request, Condominium $condominium): void
    {
        abort_unless($this->canManage($request->user(), $condominium), 403);
    }

    public function condominiums(Request $request): JsonResponse
    {
        $condominiums = Condominium::where('status', 'active')->orderBy('name')->get(['id', 'name', 'status']);

        return response()->json($condominiums->filter(fn ($condominium) => $this->canManage($request->user(), $condominium))->values());
    }

    public function index(Request $request, Condominium $condominium): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);

        $users = User::query()->orderBy('name');
        if (! $request->user()->can('manage-access')) {
            $users->whereHas('condominiumUsers', fn ($query) => $query->current()->where('condominium_id', $condominium->id));
        }

        return response()->json([
            'users' => $users->get(['id', 'name', 'email']),
            'blocks' => $this->structureService->getBlocks($condominium),
            'units' => $this->structureService->getUnits($condominium),
            'residents' => $this->residentService->getResidents($condominium),
        ]);
    }

    public function storeBlock(Request $request, Condominium $condominium): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255'],
        ]);

        return response()->json($this->structureService->createBlock($condominium, $data), 201);
    }

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

        $user = User::whereKey($data['user_id'])->firstOrFail();
        if (! $request->user()->can('manage-access') && ! $user->condominiumUsers()->current()->where('condominium_id', $condominium->id)->exists()) {
            throw ValidationException::withMessages(['user_id' => ['O usuário não possui vínculo ativo com este condomínio.']]);
        }

        $link = $this->residentService->createResident($user, $data);

        return response()->json($link->load(['resident.user:id,name,email', 'unit.block:id,name']), 201);
    }

    private function link(Condominium $condominium, UnitResident $link): UnitResident
    {
        abort_unless($link->unit()->where('condominium_id', $condominium->id)->exists() && $link->status === 'active' && $link->end_date === null, 404);

        return $link;
    }

    public function show(Request $request, Condominium $condominium, UnitResident $link): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);

        return response()->json($this->link($condominium, $link)->load(['resident.user:id,name,email', 'unit.block:id,name']));
    }

    public function update(Request $request, Condominium $condominium, UnitResident $link): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);
        $this->link($condominium, $link);

        $data = $request->validate([
            'document' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'relationship_type' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $resident = $link->resident;
        $updated = $this->residentService->updateResident($resident, $link, $data);

        return response()->json($updated->load(['resident.user:id,name,email', 'unit.block:id,name']));
    }

    public function destroy(Request $request, Condominium $condominium, UnitResident $link): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);
        $this->link($condominium, $link);

        $this->residentService->deleteResident($link);

        return response()->json(['message' => 'Morador excluído.']);
    }
}
