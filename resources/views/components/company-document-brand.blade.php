<div class="document-brand">
    <div class="document-logo">@if($logoUrl)<img src="{{ $logoUrl }}" alt="Logo de {{ $company->trade_name }}">@endif</div>
    <div class="document-company-details">
        <h2>{{ $company->trade_name }}</h2>
        @if($company->legal_name)<div>{{ $company->legal_name }}</div>@endif
        @if($company->document)<div>Documento: {{ $company->document }}</div>@endif
        @if($company->phone)<div>Telefone: {{ $company->phone }}</div>@endif
        @if($addressText)<div>{{ $addressText }}</div>@endif
    </div>
</div>
