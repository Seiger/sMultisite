---
title: Getting started
sidebar_label: Getting started
sidebar_position: 2
---

## Requirements
- Evolution CMS **3.2.0+**
- PHP **8.2+**
- Composer **2.2+**
- One of: **MySQL 8.0+** / **MariaDB 10.5+** / **PostgreSQL 10+** / **SQLite 3.25+**

## Where to find the module
Manager → **Tools → sMultisite**. You’ll see tabs for Configure.

## Install by artisan package

Go to You /core/ folder

```console
cd core
```

```console
composer update
```

Run php artisan commands

```console
php artisan package:installrequire seiger/smultisite "*"
```

```console
php artisan vendor:publish --tag="sMultisite"
```

```console
php artisan migrate
```

## Available values

```php
$currentDomainKey = evo()->getConfig('site_key');
$currentDomainName = evo()->getConfig('site_name');
$currentDomainHomePage = evo()->getConfig('site_start');
$currentDomainNotFoundPage = evo()->getConfig('error_page');
$currentDomainUnauthorizedPage = evo()->getConfig('unauthorized_page');
```

## Configuration in frontend

Show all domains in Blade layout:

```php
@foreach(sMultisite::domains() as $domain)
    <a href="{{$domain['link']}}" class="@if($domain['is_current']) active @endif">
        <img src="/img/logo-{{$domain['key']}}.svg" alt="" />
        <span>{{$domain['site_name']}}</span>
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
    "example" => array:4 [▼
        "key" => "example"
        "link" => "https://example.example.com"
        "site_name" => "Example Example Website"
        "is_current" => false
    ]
]
```

More examples in **[Use in Blade](./use-in-blade.md)** page.

## Extra

If you write your own code that can integrate with the sMultisite plugin, you can check the presence of this module in the system through a configuration variable.

```php
if (evo()->getConfig('check_sMultisite', false)) {
    // You code
}
```

If the plugin is installed, the result of ```evo()->getConfig('check_sMultisite', false)``` will always be ```true```. Otherwise, you will get an ```false```.
