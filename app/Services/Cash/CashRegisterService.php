<?php

namespace App\Services\Cash;

use App\Models\AccountPayablePayment;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Company;
use App\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CashRegisterService
{
    public const MAX_BALANCE_CENTS = 4294967295;

    public function __construct(private readonly AuditTrailService $audit) {}

    public function open(int $companyId, int $openingCents, ?int $userId): CashSession
    {
        return DB::transaction(function () use ($companyId, $openingCents, $userId): CashSession {
            Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();
            if (CashSession::query()->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['cash' => 'Já existe um caixa aberto para esta empresa. Feche-o antes de abrir outro.']);
            }

            $session = CashSession::create([
                'company_id' => $companyId,
                'opening_amount_cents' => $openingCents,
                'status' => 'open',
                'opened_at' => now(),
                'opened_by' => $userId,
            ]);
            $this->audit->record($companyId, 'cash.opened', 'Caixa #'.$session->id.' aberto.', $userId, 'cash_session', (int) $session->id, ['opening_amount_cents' => $openingCents]);
            return $session;
        });
    }

    public function recordManual(int $sessionId, string $type, int $amountCents, string $description, string $requestKey, ?int $userId): CashMovement
    {
        return DB::transaction(function () use ($sessionId, $type, $amountCents, $description, $requestKey, $userId): CashMovement {
            $session = CashSession::query()->whereKey($sessionId)->where('status', 'open')->lockForUpdate()->firstOrFail();
            $existing = CashMovement::query()->where('request_key', $requestKey)->first();
            if ($existing) {
                if ((int) $existing->cash_session_id !== $sessionId) {
                    throw ValidationException::withMessages(['cash' => 'Esta operação já foi registrada. Atualize a página e tente novamente.']);
                }
                return $existing;
            }

            [$direction, $movementType] = match ($type) {
                'entry' => ['in', 'cash_in'],
                'supply' => ['in', 'supply'],
                'expense' => ['out', 'cash_out'],
                'withdrawal' => ['out', 'withdrawal'],
                default => throw ValidationException::withMessages(['movement_type' => 'Tipo de movimentação inválido.']),
            };

            if ($direction === 'out') {
                $expected = $this->expectedBalance($session);
                if ($expected < 0 || $amountCents > $expected) {
                    throw ValidationException::withMessages(['amount' => 'O valor da saída excede o dinheiro disponível no caixa.']);
                }
            } else {
                $this->assertIncomingFits($session, $amountCents);
            }

            $movement = CashMovement::create([
                'company_id' => $session->company_id,
                'cash_session_id' => $session->id,
                'direction' => $direction,
                'movement_type' => $movementType,
                'amount_cents' => $amountCents,
                'description' => $description,
                'request_key' => $requestKey,
                'created_by' => $userId,
            ]);
            $this->audit->record((int) $session->company_id, 'cash.movement_recorded', 'Movimenta��o manual do caixa registrada: '.$description, $userId, 'cash_movement', (int) $movement->id, ['movement_type' => $movementType, 'direction' => $direction, 'amount_cents' => $amountCents]);
            return $movement;
        });
    }

    public function recordPayablePayment(AccountPayablePayment $payment, ?int $userId): CashSession
    {
        return DB::transaction(function () use ($payment, $userId): CashSession {
            $existingMovement = CashMovement::query()
                ->where('source_type', 'account_payable_payment')
                ->where('source_id', $payment->id)
                ->first();
            if ($existingMovement) {
                return $existingMovement->session;
            }

            $session = CashSession::query()->where('status', 'open')->orderByDesc('id')->lockForUpdate()->first();
            if (! $session) {
                throw ValidationException::withMessages(['payment' => 'Abra o caixa antes de registrar um pagamento de conta em dinheiro.']);
            }
            if ((int) $payment->amount_cents > $this->expectedBalance($session)) {
                throw ValidationException::withMessages(['payment' => 'O dinheiro disponível no caixa é insuficiente para este pagamento. Registre um suprimento ou escolha outro meio.']);
            }

            CashMovement::create([
                'company_id' => $session->company_id,
                'cash_session_id' => $session->id,
                'direction' => 'out',
                'movement_type' => 'payable_payment',
                'amount_cents' => $payment->amount_cents,
                'description' => 'Pagamento de conta a pagar #'.$payment->account_payable_id,
                'source_type' => 'account_payable_payment',
                'source_id' => $payment->id,
                'created_by' => $userId,
            ]);
            $this->audit->record((int) $session->company_id, 'cash.payable_paid', 'Pagamento de conta em dinheiro registrado no caixa.', $userId, 'account_payable_payment', (int) $payment->id, ['amount_cents' => (int) $payment->amount_cents, 'cash_session_id' => (int) $session->id]);

            return $session;
        });
    }

    public function close(int $sessionId, int $countedCents, ?int $userId, ?string $note): CashSession
    {
        return DB::transaction(function () use ($sessionId, $countedCents, $userId, $note): CashSession {
            $session = CashSession::query()->whereKey($sessionId)->where('status', 'open')->lockForUpdate()->firstOrFail();
            $expected = $this->expectedBalance($session);
            if ($expected < 0 || $expected > self::MAX_BALANCE_CENTS) {
                throw ValidationException::withMessages(['counted_amount' => 'O saldo calculado excede a capacidade do caixa.']);
            }
            $session->update([
                'status' => 'closed',
                'expected_closing_cents' => $expected,
                'counted_closing_cents' => $countedCents,
                'difference_cents' => $countedCents - $expected,
                'closed_at' => now(),
                'closed_by' => $userId,
                'closing_note' => $note,
            ]);
            $this->audit->record((int) $session->company_id, 'cash.closed', 'Caixa #'.$session->id.' fechado.', $userId, 'cash_session', (int) $session->id, ['expected_cents' => $expected, 'counted_cents' => $countedCents, 'difference_cents' => $countedCents - $expected]);
            return $session;
        });
    }

    public function expectedBalance(CashSession $session): int
    {
        $totals = CashMovement::query()->where('cash_session_id', $session->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount_cents ELSE 0 END), 0) as total_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN amount_cents ELSE 0 END), 0) as total_out")
            ->first();

        return (int) $session->opening_amount_cents + (int) $totals->total_in - (int) $totals->total_out;
    }

    public function assertIncomingFits(CashSession $session, int $amountCents): void
    {
        $expected = $this->expectedBalance($session);
        if ($amountCents <= 0 || $expected < 0 || $expected > self::MAX_BALANCE_CENTS - $amountCents) {
            throw ValidationException::withMessages(['amount' => 'O saldo do caixa excederia o limite permitido.']);
        }
    }
}
