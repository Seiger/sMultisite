---
title: Use in Blade
sidebar_label: Use in Blade
sidebar_position: 3
---

You can use different templates for each domain.
But if your templates are identical, you can give them features by dynamically replacing design elements.

## Show all domains

Use this example code for show all active domains:

```php
@foreach(sMultisite::domains() as $domain)
    <a href="{{$domain['link']}}" class="@if($domain['is_current']) active @endif">
        <img src="/img/logo-{{$domain['key']}}.svg" alt="" />
        <span>{{$domain['site_name']}}</span>
    </a>
@endforeach
```

This sMultisite::domains() method returns the list of active domains as an array:

```php
array:2 [▼
    "default" => array:4 [▼
        "key" => "default"
        "link" => "https://default.example.com"
        "site_name" => "Default Example Website"
        "is_current" => true
    ]
    "example" => array:4 [▼
        "key" => "example"
        "link" => "https://example.example.com"
        "site_name" => "Example Example Website"
        "is_current" => false
    ]
]
```

In this array:
 * ```key``` is a unique key for domain;
 * ```link``` is a Domain host with protocol. Protocol configure in **Admin Panel -> System configuration -> Site -> Server type**;
 * ```site_name``` is a Site name setting;
 * ```is_current``` this parameter is true if Domain host equal current server hostname;

## Partials (Chunks)

```php
@include('partials.'.evo()->getConfig('site_key').'.header_nav')
```

## Main logo

```php
@if(evo()->documentIdentifier == evo()->getConfig('site_start'))
    <span class="header__logo">
@else
    <a href="{{url(evo()->getConfig('site_start'), '', '', 'full')}}" class="header__logo">
@endif
    <img src="/img/main-logo-{{evo()->getConfig('site_key')}}.svg" alt="{{evo()->getConfig('site_name')}} logo" />
@if(evo()->documentIdentifier == evo()->getConfig('site_start'))
    </span>
@else
    </a>
@endif
```
