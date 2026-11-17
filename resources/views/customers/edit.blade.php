@extends('layouts.app', ['title' => 'Editar cliente | RMC Flow Comércio Cloud'])
@section('content')
<section class="card narrow"><p class="muted" style="margin-bottom:8px">CADASTROS</p><h1 class="title">Editar cliente</h1><p class="muted">{{ $customer->name }}</p>@include('customers.form')</section>
@endsection
