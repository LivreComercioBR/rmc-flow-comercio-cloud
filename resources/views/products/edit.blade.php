@extends('layouts.app', ['title' => 'Editar produto | RMC Flow Comércio Cloud'])
@section('content')<section class="card narrow"><p class="muted" style="margin-bottom:8px">CATÁLOGO</p><h1 class="title">Editar produto / peça</h1><p class="muted">{{ $product->name }} · saldo {{ $product->stock_quantity }} {{ $product->unit }}</p>@include('products.form')</section>@endsection
