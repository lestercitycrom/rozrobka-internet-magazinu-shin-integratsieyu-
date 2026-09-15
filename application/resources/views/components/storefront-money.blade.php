@props(['minor', 'currency', 'decimals' => 2])
@php
    $currencyCode = trim((string) $currency);
    $currencyLabel = strtoupper($currencyCode) === 'NOK' ? 'kr' : $currencyCode;
@endphp
{{ number_format(((int) $minor) / 100, (int) $decimals, ',', ' ') }} {{ $currencyLabel }}
