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

    /**
     * Lista os funcionários de um condomínio específico, opcionalmente filtrando pelo tipo de vínculo
     * @param Condominium $condominium
     * @param string|null $type
     * @return Collection
     */
    public function listForCondominium(Condominium $condominium, ?string $type): Collection
    {
        $employmentType = $type === null ? null : $this->employmentType($type);

        return $this->employees->listForCondominium($condominium, $employmentType);
    }

    /**
     * Cria um novo funcionário para um condomínio específico
     * @param Condominium $condominium
     * @param array $data
     * @return Employee
     */
    public function create(Condominium $condominium, array $data): Employee
    {
        return $this->employees->create($condominium, $this->prepareData($data));
    }

    /**
     * Atualiza um funcionário específico
     * @param Employee $employee
     * @param array $data
     * @return Employee
     */
    public function update(Employee $employee, array $data): Employee
    {
        return $this->employees->update($employee, $this->prepareData($data));
    }

    /**
     * Remove um funcionário específico
     * @param Employee $employee
     * @return void
     */
    public function delete(Employee $employee): void
    {
        $this->employees->delete($employee);
    }

    /**
     * Garante que um funcionário pertence a um condomínio específico
     * @param Condominium $condominium
     * @param Employee $employee
     * @return void
     */
    public function ensureBelongsToCondominium(Condominium $condominium, Employee $employee): void
    {
        abort_unless((int) $employee->condominium_id === $condominium->id, 404);
    }

    /**
     * Prepara os dados de um funcionário antes de criar ou atualizar
     * @param array $data
     * @return array
     */
    private function prepareData(array $data): array
    {
        $data['employment_type'] = $this->employmentType($data['type']);
        unset($data['type']);
        foreach (['email', 'phone', 'company_id', 'department', 'hire_date'] as $field) {
            $data[$field] = $data[$field] ?? null;
        }

        return $data;
    }

    /**
     * Converte o tipo de vínculo de funcionário para o formato interno
     * @param string $type
     * @return string
     */
    private function employmentType(string $type): string
    {
        return match ($type) {
            'own' => 'direct',
            'third-party' => 'contracted',
            default => throw new InvalidArgumentException('Tipo de vínculo de funcionário não reconhecido.'),
        };
    }
}
