@extends('layouts.core.frontend', [
'menu' => 'proxies',
])

@section('title', 'New Proxies')

@section('page_header')

<div class="page-title">
    <ul class="breadcrumb breadcrumb-caret position-right">
        <li class="breadcrumb-item"><a href="{{ action("HomeController@index") }}">{{ trans('messages.home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ action("ProxiesController@index") }}">Proxies</a></li>
    </ul>
    <h1 class="mc-h1">
        <span class="text-semibold">New proxies</span>
    </h1>
</div>

@endsection

@section('content')

@include('proxies.form')
<script>
    var form_count = 1;
    $(document).on('click','.add_form',function(){
        var form_html = '<div class="row" id="form_section_'+form_count+'">\n\
            <div class="col-md-6">\n\
                <div class="form-group control-text">\n\
                    <label> Ip Address</label>\n\
                    <input id="ip_address[]" placeholder="" value="" type="text" name="ip_address[]" class="form-control  ">\n\
                </div>\n\
            </div>\n\
            <div class="col-md-4">\n\
                <div class="form-group control-text">\n\
                    <label> Port</label>\n\
                    <input id="port[]" placeholder="" value="" type="text" name="port[]" class="form-control  ">\n\
                </div>\n\
            </div>\n\
            <div class="col-md-2">\n\
                <a href="javascript:" role="button" data-count="'+form_count+'" class="btn btn-danger mt-4 remove_form">\n\
                    <span class="material-symbols-rounded">remove</span>Proxy\n\
                </a>\n\
            </div>\n\
        </div>';
        $('#main_form_section').append(form_html);
        form_count++;
    })
    $(document).on('click','.remove_form',function(){
        $('#form_section_'+$(this).attr('data-count')).remove();
    })
</script>

@endsection
