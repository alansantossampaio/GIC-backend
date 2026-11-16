<?php

namespace App\Repositories\Core;

use App\Models\Condominium;
use App\Models\Employee;
use App\Repositories\Contracts\EmployeeInterface;
use Illuminate\Database\Eloquent\Collection;

class EmployeeRepository implements EmployeeInterface
{
    public function listForCondominium(Condominium $condominium, ?string $employmentType): Collection
    {
        $query = $condominium->employees()->orderBy('name');
        if ($employmentType !== null) {
            $query->where('employment_type', $employmentType);
        }

        return $query->get();
    }

    public function create(Condominium $condominium, array $data): Employee
    {
        return $condominium->employees()->create($data);
    }

    public function update(Employee $employee, array $data): Employee
    {
        $employee->update($data);

        return $employee;
    }

    public function delete(Employee $employee): void
    {
        $employee->delete();
    }
}
