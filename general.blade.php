@extends('layouts.base')

@section('head')
<link rel="icon" type="image/png" sizes="32x32" href="{{ URL::asset('images/favicon/favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="96x96" href="{{ URL::asset('images/favicon/favicon-96x96.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ URL::asset('images/favicon/favicon-16x16.png') }}">
{{ HTML::script('js/jquery.min.js'); }}
{{ HTML::style('http://fast.fonts.net/cssapi/0cdedb6f-2321-41f2-980c-be5e8ad1d186.css'); }}
{{ HTML::style('css/bootstrap.min.css'); }}
{{ HTML::style('css/style.css'); }}
{{ HTML::style('css/jquery-ui.css'); }}
{{ HTML::style('css/jquery-ui-smooth.css'); }}
@stop

@section('body')

@stop

@section('foot')
<!--[if lt IE 9]>
        {{ HTML::script('//html5shim.googlecode.com/svn/trunk/html5.js'); }}
<![endif]-->

{{ HTML::script('js/bootstrap.min.js'); }}
{{ HTML::script('js/jquery-ui.js'); }}

@stop
