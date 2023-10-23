@extends('layouts.core.install')

@section('title', trans('messages.finish'))

@section('content')


        <h4 class="text-primary fw-600 mb-3"><span class="material-symbols-rounded me-2">task_alt</span> Congratulations, you've successfully installed the application.</h4>
            
        Remember that all your configurations were saved in <strong class="text-semibold">[APP_ROOT]/.env</strong> file. You can change it when needed.
        <br /><br />
        Now, you can go to your Admin Panel at <a class="text-semibold" href="{{ action('Admin\HomeController@index') }}">{{ action('Admin\HomeController@index') }}</a>
        <br /><br />

        Thank you for chosing us.
        <div class="clearfix"><!-- --></div>      
<br />

@endsection
