@extends('layouts.app')

@section('title','مدیریت نقش ها')

@section('content')

<div class="card-box">

    <div class="d-flex justify-content-between mb-3">

        <h4>مدیریت نقش ها</h4>

        <a href="{{ route('roles.create') }}"
           class="btn btn-success">

            <i class="bi bi-plus-circle"></i>

            نقش جدید

        </a>

    </div>

    <table class="table table-bordered table-hover">

        <thead>

        <tr>

            <th width="70">ID</th>

            <th>نام نقش</th>

            <th width="220">عملیات</th>

        </tr>

        </thead>

        <tbody>

        @foreach($roles as $role)

            <tr>

                <td>{{ $role->id }}</td>

                <td>{{ $role->name }}</td>

                <td>

                    <a href="{{ route('roles.edit',$role) }}"
                       class="btn btn-warning btn-sm">

                        <i class="bi bi-pencil"></i>

                    </a>

                    @if($role->name!='superadmin')

                        <form
                            method="POST"
                            action="{{ route('roles.destroy',$role) }}"
                            class="d-inline">

                            @csrf
                            @method('DELETE')

                            <button
                                class="btn btn-danger btn-sm"
                                onclick="return confirm('حذف شود؟')">

                                <i class="bi bi-trash"></i>

                            </button>

                        </form>

                    @endif

                </td>

            </tr>

        @endforeach

        </tbody>

    </table>

    {{ $roles->links() }}

</div>

@endsection