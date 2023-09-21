@extends('manager::template.page')
@section('content')
    <h1><i class="@lang('sMultisite::global.icon')" data-tooltip="@lang('sMultisite::global.description')"></i>@lang('sMultisite::global.title')</h1>
    <div class="sectionBody">
        <div class="tab-pane" id="resourcesPane">
            <script>tpResources = new WebFXTabPane(document.getElementById('resourcesPane'), false);</script>
            <div class="tab-page configureTab" id="configureTab">
                <h2 class="tab">
                    <a href="{{sMultisite::route('sMultisite.index')}}">
                        <span><i class="@lang('sMultisite::global.configure_icon')" data-tooltip="@lang('sMultisite::global.configure_help')"></i> @lang('sMultisite::global.configure')</span>
                    </a>
                </h2>
                <script>tpResources.addTabPage(document.getElementById('configureTab'));</script>
                <div class="container container-body">
                    @include('sMultisite::configureTab')
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts.top')
    @include('sMultisite::partials.style')
@endpush
@push('scripts.bot')
    <div id="copyright"><a href="https://seigerit.com/" target="_blank"><img src="{{evo()->getConfig('site_url', '/')}}assets/site/seigerit-yellow.svg"/></a></div>
    <script src="//cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>
    <link rel="stylesheet" href="//cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css"/>
    <link rel="stylesheet" href="//cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/bootstrap.min.css"/>
    <script>
        // Form Validation and Saving
        function saveForm(selector) {
            documentDirty=false;
            let messages = [];
            let validates = document.querySelectorAll(selector + " [data-validate]");
            validates.forEach(function (element) {
                let rule = element.getAttribute('data-validate').split(":");
                switch (rule[0]) {
                    case "hideFromTreeCheck": // Hide in tree if domain is not active
                        if (document.form.hide_from_tree.value == 1 && document.form.active_on.value == 1) {
                            messages.push(element.getAttribute('data-text'));
                            element.classList.remove('is-valid');
                            element.classList.add('is-invalid');
                        } else {
                            element.classList.remove('is-invalid');
                            element.classList.add('is-valid');
                        }
                        break;
                    case "textNoEmpty": // Not an empty field
                        if (element.value.length < 1) {
                            messages.push(element.getAttribute('data-text'));
                            element.classList.remove('is-valid');
                            element.classList.add('is-invalid');
                        } else {
                            element.classList.remove('is-invalid');
                            element.classList.add('is-valid');
                        }
                        break;
                }
            });
            if (messages.length < 1) {
                document.querySelector(selector).submit();
            } else {
                alertify.alert('@lang('sMultisite::global.check_fields')', messages.join("<br/>"), function(){
                    alertify.error('@lang('sMultisite::global.not_saved')')
                }).set('modal', true);
            }
        }
    </script>
@endpush
