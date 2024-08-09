<form id="form" name="form" method="post" action="{{sMultisite::route('sMultisite.update')}}" onsubmit="documentDirty=false;">
    @foreach(Seiger\sMultisite\Models\sMultisite::all() as $domain)
        <div id="domain-{{$domain->id}}">
            <input type="hidden" name="domains[{{$domain->id}}][key]" value="{{$domain->key}}">
            <div class="row form-row form-element-input">
                <label class="control-label col-5 col-md-4 col-lg-2">
                    <span>@lang('sMultisite::global.domain')</span>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sMultisite::global.domain_help')"></i>
                    @if($domain->key == 'default')
                        <sup><span class="badge bg-seigerit">Default</span></sup>
                    @endif
                </label>
                <div class="col-7 col-md-8 col-lg-10">
                    <input name="domains[{{$domain->id}}][domain]" value="{{$domain->domain}}" data-validate="textNoEmpty" data-text="<b>@lang('sMultisite::global.domain')</b> @lang('sMultisite::global.empty_field')" placeholder="example.com" type="text" class="form-control" onchange="documentDirty=true;">
                </div>
            </div>

            <div class="row form-row form-element-input">
                <label class="control-label col-5 col-md-4 col-lg-2">
                    <span>@lang('global.sitename_title')</span>
                    <i class="fa fa-question-circle" data-tooltip="@lang('global.sitename_message')"></i>
                </label>
                <div class="col-7 col-md-8 col-lg-10">
                    <input name="domains[{{$domain->id}}][site_name]" value="{{$domain->site_name}}" data-validate="textNoEmpty" data-text="<b>@lang('global.sitename_title')</b> @lang('sMultisite::global.empty_field')" placeholder="Evolution CMS website" type="text" class="form-control" onchange="documentDirty=true;">
                </div>
            </div>

            <div class="row form-row form-element-input">
                <label class="control-label col-5 col-md-4 col-lg-2">
                    <span>@lang('global.sitestart_title')</span>
                    <i class="fa fa-question-circle" data-tooltip="@lang('global.sitestart_message')"></i>
                </label>
                <div class="col-7 col-md-8 col-lg-10">
                    <input name="domains[{{$domain->id}}][site_start]" value="{{$domain->site_start}}" type="text" class="form-control" onchange="documentDirty=true;">
                </div>
            </div>

            <div class="row form-row form-element-input">
                <label class="control-label col-5 col-md-4 col-lg-2">
                    <span>@lang('global.errorpage_title')</span>
                    <i class="fa fa-question-circle" data-tooltip="@lang('global.errorpage_message')"></i>
                </label>
                <div class="col-7 col-md-8 col-lg-10">
                    <input name="domains[{{$domain->id}}][error_page]" value="{{$domain->error_page}}" type="text" class="form-control" onchange="documentDirty=true;">
                </div>
            </div>

            <div class="row form-row form-element-input">
                <label class="control-label col-5 col-md-4 col-lg-2">
                    <span>@lang('global.unauthorizedpage_title')</span>
                    <i class="fa fa-question-circle" data-tooltip="@lang('global.unauthorizedpage_message')"></i>
                </label>
                <div class="col-7 col-md-8 col-lg-10">
                    <input name="domains[{{$domain->id}}][unauthorized_page]" value="{{$domain->unauthorized_page}}" type="text" class="form-control" onchange="documentDirty=true;">
                </div>
            </div>

            @if($domain->key != 'default')
                <div class="row form-row form-element-input">
                    <label class="control-label col-5 col-md-4 col-lg-2">
                        <span>@lang('sMultisite::global.domain_on')</span>
                        <i class="fa fa-question-circle" data-tooltip="@lang('sMultisite::global.domain_on_help')"></i>
                    </label>
                    <div class="col-7 col-md-8 col-lg-10">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" id="active_on_{{$domain->id}}" class="custom-control-input" name="domains[{{$domain->id}}][active]" value="{{$domain->active}}" onclick="changestate(document.form.active_on_{{$domain->id}});" onchange="documentDirty=true;" @if($domain->active) checked @endif>
                            <label class="custom-control-label" for="active_on_{{$domain->id}}"></label>
                        </div>
                    </div>
                </div>

                <div class="row form-row form-element-input">
                    <label class="control-label col-5 col-md-4 col-lg-2">
                        <span>@lang('sMultisite::global.hide_from_tree')</span>
                        <i class="fa fa-question-circle" data-tooltip="@lang('sMultisite::global.hide_from_tree_help')"></i>
                    </label>
                    <div class="col-7 col-md-8 col-lg-10">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" id="hide_from_tree_{{$domain->id}}" class="custom-control-input" name="domains[{{$domain->id}}][hide_from_tree]" value="{{$domain->hide_from_tree}}" data-validate="hideFromTreeCheck" data-text="<b>@lang('sMultisite::global.hide_from_tree')</b> @lang('sMultisite::global.must_be_dis-active')" onclick="changestate(document.form.hide_from_tree_{{$domain->id}});" onchange="documentDirty=true;" @if($domain->hide_from_tree) checked @endif>
                            <label class="custom-control-label" for="hide_from_tree_{{$domain->id}}"></label>
                        </div>
                    </div>
                </div>
            @endif

            <div class="split my-3"></div>
        </div>
    @endforeach
</form>

@push('scripts.bot')
    <div id="actions">
        <div class="btn-group">
            <a id="Button1" class="btn btn-success" href="javascript:void(0);" onclick="saveForm('#form');">
                <i class="fa fa-save"></i> <span>@lang('global.save')</span>
            </a>
            <a id="Button2" class="btn btn-primary" href="javascript:void(0);" onclick="addDomain();" title="@lang('sMultisite::global.add_help')">
                <i class="fa fa-plus"></i> <span>@lang('global.add')</span>
            </a>
        </div>
    </div>

    <div class="hidden-elements" style="display: none;">
        <div class="row form-row form-element-input">
            <label class="control-label col-5 col-md-3 col-lg-2">
                <span>@lang('sMultisite::global.domain')</span>
                <i class="fa fa-question-circle" data-tooltip="@lang('sMultisite::global.domain_help')"></i>
            </label>
            <div class="col-7 col-md-9 col-lg-10">
                <input name="new-domains[]" value="" type="text" class="form-control" onchange="documentDirty=true;">
            </div>
        </div>
    </div>

    <script>
        // Add a new domain input field
        function addDomain() {
            const element = document.querySelector('.hidden-elements').innerHTML;
            document.getElementById('form').insertAdjacentHTML('beforeend', element);
            documentDirty = true;
        }

        // Toggle the value of a checkbox input
        function changestate(el) {
            el.value = el.value === '1' ? '0' : '1';
            documentDirty = true;
        }
    </script>
@endpush
