---
layout: page
title: Getting started
description: Getting started with sMultisite
permalink: /getting-started/
---

## Install by artisan package

Go to You /core/ folder

```console
cd core
```

Run php artisan commands

```console
php artisan package:installrequire seiger/smultisite "*"
```

```console
php artisan vendor:publish --provider="Seiger\sMultisite\sMultisiteServiceProvider"
```

```console
php artisan migrate
```

## Configuration in backend

Plugin settings are located at **Admin Panel -> Tools -> sMultisite**.
{% include figure.html path="assets/img/smultisitetools.jpg" %}

## Configuration in frontend

Show all domains in Blade layout:

```php
@foreach(sMultisite::domains() as $domain)
    <a href="{% raw %}{{$domain['link']}}{% endraw %}" class="@if($domain['is_current']) active @endif">
        <img src="/img/logo-{% raw %}{{$domain['key']}}{% endraw %}.svg" alt="" />
        <span>{% raw %}{{$domain['site_name']}}{% endraw %}</span>
    </a>
@endforeach
```

This ```sMultisite::domains()``` method returns the list of active domains as an array:

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

More examples in **Use in Blade** page

[Use in Blade]({{ site.baseurl }}/use-in-blade/){: .btn .btn-sky}
