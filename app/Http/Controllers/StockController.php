<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Inventory\StockMovementService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class StockController extends Controller
{
    public function show(int $product, CompanyContext $context): View
    {
        $record = $this->findForCompany($product, $context);
        $movements = $record->stockMovements()->with('creator')->paginate(30);
        return view('products.stock', compact('product', 'record', 'movements'));
    }

    public function store(Request $request, int $product, CompanyContext $context, StockMovementService $stock): RedirectResponse
    {
        $record = $this->findForCompany($product, $context);
        $data = $request->validate([
            'movement_type' => ['required', 'in:entry,exit,count'],
            'quantity' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'regex:/^\d+(?:[.,]\d{1,3})?$/'],
            'reason' => ['required', 'string', 'max:180'],
        ]);
        $stock->record($record, $data['movement_type'], (float) str_replace(',', '.', $data['quantity']), $data['reason'], $request->user()->id);
        return redirect()->route('products.stock', $record->id)->with('stock_notice', 'Movimentação registrada e saldo atualizado.');
    }

    private function findForCompany(int $product, CompanyContext $context): Product
    {
        abort_unless($context->id(), 403);
        return Product::query()->findOrFail($product);
    }
}
