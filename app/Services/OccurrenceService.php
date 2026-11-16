<?php

namespace App\Services;

use App\Models\Condominium;
use App\Models\Occurrence;
use App\Models\User;
use App\Repositories\Contracts\OccurrenceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OccurrenceService
{
    private const TRACKED_FIELDS = ['type', 'description', 'status', 'occurred_at', 'unit_id', 'resident_id'];

    public function __construct(
        private OccurrenceInterface $occurrences,
        private CondominiumManagementAccess $management,
    ) {}

    public function availableCondominiums(User $user): Collection
    {
        return $this->management->availableTo($user)->concat($this->occurrences->residentCondominiums($user))
            ->unique('id')->sortBy('name')->values();
    }

    public function context(User $user, Condominium $condominium): array
    {
        $manage = $this->management->canManage($user, $condominium);
        if ($manage) {
            return ['can_manage' => true, 'units' => []];
        }

        abort_unless($condominium->status === 'active' && $this->occurrences->hasCurrentMembership($user, $condominium), 403);
        $units = $this->occurrences->residentUnits($user, $condominium);
        abort_if($units->isEmpty(), 403);

        return ['can_manage' => false, 'units' => $units];
    }

    public function list(Condominium $condominium, User $user, bool $manage): Collection
    {
        return $this->occurrences->list($condominium, $manage ? null : $user->id);
    }

    public function find(Condominium $condominium, int $id, User $user, bool $manage): Occurrence
    {
        return $this->occurrences->find($condominium, $id, $manage ? null : $user->id);
    }

    public function create(Condominium $condominium, User $user, array $data): Occurrence
    {
        $data['reported_by'] = $user->id;
        $data['status'] = 'open';

        return DB::transaction(function () use ($condominium, $user, $data) {
            $occurrence = $this->occurrences->create($condominium, $data);
            $this->occurrences->addHistory($occurrence, [
                'user_id' => $user->id, 'action' => 'created', 'before' => null, 'after' => $this->snapshot($occurrence),
            ]);

            return $occurrence;
        });
    }

    public function update(Condominium $condominium, int $id, User $user, bool $manage, array $data): Occurrence
    {
        return DB::transaction(function () use ($condominium, $id, $user, $manage, $data) {
            $occurrence = $this->occurrences->find($condominium, $id, $manage ? null : $user->id, true);
            if (! $manage && $occurrence->resident_id !== null && $data['unit_id'] !== null
                && ! $this->occurrences->residentHasUnit($occurrence->resident_id, $data['unit_id'])) {
                throw ValidationException::withMessages(['unit_id' => 'A unidade não corresponde ao morador envolvido na ocorrência.']);
            }

            $before = $this->snapshot($occurrence);
            $occurrence = $this->occurrences->update($occurrence, $data);
            $after = $this->snapshot($occurrence);
            if ($before !== $after) {
                $this->occurrences->addHistory($occurrence, [
                    'user_id' => $user->id, 'action' => 'updated', 'before' => $before, 'after' => $after,
                ]);
            }

            return $occurrence;
        });
    }

    public function history(Condominium $condominium, int $id, User $user, bool $manage): Collection
    {
        return $this->occurrences->history($this->find($condominium, $id, $user, $manage));
    }

    private function snapshot(Occurrence $occurrence): array
    {
        return Arr::only($occurrence->toArray(), self::TRACKED_FIELDS);
    }
}
