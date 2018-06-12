@extends('layouts.base')

@section('head')
<link rel="apple-touch-icon" sizes="57x57" href="{{ URL::asset('images/favicon/apple-icon-57x57.png') }}">
<link rel="apple-touch-icon" sizes="60x60" href="{{ URL::asset('images/favicon/apple-icon-60x60.png') }}">
<link rel="apple-touch-icon" sizes="72x72" href="{{ URL::asset('images/favicon/apple-icon-72x72.png') }}">
<link rel="apple-touch-icon" sizes="76x76" href="{{ URL::asset('images/favicon/apple-icon-76x76.png') }}">
<link rel="apple-touch-icon" sizes="114x114" href="{{ URL::asset('images/favicon/apple-icon-114x114.png') }}">
<link rel="apple-touch-icon" sizes="120x120" href="{{ URL::asset('images/favicon/apple-icon-120x120.png') }}">
<link rel="apple-touch-icon" sizes="144x144" href="{{ URL::asset('images/favicon/apple-icon-144x144.png') }}">
<link rel="apple-touch-icon" sizes="152x152" href="{{ URL::asset('images/favicon/apple-icon-152x152.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ URL::asset('images/favicon/apple-icon-180x180.png') }}">
<link rel="icon" type="image/png" sizes="192x192"  href="{{ URL::asset('images/favicon/android-icon-192x192.png') }}"> 
<link rel="icon" type="image/png" sizes="32x32" href="{{ URL::asset('images/favicon/favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="96x96" href="{{ URL::asset('images/favicon/favicon-96x96.png') }}">
<link rel="manifest" href="{{ URL::asset('images/favicon/manifest.json') }}">
{{ HTML::script('js/jquery.min.js'); }}
{{ HTML::style('//fast.fonts.net/cssapi/0cdedb6f-2321-41f2-980c-be5e8ad1d186.css'); }}
{{ HTML::style('css/bootstrap.min.css'); }}
{{ HTML::style('css/style.css'); }}
{{ HTML::style('css/jquery.mCustomScrollbar.css'); }}
{{ HTML::style('css/jquery.datetimepicker.css'); }}
{{ HTML::style('css/uploadfile.css'); }}
{{ HTML::script('plugins/ckeditor/ckeditor.js'); }}
{{ HTML::script('plugins/ckfinder/ckfinder_v1.js'); }}
{{ HTML::style('css/jquery-ui.css'); }}
{{ HTML::style('css/jquery-ui-smooth.css'); }}
{{ HTML::style('css/bootstrap-switch.css'); }}
{{ HTML::style('css/vast.css'); }}
{{ HTML::style('css/responsive.css'); }}
{{ HTML::style('css/jquery.bxslider.css'); }}
{{ HTML::style('css/focuspoint.css'); }}
{{ HTML::style('css/jquery.Jcrop.min.css'); }}
@stop

@section('body')
@if((isset($data['channelFlag']) && $data['channelFlag'] == 0 ) && (isset($data['role']) && $data['role'] == 2))
@include('default.topbar')
@endif
@stop

@section('foot')
<!--[if lt IE 9]>
        {{ HTML::script('//html5shim.googlecode.com/svn/trunk/html5.js'); }}
<![endif]-->

{{ HTML::script('js/bootstrap.min.js'); }}
{{ HTML::script('plugins/crop/jQcrop.js'); }}
{{ HTML::style('plugins/crop/jQcrop.css'); }}
{{ HTML::script('js/jquery.mCustomScrollbar.concat.min.js'); }}
{{ HTML::script('js/jquery.uploadfile.js'); }}
{{ HTML::script('js/script.js'); }}
{{ HTML::script('js/audioPlayerScript.js'); }}
{{ HTML::script('js/jquery.datetimepicker.js'); }}
{{ HTML::script('js/moment.min.js'); }}
{{ HTML::script('js/fbLogin.js'); }}
{{ HTML::script('js/analytics.js'); }}
{{ HTML::script('js/jquery-ui.js'); }}
{{ HTML::script('js/urlScripts.js'); }}
{{ HTML::script('js/fileUploadScripts.js'); }}
{{ HTML::script('js/bouncerScripts.js'); }}
{{ HTML::script('js/scripts.js'); }}
{{ HTML::script('js/bootstrap-switch.min.js'); }}
{{ HTML::script('js/vastScripts.js'); }}
{{ HTML::script('js/jquery.bxslider.js'); }}
{{ HTML::script('js/jquery.focuspoint.js'); }}
{{ HTML::script('js/jquery.Jcrop.min.js'); }}
@stop
