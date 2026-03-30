@extends('layouts.serviconli')

@section('page', 'ficha')

@section('title', 'Ficha 360° — '.config('app.name'))

@section('content')
    <div id="serviconli-vue-root" data-affiliate-id="{{ $affiliateId }}"></div>
@endsection
