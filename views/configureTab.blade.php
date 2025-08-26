@extends('sMultisite::index')
@section('header')
    <button class="s-btn s-btn--primary" onclick="openAddDomainModal();">
        <i data-lucide="plus" class="w-4 h-4"></i>@lang('global.add')
    </button>
    <button class="s-btn s-btn--success" onclick="window.sMultisite.submitForm('#form');">
        <i data-lucide="save" class="w-4 h-4"></i>@lang('global.save')
    </button>
@endsection
@section('content')
    <form id="form" name="form" method="post" enctype="multipart/form-data" action="{{sMultisite::route('sMultisite.uconfigure')}}" onsubmit="documentDirty=false;">@csrf
        @if(!is_writable(EVO_CORE_PATH . 'custom/config/seiger/settings/sMultisite.php'))
            <div class="s-alert s-alert--danger">
                <i data-lucide="alert-triangle" class="s-alert--icon-danger"></i>
                <div>
                    <strong class="font-semibold">@lang('sMultisite::global.warning')</strong><br>
                    @lang('sMultisite::global.not_writable', ['file' => EVO_CORE_PATH . 'custom/config/seiger/settings/sMultisite.php'])
                </div>
            </div>
        @endif
        @foreach($domains as $domain)
            @include('sMultisite::partials.domainForm', ['item' => $domain])
        @endforeach
    </form>
@endsection
@push('scripts.bot')
    <script>
        function openAddDomainModal() {
            let formHtml = `
                <div class="m-2">
                    <label class="block text-sm font-medium mb-1">` + '@lang('sMultisite::global.domain_key')' + `
                        <span class="inline-flex items-center justify-center align-middle translate-y-[-2px] text-slate-400">
                            <i data-lucide="help-circle" data-tooltip="` + '@lang('sMultisite::global.domain_key_help')' + `" class="w-4 h-4 inline"></i>
                        </span>
                    </label>
                    <input name="domain_key" type="text" class="w-full border rounded px-3 py-2 text-sm darkness:bg-slate-800" placeholder="example"/>
                </div>
                <div class="m-2">
                    <label class="block text-sm font-medium mb-1">` + '@lang('sMultisite::global.domain')' + `
                        <span class="inline-flex items-center justify-center align-middle translate-y-[-2px] text-slate-400">
                            <i data-lucide="help-circle" data-tooltip="` + '@lang('sMultisite::global.domain_help')' + `" class="w-4 h-4 inline"></i>
                        </span>
                    </label>
                    <input name="domain" type="text" class="w-full border rounded px-3 py-2 text-sm darkness:bg-slate-800" placeholder="example.com"/>
                </div>
            `;

            alertify.confirm()
                .set({
                    title: `<h3>@lang('sMultisite::global.add_help')</h3>`,
                    message: `<form id="redirectForm">${formHtml}</form>`,
                    onok: function () {
                        window.parent.document.getElementById('mainloader')?.classList.add('show');
                        let redirectForm = document.getElementById('redirectForm');
                        let formData = new FormData(redirectForm);

                        if (!formData.get('domain_key') || !formData.get('domain')) {
                            alertify.error("@lang('sMultisite::global.error_empty_fields')");
                            window.parent.document.getElementById('mainloader')?.classList.remove('show');
                            return false;
                        }

                        (async () => {
                            let response = await window.sMultisite.callApi('{!!sMultisite::route('sMultisite.adomain')!!}', formData);

                            if (response.success === true) {
                                parent.location.reload();
                            } else {
                                alertify.error(response.message);
                            }

                            window.parent.document.getElementById('mainloader')?.classList.remove('show');
                        })();
                        return false;
                    },
                    oncancel: function () {
                        alertify.notify("@lang('sSeo::global.action_cancelled')");
                    }
                })
                .set('labels', {ok: "@lang('global.save')", cancel: "@lang('global.cancel')"})
                .set('closable', false)
                .set('transition', 'zoom')
                .set('defaultFocus', 'cancel')
                .set('notifier', 'delay', 5)
                .show();
            window.sSeo.queueLucide();
        }
    </script>
@endpush
