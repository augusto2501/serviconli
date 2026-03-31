@extends('layouts.serviconli')

@section('page', 'aporte-individual')

@section('title', 'Aporte Individual — '.config('app.name'))

@section('content')
    <div id="serviconli-vue-root" data-affiliate-id="{{ $affiliateId }}"></div>
@endsection
