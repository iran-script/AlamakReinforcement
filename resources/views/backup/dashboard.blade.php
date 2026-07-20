@extends('layouts.app')

@section('title', 'داشبورد')

@section('content')



<h4 class="mb-4">داشبورد سیستم مقاوم‌سازی علمک‌ها</h4>

<div class="row g-3">

    <div class="col-md-3">
        <div class="card-box text-center">
            <h2>12000</h2>
            <p>کل علمک‌ها</p>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card-box text-center">
            <h2 style="color:green">3500</h2>
            <p>انجام شده</p>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card-box text-center">
            <h2 style="color:red">8500</h2>
            <p>در انتظار</p>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card-box text-center">
            <h2 style="color:blue">45</h2>
            <p>عملیات امروز</p>
        </div>
    </div>

</div>

<div class="row mt-4">

    <div class="col-md-6">
        <div class="card-box">
            <h5>آخرین عملیات‌ها</h5>

            <ul class="list-group mt-3">
                <li class="list-group-item">AMK-1001 → انجام شد</li>
                <li class="list-group-item">AMK-1002 → در حال انجام</li>
                <li class="list-group-item">AMK-1003 → انجام شد</li>
            </ul>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card-box">
            <h5>پیشرفت پروژه</h5>

            <div class="progress mt-3">
                <div class="progress-bar" style="width: 40%"></div>
            </div>

        </div>
    </div>

</div>

@endsection