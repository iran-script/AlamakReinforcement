@extends('layouts.app')
@section('title', 'ویرایش سازمان')
@section('content')<div class="page-heading"><div class="page-eyebrow">SYSTEM / ORGANIZATIONS</div><h4><i class="bi bi-building-gear text-primary"></i> ویرایش سازمان</h4></div><div class="card-box"><div class="card-body p-4"><form method="POST" action="{{ route('organizations.update', $organization) }}">@include('organizations._form')</form></div></div>@endsection
