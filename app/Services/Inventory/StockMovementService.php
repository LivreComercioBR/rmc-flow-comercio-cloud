<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StockMovementService
{
    public function record(Product $product, string $type, float $quantity, string $reason, ?int $userId, bool $allowNegative = false): StockMovement
    {
        return DB::transaction(function () use ($product, $type, $quantity, $reason, $userId, $allowNegative): StockMovement {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $current = round((float) $locked->stock_quantity, 3);
            $quantity = round($quantity, 3);

            $delta = match ($type) {
                'entry' => $quantity,
                'exit' => -$quantity,
                'count' => round($quantity - $current, 3),
                default => throw ValidationException::withMessages(['movement_type' => 'Tipo de movimentação inválido.']),
            };
            $newBalance = round($current + $delta, 3);

            $isSaleReversal = $allowNegative && $type === 'entry' && str_starts_with($reason, 'Estorno da venda #');
            if ($quantity < 0 || ($type !== 'count' && $quantity <= 0) || (! $isSaleReversal && $newBalance < 0) || $newBalance > 999999999.999) {
                throw ValidationException::withMessages(['quantity' => 'Informe uma quantidade válida; o saldo não pode ser negativo nem exceder a capacidade do estoque.']);
            }

            $locked->update(['stock_quantity' => $newBalance]);

            return StockMovement::create([
                'company_id' => $locked->company_id,
                'product_id' => $locked->id,
                'movement_type' => $type,
                'quantity_delta' => $delta,
                'balance_after' => $newBalance,
                'reason' => $reason,
                'created_by' => $userId,
            ]);
        });
    }
}
