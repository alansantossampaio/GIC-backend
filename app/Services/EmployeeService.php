<?php

namespace App\Services;

use App\Models\Condominium;
use App\Models\Employee;
use App\Repositories\Contracts\EmployeeInterface;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class EmployeeService
{
    public function __construct(private EmployeeInterface $employees) {}

    public function listForCondominium(Condominium $condominium, ?string $type): Collection
    {
        $employmentType = $type === null ? null : $this->employmentType($type);

        return $this->employees->listForCondominium($condominium, $employmentType);
    }

    public function create(Condominium $condominium, array $data): Employee
    {
        return $this->employees->create($condominium, $this->prepareData($data));
    }

    public function update(Employee $employee, array $data): Employee
    {
        return $this->employees->update($employee, $this->prepareData($data));
    }

    public function delete(Employee $employee): void
    {
        $this->employees->delete($employee);
    }

    public function ensureBelongsToCondominium(Condominium $condominium, Employee $employee): void
    {
        abort_unless((int) $employee->condominium_id === $condominium->id, 404);
    }

    private function prepareData(array $data): array
    {
        $data['employment_type'] = $this->employmentType($data['type']);
        unset($data['type']);
        foreach (['email', 'phone', 'company_id', 'department', 'hire_date'] as $field) {
            $data[$field] = $data[$field] ?? null;
        }

        return $data;
    }

    private function employmentType(string $type): string
    {
        return match ($type) {
            'own' => 'direct',
            'third-party' => 'contracted',
            default => throw new InvalidArgumentException('Tipo de vínculo de funcionário não reconhecido.'),
        };
    }
}
