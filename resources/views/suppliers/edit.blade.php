@extends('layouts.app', ['title' => 'Editar fornecedor | RMC Flow Comércio Cloud'])
@section('content')<section class="card narrow"><p class="muted" style="margin-bottom:8px">CADASTROS</p><h1 class="title">Editar fornecedor</h1><p class="muted">{{ $supplier->name }}</p>@include('suppliers.form')</section>@endsection
