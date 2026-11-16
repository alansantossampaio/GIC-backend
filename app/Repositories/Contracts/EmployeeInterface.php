<?php

namespace App\Repositories\Contracts;

use App\Models\Condominium;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Collection;

interface EmployeeInterface
{
    public function listForCondominium(Condominium $condominium, ?string $employmentType): Collection;

    public function create(Condominium $condominium, array $data): Employee;

    public function update(Employee $employee, array $data): Employee;

    public function delete(Employee $employee): void;
}
