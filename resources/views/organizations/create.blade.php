@extends('layouts.app')
@section('title', 'ایجاد سازمان')
@section('content')<div class="page-heading"><div class="page-eyebrow">SYSTEM / ORGANIZATIONS</div><h4><i class="bi bi-building-add text-primary"></i> ایجاد سازمان</h4></div><div class="card-box"><div class="card-body p-4"><form method="POST" action="{{ route('organizations.store') }}">@include('organizations._form')</form></div></div>@endsection
