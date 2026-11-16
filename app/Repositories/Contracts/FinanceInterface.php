<?php

namespace App\Repositories\Contracts;

use App\Models\Condominium;
use App\Models\FinancialCategory;
use App\Models\FinancialPayment;
use App\Models\FinancialTransaction;
use Illuminate\Database\Eloquent\Collection;

interface FinanceInterface
{
    public function categories(Condominium $condominium): Collection;

    public function createCategory(Condominium $condominium, array $data): FinancialCategory;

    public function transactions(Condominium $condominium): Collection;

    public function findTransaction(Condominium $condominium, int $id, bool $lock = false): FinancialTransaction;

    public function createTransaction(Condominium $condominium, array $data): FinancialTransaction;

    public function payments(FinancialTransaction $transaction): Collection;

    public function createPayment(FinancialTransaction $transaction, array $data): FinancialPayment;

    public function updateStatus(FinancialTransaction $transaction, string $status): void;
}
