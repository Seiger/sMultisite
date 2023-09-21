<form id="form" name="form" method="post" action="{{sMultisite::route('sMultisite.update')}}" onsubmit="documentDirty=false;">
    <div id="domain-0">
        <div class="row form-row form-element-input">
            <label class="control-label col-5 col-md-4 col-lg-2">
                <span>@lang('sMultisite::global.default_domain')</span>
                <i class="fa fa-question-circle" data-tooltip="@lang('sMultisite::global.default_domain_help')"></i>
            </label>
            <div class="col-7 col-md-8 col-lg-10">
                <h4><span class="badge bg-seigerit">{{evo()->getConfig('site_name', 'New website')}}</span></h4>
            </div>
        </div>
        <div class="split my-3"></div>
    </div>
    @foreach(Seiger\sMultisite\Models\sMultisite::all() as $domain)
        <div id="domain-{{$domain->id}}">
            <div class="row form-row form-element-input">
                <label class="control-label col-5 col-md-4 col-lg-2">
                    <span>@lang('sMultisite::global.domain')</span>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sMultisite::global.domain_help')"></i>
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
            <div class="row form-row form-element-input">
                <label class="control-label col-5 col-md-4 col-lg-2">
                    <span>@lang('sMultisite::global.domain_on')</span>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sMultisite::global.domain_on_help')"></i>
                </label>
                <div class="col-7 col-md-8 col-lg-10">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" id="active_on_check" class="custom-control-input" name="active_on_check" value="" onchange="documentDirty=true;" onclick="changestate(document.form.active_on);" @if($domain->active == 1) checked @endif>
                        <label class="custom-control-label" for="active_on_check"></label>
                        <input type="hidden" id="active_on" name="domains[{{$domain->id}}][active]" value="{{$domain->active}}" onchange="documentDirty=true;">
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
                        <input type="checkbox" id="hide_from_tree_check" class="custom-control-input" name="hide_from_tree_check" data-validate="hideFromTreeCheck" data-text="<b>@lang('sMultisite::global.hide_from_tree')</b> @lang('sMultisite::global.must_be_dis-active')" onchange="documentDirty=true;" onclick="changestate(document.form.hide_from_tree);" @if($domain->hide_from_tree == 1) checked @endif>
                        <label class="custom-control-label" for="hide_from_tree_check"></label>
                        <input type="hidden" id="hide_from_tree" name="domains[{{$domain->id}}][hide_from_tree]" value="{{$domain->hide_from_tree}}" onchange="documentDirty=true;">
                    </div>
                </div>
            </div>
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
    <div class="hidden-elemens" style="display: none">
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
        function addDomain(){let element=document.querySelector('.hidden-elemens').innerHTML;document.getElementById('form').insertAdjacentHTML('beforeend',element);documentDirty=true;}
        function changestate(el){if(parseInt(el.value)===1){el.value=0}else{el.value=1;}documentDirty=true;}
    </script>
@endpush
