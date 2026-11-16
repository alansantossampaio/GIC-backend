<?php

namespace App\Services;

use App\Models\Condominium;
use App\Models\User;
use App\Repositories\Contracts\CondominiumInterface;
use App\Rules\Cnpj;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CondominiumService
{
    public function __construct(private CondominiumInterface $condominiums) {}

    /** @return Collection<int, Condominium> */
    public function availableTo(User $user): Collection
    {
        return $this->condominiums->availableTo($user);
    }

    /**
     * Cria o condomínio com os dados fornecidos
     * @param array $data
     * @return Condominium
     */
    public function create(array $data): Condominium
    {
        $data = $this->normalizeDocument($data);

        return $this->save(fn () => $this->condominiums->create($data + ['status' => 'active']));
    }

    /**
     * Atualiza os dados de um condomínio específico
     * @param Condominium $condominium
     * @param array $data
     * @return Condominium
     */
    public function update(Condominium $condominium, array $data): Condominium
    {
        return DB::transaction(function () use ($condominium, $data) {
            $condominium = $this->condominiums->findForUpdate($condominium->id);
            Gate::authorize('update', $condominium);
            $data = $this->normalizeDocument($data, $condominium->id);

            return $this->save(fn () => $this->condominiums->update($condominium, $data));
        });
    }

    /**
     * Altera o status de um condomínio específico
     * @param Condominium $condominium
     * @param string $status
     * @return Condominium
     */
    public function changeStatus(Condominium $condominium, string $status): Condominium
    {
        return DB::transaction(function () use ($condominium, $status) {
            $condominium = $this->condominiums->findForUpdate($condominium->id);
            Gate::authorize('changeStatus', $condominium);

            return $this->condominiums->update($condominium, ['status' => $status]);
        });
    }

    /**
     * Normaliza o documento (CNPJ) do condomínio
     * @param array $data
     * @param int|null $exceptId
     * @return array
     */
    private function normalizeDocument(array $data, ?int $exceptId = null): array
    {
        $document = Cnpj::normalize($data['document']);
        if ($document === null) {
            throw ValidationException::withMessages(['document' => ['Informe um CNPJ válido, com 14 caracteres.']]);
        }
        if ($this->condominiums->documentExists($document, $exceptId)) {
            throw ValidationException::withMessages(['document' => ['Este CNPJ já está cadastrado.']]);
        }

        $data['document'] = $document;

        return $data;
    }

    /**
     * Salva as alterações de um condomínio, tratando exceções de banco de dados
     * @param Closure $operation
     * @return Condominium
     */
    private function save(Closure $operation): Condominium
    {
        try {
            return $operation();
        } catch (QueryException $exception) {
            $message = $exception->getMessage();
            if (($exception->getCode() === '23505' && str_contains($message, 'condominiums_document_unique'))
                || ($exception->getCode() === '23000' && str_contains($message, 'condominiums.document'))) {
                throw ValidationException::withMessages(['document' => ['Este CNPJ já está cadastrado.']]);
            }

            throw $exception;
        }
    }
}
