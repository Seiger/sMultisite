<div class="max-w-7xl mx-auto py-3 px-6" x-data="sMultisite.sPinner('domain{{ucfirst($item->key)}}')">
    <div class="s-meta-block-head">
        <span @click="toggle()" class="s-meta-block-btn bg-[var(--brand-accent)]" @if(trim($domain->site_color ?? '')) @style(['background-color: ' . $domain->site_color]) @endif>
            <div class="flex items-center gap-2">
                <svg data-lucide="link-2" class="w-5 h-5 text-[var(--brand-color)]"></svg>
                <span class="font-semibold text-base text-slate-700 darkness:text-slate-200">{{$item->site_name??''}}</span>
                <sup><b>{{$domain->key}}</b></sup>
            </div>
            <svg :class="open ? 'rotate-180' : ''" data-lucide="chevron-down" class="w-4 h-4 transition-transform text-slate-500"></svg>
        </span>
        <div x-ref="content" x-bind:style="open ? 'min-height:' + $refs.content.scrollHeight + 'px' : 'max-height: 0px'" class="s-meta-block-content">
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-12 gap-x-2 gap-y-4 items-start">
                    <label class="col-span-12 sm:col-span-2 text-sm font-medium text-slate-700 darkness:text-slate-300 pt-2 pr-2">
                        @lang('sMultisite::global.domain')
                    </label>
                    <div class="col-span-12 sm:col-span-10">
                        <input name="domains[{{$domain->id}}][domain]" value="{{$domain->domain}}" data-validate="textNoEmpty" data-text="<b>{{$domain->key}} - @lang('sMultisite::global.domain')</b> @lang('sMultisite::global.empty_field')" placeholder="example.com" type="text" class="w-full rounded-md border border-slate-300 darkness:border-slate-600 bg-white darkness:bg-slate-800 text-slate-800 darkness:text-white px-3 py-2 focus:ring-2 focus:ring-blue-500" onchange="documentDirty=true;">
                        <p class="text-xs text-slate-500 darkness:text-slate-400 mt-1">@lang('sMultisite::global.domain_help')</p>
                    </div>
                </div>
                <div class="grid grid-cols-12 gap-x-2 gap-y-4 items-start">
                    <label class="col-span-12 sm:col-span-2 text-sm font-medium text-slate-700 darkness:text-slate-300 pt-2 pr-2">
                        @lang('global.sitename_title')
                    </label>
                    <div class="col-span-12 sm:col-span-10">
                        <input name="domains[{{$domain->id}}][site_name]" value="{{$domain->site_name}}" data-validate="textNoEmpty" data-text="<b>{{$domain->key}} - @lang('global.sitename_title')</b> @lang('sMultisite::global.empty_field')" placeholder="Evolution CMS website" type="text" class="w-full rounded-md border border-slate-300 darkness:border-slate-600 bg-white darkness:bg-slate-800 text-slate-800 darkness:text-white px-3 py-2 focus:ring-2 focus:ring-blue-500" onchange="documentDirty=true;">
                        <p class="text-xs text-slate-500 darkness:text-slate-400 mt-1">@lang('global.sitename_message')</p>
                    </div>
                </div>
                <div class="grid grid-cols-12 gap-x-2 gap-y-4 items-start">
                    <label class="col-span-12 sm:col-span-2 text-sm font-medium text-slate-700 darkness:text-slate-300 pt-2 pr-2">
                        @lang('global.sitestart_title')
                    </label>
                    <div class="col-span-12 sm:col-span-10">
                        <input name="domains[{{$domain->id}}][site_start]" value="{{$domain->site_start}}" type="text" class="w-full rounded-md border border-slate-300 darkness:border-slate-600 bg-white darkness:bg-slate-800 text-slate-800 darkness:text-white px-3 py-2 focus:ring-2 focus:ring-blue-500" onchange="documentDirty=true;">
                        <p class="text-xs text-slate-500 darkness:text-slate-400 mt-1">@lang('global.sitestart_message')</p>
                    </div>
                </div>
                <div class="grid grid-cols-12 gap-x-2 gap-y-4 items-start">
                    <label class="col-span-12 sm:col-span-2 text-sm font-medium text-slate-700 darkness:text-slate-300 pt-2 pr-2">
                        @lang('global.errorpage_title')
                    </label>
                    <div class="col-span-12 sm:col-span-10">
                        <input name="domains[{{$domain->id}}][error_page]" value="{{$domain->error_page}}" type="text"  class="w-full rounded-md border border-slate-300 darkness:border-slate-600 bg-white darkness:bg-slate-800 text-slate-800 darkness:text-white px-3 py-2 focus:ring-2 focus:ring-blue-500" onchange="documentDirty=true;">
                        <p class="text-xs text-slate-500 darkness:text-slate-400 mt-1">@lang('global.errorpage_message')</p>
                    </div>
                </div>
                <div class="grid grid-cols-12 gap-x-2 gap-y-4 items-start">
                    <label class="col-span-12 sm:col-span-2 text-sm font-medium text-slate-700 darkness:text-slate-300 pt-2 pr-2">
                        @lang('global.unauthorizedpage_title')
                    </label>
                    <div class="col-span-12 sm:col-span-10">
                        <input name="domains[{{$domain->id}}][unauthorized_page]" value="{{$domain->unauthorized_page}}" type="text"  class="w-full rounded-md border border-slate-300 darkness:border-slate-600 bg-white darkness:bg-slate-800 text-slate-800 darkness:text-white px-3 py-2 focus:ring-2 focus:ring-blue-500" onchange="documentDirty=true;">
                        <p class="text-xs text-slate-500 darkness:text-slate-400 mt-1">@lang('global.unauthorizedpage_message')</p>
                    </div>
                </div>
                <div class="grid grid-cols-12 gap-x-2 gap-y-4 items-start">
                    <label class="col-span-12 sm:col-span-2 text-sm font-medium text-slate-700 darkness:text-slate-300 pt-2 pr-2">
                        @lang('sMultisite::global.site_color')
                    </label>
                    <div class="col-span-12 sm:col-span-10">
                        <input name="domains[{{$domain->id}}][site_color]" value="{{$domain->site_color}}" type="text"  class="w-full rounded-md border border-slate-300 darkness:border-slate-600 bg-white darkness:bg-slate-800 text-slate-800 darkness:text-white px-3 py-2 focus:ring-2 focus:ring-blue-500" onchange="documentDirty=true;">
                        <p class="text-xs text-slate-500 darkness:text-slate-400 mt-1">@lang('sMultisite::global.hex_color_may_be_used_some_modules')</p>
                    </div>
                </div>
                @if($domain->key == 'default')
                    <input name="domains[{{$domain->id}}][active]" value="1" type="hidden">
                @else
                    <div class="grid grid-cols-12 gap-x-2 gap-y-4 items-start">
                        <label class="col-span-12 sm:col-span-2 text-sm font-medium text-slate-700 darkness:text-slate-300 pt-2 pr-2">
                            @lang('sMultisite::global.domain_on')
                        </label>
                        <div class="col-span-12 sm:col-span-10">
                            <label class="inline-flex items-center me-5 cursor-pointer">
                                <input type="checkbox" class="sr-only peer" data-target="active_{{$domain->id}}" {{$domain->active == 1 ? 'checked' : ''}}>
                                <div class="s-toggle-slider"></div>
                                <input id="active_{{$domain->id}}" name="domains[{{$domain->id}}][active]" value="{{$domain->active}}" type="hidden"  onchange="documentDirty=true;">
                            </label>
                            <p class="text-xs text-slate-500 darkness:text-slate-400 mt-1">
                                @lang('sMultisite::global.domain_on_help')
                            </p>
                        </div>
                    </div>
                    <div class="grid grid-cols-12 gap-x-2 gap-y-4 items-start">
                        <label class="col-span-12 sm:col-span-2 text-sm font-medium text-slate-700 darkness:text-slate-300 pt-2 pr-2">
                            @lang('sMultisite::global.hide_from_tree')
                        </label>
                        <div class="col-span-12 sm:col-span-10">
                            <label class="inline-flex items-center me-5 cursor-pointer">
                                <input type="checkbox" class="sr-only peer" data-target="hide_from_tree_{{$domain->id}}" {{$domain->hide_from_tree == 1 ? 'checked' : '' }}>
                                <div class="s-toggle-slider"></div>
                                <input id="hide_from_tree_{{$domain->id}}" name="domains[{{$domain->id}}][hide_from_tree]" value="{{$domain->hide_from_tree}}" data-validate="hideFromTreeCheck" data-text="<b>{{$domain->key}} - @lang('sMultisite::global.hide_from_tree')</b> @lang('sMultisite::global.must_be_dis-active')" type="hidden" onchange="documentDirty=true;">
                            </label>
                            <p class="text-xs text-slate-500 darkness:text-slate-400 mt-1">
                                @lang('sMultisite::global.hide_from_tree_help')
                            </p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@push('scripts.bot')
    <script>
        document.addEventListener('DOMContentLoaded', () => {

            function getContrastColor(r, g, b) {
                const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                return luminance > 0.5 ? '#0f172a' : '#ffffff';
            }

            function applyContrast(el) {
                const bg = getComputedStyle(el).backgroundColor || '';
                const m = bg.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/i);

                if (!m) return;

                const r = parseInt(m[1], 10);
                const g = parseInt(m[2], 10);
                const b = parseInt(m[3], 10);

                el.style.color = getContrastColor(r, g, b);
            }

            function run() {
                document.querySelectorAll('.s-meta-block-btn').forEach(applyContrast);
            }

            run();

            const observer = new MutationObserver(run);
            observer.observe(document.body, {
                subtree: true,
                childList: true,
                attributes: true,
                attributeFilter: ['style']
            });
        });
    </script>
@endpush