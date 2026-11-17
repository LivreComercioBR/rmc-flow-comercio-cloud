@extends('layouts.app', ['title' => 'Novo produto | RMC Flow Comércio Cloud'])
@section('content')<section class="card narrow"><p class="muted" style="margin-bottom:8px">CATÁLOGO</p><h1 class="title">Novo produto / peça</h1><p class="muted">A quantidade inicial será registrada no histórico de estoque.</p>@include('products.form')</section>@endsection
