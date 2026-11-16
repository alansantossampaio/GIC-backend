<?php

namespace App\Services;

use App\Models\Condominium;
use App\Models\FinancialCategory;
use App\Models\FinancialPayment;
use App\Models\FinancialTransaction;
use App\Repositories\Contracts\FinanceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceService
{
    public function __construct(private FinanceInterface $finance) {}

    public function categories(Condominium $condominium): Collection
    {
        return $this->finance->categories($condominium);
    }

    public function createCategory(Condominium $condominium, array $data): FinancialCategory
    {
        return $this->finance->createCategory($condominium, $data);
    }

    public function transactions(Condominium $condominium): Collection
    {
        return $this->finance->transactions($condominium);
    }

    public function findTransaction(Condominium $condominium, int $id): FinancialTransaction
    {
        return $this->finance->findTransaction($condominium, $id);
    }

    public function createTransaction(Condominium $condominium, array $data): FinancialTransaction
    {
        $this->positiveAmount($data['amount']);
        $data['status'] = 'open';

        return $this->finance->createTransaction($condominium, $data);
    }

    public function payments(Condominium $condominium, int $id): Collection
    {
        return $this->finance->payments($this->findTransaction($condominium, $id));
    }

    public function withBalance(FinancialTransaction $transaction): array
    {
        $paid = $this->paidCents($transaction);
        $data = $transaction->toArray();
        unset($data['payments']);
        $data['paid_amount'] = $this->formatCents($paid);
        $data['balance'] = $this->formatCents($this->cents($transaction->amount) - $paid);

        return $data;
    }

    public function createPayment(Condominium $condominium, int $id, array $data): FinancialPayment
    {
        $amount = $this->positiveAmount($data['amount']);

        return DB::transaction(function () use ($condominium, $id, $data, $amount) {
            $transaction = $this->finance->findTransaction($condominium, $id, true);
            if (! in_array($transaction->status, ['open', 'partial'], true)) {
                throw ValidationException::withMessages(['transaction' => 'Este lançamento não aceita pagamentos.']);
            }

            $paid = $this->paidCents($transaction);
            $remaining = $this->cents($transaction->amount) - $paid;
            if ($amount > $remaining) {
                throw ValidationException::withMessages(['amount' => 'O pagamento não pode exceder o saldo do lançamento.']);
            }

            $data['status'] = 'completed';
            $payment = $this->finance->createPayment($transaction, $data);
            $this->finance->updateStatus($transaction, $amount === $remaining ? 'paid' : 'partial');

            return $payment;
        });
    }

    private function positiveAmount(string $amount): int
    {
        $cents = $this->cents($amount);
        if ($cents <= 0) {
            throw ValidationException::withMessages(['amount' => 'O valor deve ser maior que zero.']);
        }

        return $cents;
    }

    private function cents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    private function paidCents(FinancialTransaction $transaction): int
    {
        return $this->finance->payments($transaction)->sum(fn (FinancialPayment $payment) => $payment->status === 'completed' ? $this->cents($payment->amount) : 0);
    }

    private function formatCents(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
