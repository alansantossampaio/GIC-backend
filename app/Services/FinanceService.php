<?php

namespace App\Services;

use App\Models\Condominium;
use App\Models\FinancialCategory;
use App\Models\FinancialPayment;
use App\Models\FinancialTransaction;
use App\Repositories\Contracts\FinanceInterface;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

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

    public function monthlyHistory(Condominium $condominium): array
    {
        $months = [];
        foreach ($this->finance->completedPayments($condominium) as $payment) {
            if (! in_array($payment->direction, ['income', 'expense'], true)) {
                throw new UnexpectedValueException('Tipo de lançamento financeiro inválido.');
            }
            $month = $payment->paid_at->copy()->setTimezone('America/Sao_Paulo')->format('Y-m');
            $months[$month] ??= ['income' => BigDecimal::zero(), 'expense' => BigDecimal::zero()];
            $months[$month][$payment->direction] = $months[$month][$payment->direction]->plus($payment->amount);
        }

        krsort($months);

        return array_map(fn ($month, $totals) => [
            'month' => $month,
            'income' => $this->formatAmount($totals['income']),
            'expense' => $this->formatAmount($totals['expense']),
            'balance' => $this->formatAmount($totals['income']->minus($totals['expense'])),
        ], array_keys($months), array_values($months));
    }

    public function withBalance(FinancialTransaction $transaction): array
    {
        $paid = $this->paidAmount($transaction);
        $data = $transaction->toArray();
        unset($data['payments']);
        $data['paid_amount'] = $this->formatAmount($paid);
        $data['balance'] = $this->formatAmount(BigDecimal::of($transaction->amount)->minus($paid));

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

            $paid = $this->paidAmount($transaction);
            $remaining = BigDecimal::of($transaction->amount)->minus($paid);
            if ($amount->isGreaterThan($remaining)) {
                throw ValidationException::withMessages(['amount' => 'O pagamento não pode exceder o saldo do lançamento.']);
            }

            $data['status'] = 'completed';
            $payment = $this->finance->createPayment($transaction, $data);
            $this->finance->updateStatus($transaction, $amount->isEqualTo($remaining) ? 'paid' : 'partial');

            return $payment;
        });
    }

    private function positiveAmount(string $amount): BigDecimal
    {
        $value = BigDecimal::of($amount);
        if (! $value->isPositive()) {
            throw ValidationException::withMessages(['amount' => 'O valor deve ser maior que zero.']);
        }

        return $value;
    }

    private function paidAmount(FinancialTransaction $transaction): BigDecimal
    {
        return $this->finance->payments($transaction)->reduce(
            fn (BigDecimal $total, FinancialPayment $payment) => $payment->status === 'completed' ? $total->plus($payment->amount) : $total,
            BigDecimal::zero()
        );
    }

    private function formatAmount(BigDecimal $amount): string
    {
        $formatted = (string) $amount->toScale(4);
        [$whole, $fraction] = explode('.', $formatted);

        return $whole.'.'.str_pad(rtrim($fraction, '0'), 2, '0');
    }
}
