@extends('layouts.app')

@section('content')
    @role('student')
        @include('dashboard.student')
    @endrole
    @role('officer')
        @include('dashboard.officer')
    @endrole
    @role('admin')
        @include('dashboard.admin')
    @endrole
@endsection
