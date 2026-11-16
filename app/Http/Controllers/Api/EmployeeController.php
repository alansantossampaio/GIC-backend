<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\Employee;
use App\Services\CondominiumManagementAccess;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use UnexpectedValueException;

class EmployeeController extends Controller
{
    public function __construct(
        private CondominiumManagementAccess $managementAccess,
        private EmployeeService $employees,
    ) {}

    public function condominiums(Request $request): JsonResponse
    {
        return response()->json($this->managementAccess->availableTo($request->user()));
    }

    /**
     * Lista os funcionários de um condomínio específico
     *
     * @param Request $request
     * @param Condominium $condominium
     * @return JsonResponse
     */
    public function index(Request $request, Condominium $condominium): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);
        $filter = $request->validate(['type' => ['sometimes', Rule::in(['own', 'third-party'])]]);
        $employees = $this->employees->listForCondominium($condominium, $filter['type'] ?? null);

        return response()->json($employees->map(fn (Employee $employee) => $this->serialize($employee)));
    }

    /**
     * Cria um novo funcionário para o condomínio
     *
     * @param Request $request
     * @param Condominium $condominium
     * @return JsonResponse
     */
    public function store(Request $request, Condominium $condominium): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);
        $employee = $this->employees->create($condominium, $this->validated($request, $condominium));

        return response()->json($this->serialize($employee), 201);
    }

    /**
     * Atualiza os detalhes de um funcionário específico do condomínio
     *
     * @param Request $request
     * @param Condominium $condominium
     * @param Employee $employee
     * @return JsonResponse
     */
    public function update(Request $request, Condominium $condominium, Employee $employee): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);
        $this->employees->ensureBelongsToCondominium($condominium, $employee);
        $employee = $this->employees->update($employee, $this->validated($request, $condominium));

        return response()->json($this->serialize($employee));
    }

    /**
     * Exclui um funcionário específico do condomínio
     *
     * @param Request $request
     * @param Condominium $condominium
     * @param Employee $employee
     * @return JsonResponse
     */
    public function destroy(Request $request, Condominium $condominium, Employee $employee): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);
        $this->employees->ensureBelongsToCondominium($condominium, $employee);
        $this->employees->delete($employee);

        return response()->json(['message' => 'Funcionário excluído.']);
    }

    /**
     * Exibe os detalhes de um funcionário específico do condomínio
     *
     * @param Request $request
     * @param Condominium $condominium
     * @param Employee $employee
     * @return JsonResponse
     */
    public function show(Request $request, Condominium $condominium, Employee $employee): JsonResponse
    {
        $this->authorizeCondominium($request, $condominium);
        $this->employees->ensureBelongsToCondominium($condominium, $employee);

        return response()->json($this->serialize($employee));
    }
    /**
     * Valida os dados de um funcionário para criação ou atualização
     *
     * @param Request $request
     * @param Condominium $condominium
     * @return array
     */
    private function authorizeCondominium(Request $request, Condominium $condominium): void
    {
        abort_unless($this->managementAccess->canManage($request->user(), $condominium), 403);
    }

    private function validated(Request $request, Condominium $condominium): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'string', 'regex:/^(?:[0-9]{11}|[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2})$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'type' => ['required', Rule::in(['own', 'third-party'])],
            'company_id' => ['nullable', 'integer', Rule::exists('company_condominiums', 'company_id')->where('condominium_id', $condominium->id)],
            'position' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive', 'on-leave'])],
            'hire_date' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'cpf.regex' => 'Informe o CPF com 11 dígitos, com ou sem pontuação.',
            'company_id.exists' => 'A empresa informada não está vinculada a este condomínio.',
        ]);

        return $data;
    }

    /**
     * Serializa os dados de um funcionário para resposta JSON
     *
     * @param Employee $employee
     * @return array
     */
    private function serialize(Employee $employee): array
    {
        $type = match ($employee->employment_type) {
            'direct' => 'own',
            'contracted' => 'third-party',
            default => throw new UnexpectedValueException('Tipo de vínculo de funcionário não reconhecido.'),
        };

        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'cpf' => $employee->cpf,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'type' => $type,
            'company_id' => $employee->company_id,
            'condominium_id' => $employee->condominium_id,
            'position' => $employee->position,
            'department' => $employee->department,
            'status' => $employee->status,
            'hire_date' => $employee->hire_date,
            'created_at' => $employee->created_at,
            'updated_at' => $employee->updated_at,
        ];
    }
}
