<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Services\CondominiumManagementAccess;
use App\Services\OccurrenceService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OccurrenceController extends Controller
{
    public function __construct(
        private CondominiumManagementAccess $access,
        private OccurrenceService $occurrences,
    ) {}

    public function index(Request $request, Condominium $condominium): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);

        return response()->json($this->occurrences->list($condominium));
    }

    public function show(Request $request, Condominium $condominium, int $occurrence): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);

        return response()->json($this->occurrences->find($condominium, $occurrence));
    }

    public function store(Request $request, Condominium $condominium): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);

        return response()->json($this->occurrences->create($condominium, $request->user(), $this->validated($request, $condominium)), 201);
    }

    public function update(Request $request, Condominium $condominium, int $occurrence): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);
        $this->occurrences->find($condominium, $occurrence);

        return response()->json($this->occurrences->update($condominium, $occurrence, $this->validated($request, $condominium, true)));
    }

    private function authorizeCondominium(Request $request, Condominium $condominium): void
    {
        abort_unless($this->access->canManage($request->user(), $condominium), 403);
    }

    private function validated(Request $request, Condominium $condominium, bool $updating = false): array
    {
        $unitId = $request->input('unit_id');
        $rules = [
            'type' => ['required', Rule::in(['complaint', 'denunciation', 'technical_problem'])],
            'description' => ['required', 'string', 'max:255'],
            'occurred_at' => ['required', 'date'],
            'unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')->where('condominium_id', $condominium->id)],
            'resident_id' => ['nullable', 'integer', Rule::exists('unit_residents', 'resident_id')->where(function (Builder $query) use ($condominium, $unitId) {
                $query->whereIn('unit_id', function (Builder $units) use ($condominium) {
                    $units->select('id')->from('units')->where('condominium_id', $condominium->id);
                });
                if (is_int($unitId) || (is_string($unitId) && ctype_digit($unitId))) {
                    $query->where('unit_id', $unitId);
                }
            })],
        ];
        if ($updating) {
            $rules['status'] = ['required', Rule::in(['open', 'in_progress', 'closed'])];
        }

        $data = $request->validate($rules);
        $data['unit_id'] = $data['unit_id'] ?? null;
        $data['resident_id'] = $data['resident_id'] ?? null;

        return $data;
    }
}
