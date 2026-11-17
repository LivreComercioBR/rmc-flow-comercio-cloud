@extends('layouts.app', ['title' => 'Novo cliente | RMC Flow Comércio Cloud'])
@section('content')
<section class="card narrow"><p class="muted" style="margin-bottom:8px">CADASTROS</p><h1 class="title">Novo cliente</h1><p class="muted">Os dados serão vinculados à empresa ativa.</p>@include('customers.form')</section>
@endsection
