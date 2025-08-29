---
title: Початок роботи
sidebar_label: Початок роботи
sidebar_position: 2
---

## Вимоги
- Evolution CMS **3.2.0+**
- PHP **8.2+**
- Composer **2.2+**
- Одна з: **MySQL 8.0+** / **MariaDB 10.5+** / **PostgreSQL 10+** / **SQLite 3.25+**

## Де знайти модуль
Менеджер → **Інструменти → Мультисайт**. Ви побачите вкладку для налаштування.

## Встановлення пакета за допомогою artisan

Перейдіть до директорії /core/

```console
cd core
```

```console
composer update
```

Виконайте команди php artisan

```console
php artisan package:installrequire seiger/smultisite "*"
```

```console
php artisan vendor:publish --tag="sMultitisite"
```

```console
php artisan migrate
```

## Доступні значення

```php
$currentDomainKey = evo()->getConfig('site_key');
$currentDomainName = evo()->getConfig('site_name');
$currentDomainHomePage = evo()->getConfig('site_start');
$currentDomainNotFoundPage = evo()->getConfig('error_page');
$currentDomainUnauthorizedPage = evo()->getConfig('неавторизована_сторінка');
```

## Конфігурація у фронтенді

Показати всі домени у макеті Blade:

```php
@foreach(sMultitisite::domains() as $domain)
    <a href="{{$domain['link']}}" class="@if($domain['is_current']) active @endif">
        <img src="/img/logo-{{$domain['key']}}.svg" alt="" />
        <span>{{$domain['site_name']}}<span>
    </a>
@endforeach
```

Цей метод ```sMultitisite::domains()``` повертає список активних доменів у вигляді масиву:

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

Більше прикладів на сторінці **[Використання в Blade](./use-in-blade.md)**.

## Додатково

Якщо ви напишете власний код, який може інтегруватися з плагіном sMultisite, ви можете перевірити наявність цього модуля в системі через змінну конфігурації.

```php
if (evo()->getConfig('check_sMultisite', false)) {
// Ваш код
}
```

Якщо плагін встановлено, результат ```evo()->getConfig('check_sMultisite', false)``` завжди буде ```true```. В іншому випадку ви отримаєте ```false``` значення.
