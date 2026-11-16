<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Services\OccurrenceService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OccurrenceController extends Controller
{
    public function __construct(private OccurrenceService $occurrences) {}

    public function condominiums(Request $request): JsonResponse
    {
        return response()->json($this->occurrences->availableCondominiums($request->user()));
    }

    public function context(Request $request, Condominium $condominium): JsonResponse
    {
        return response()->json($this->occurrences->context($request->user(), $condominium));
    }

    public function index(Request $request, Condominium $condominium): JsonResponse
    {
        $context = $this->occurrences->context($request->user(), $condominium);

        return response()->json($this->occurrences->list($condominium, $request->user(), $context['can_manage']));
    }

    public function show(Request $request, Condominium $condominium, int $occurrence): JsonResponse
    {
        $context = $this->occurrences->context($request->user(), $condominium);

        return response()->json($this->occurrences->find($condominium, $occurrence, $request->user(), $context['can_manage']));
    }

    public function store(Request $request, Condominium $condominium): JsonResponse
    {
        $context = $this->occurrences->context($request->user(), $condominium);
        $data = $this->validated($request, $condominium, $context);
        $data['resident_id'] = $data['resident_id'] ?? null;

        return response()->json($this->occurrences->create($condominium, $request->user(), $data), 201);
    }

    public function update(Request $request, Condominium $condominium, int $occurrence): JsonResponse
    {
        $context = $this->occurrences->context($request->user(), $condominium);
        $this->occurrences->find($condominium, $occurrence, $request->user(), $context['can_manage']);

        return response()->json($this->occurrences->update($condominium, $occurrence, $request->user(), $context['can_manage'], $this->validated($request, $condominium, $context, true)));
    }

    public function history(Request $request, Condominium $condominium, int $occurrence): JsonResponse
    {
        $context = $this->occurrences->context($request->user(), $condominium);

        return response()->json($this->occurrences->history($condominium, $occurrence, $request->user(), $context['can_manage']));
    }

    private function validated(Request $request, Condominium $condominium, array $context, bool $updating = false): array
    {
        $unitId = $request->input('unit_id');
        $rules = [
            'type' => ['required', Rule::in(['complaint', 'denunciation', 'technical_problem'])],
            'description' => ['required', 'string', 'max:255'],
            'occurred_at' => ['required', 'date'],
            'unit_id' => $context['can_manage']
                ? ['nullable', 'integer', Rule::exists('units', 'id')->where('condominium_id', $condominium->id)]
                : ['nullable', 'integer', Rule::in($context['units']->modelKeys())],
        ];
        if ($context['can_manage']) {
            $rules['resident_id'] = ['nullable', 'integer', Rule::exists('unit_residents', 'resident_id')->where(function (Builder $query) use ($condominium, $unitId) {
                $query->whereIn('unit_id', function (Builder $units) use ($condominium) {
                    $units->select('id')->from('units')->where('condominium_id', $condominium->id);
                });
                if (is_int($unitId) || (is_string($unitId) && ctype_digit($unitId))) {
                    $query->where('unit_id', $unitId);
                }
            })];
            if ($updating) {
                $rules['status'] = ['required', Rule::in(['open', 'in_progress', 'closed'])];
            }
        } else {
            $rules['resident_id'] = ['prohibited'];
            $rules['status'] = ['prohibited'];
        }

        $data = $request->validate($rules);
        $data['unit_id'] = $data['unit_id'] ?? null;
        if ($context['can_manage']) {
            $data['resident_id'] = $data['resident_id'] ?? null;
        } else {
            unset($data['resident_id'], $data['status']);
        }

        return $data;
    }
}
