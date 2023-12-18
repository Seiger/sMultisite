---
layout: page
title: Use in Blade
description: Use sMultisite code in Blade layouts
permalink: /use-in-blade/
---

You can use different templates for each domain.
But if your templates are identical, you can give them features by dynamically replacing design elements.

## Show all domains

Use this example code for show all active domains:

```php
@foreach(sMultisite::domains() as $domain)
    <a href="{% raw %}{{$domain['link']}}{% endraw %}" class="@if($domain['is_current']) active @endif">
        <img src="/img/logo-{% raw %}{{$domain['key']}}{% endraw %}.svg" alt="" />
        <span>{% raw %}{{$domain['site_name']}}{% endraw %}</span>
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
    "nordic" => array:4 [▼
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
    <a href="{% raw %}{{url(evo()->getConfig('site_start'), '', '', 'full')}}{% endraw %}" class="header__logo">
@endif
    <img src="/img/main-logo-{% raw %}{{evo()->getConfig('site_key')}}{% endraw %}.svg" alt="{% raw %}{{evo()->getConfig('site_name')}}{% endraw %} logo" />
@if(evo()->documentIdentifier == evo()->getConfig('site_start'))
    </span>
@else
    </a>
@endif
```
