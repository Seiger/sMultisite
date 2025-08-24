---
title: Використання в Blade
sidebar_label: Використання в Blade
sidebar_position: 3
---

Ви можете використовувати різні шаблони для кожного домену.
Але якщо ваші шаблони ідентичні, ви можете надати їм функцій, динамічно замінюючи елементи дизайну.

## Показати всі домени

Використайте цей приклад коду для відображення всіх активних доменів:

```php
@foreach(sMultisite::domains() as $domain)
    <a href="{{$domain['link']}}" class="@if($domain['is_current']) active @endif">
        <img src="/img/logo-{{$domain['key']}}.svg" alt="" />
        <span>{{$domain['site_name']}}</span>
    </a>
@endforeach
```

Цей метод sMultisite::domains() повертає список активних доменів у вигляді масиву:

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

У цьому масиві:
 * ```key``` є унікальним ключем для домену;
 * ```link``` є хостом домену з протоколом. Протокол налаштовується в **Панель адміністратора -> Конфігурація системи -> Сайт -> Тип сервера**;
 * ```site_name``` це налаштування назви сайту;
 * ```is_current``` Цей параметр має значення true, якщо хост домену дорівнює поточному імені хоста сервера;

## Partials (Chunks)

```php
@include('partials.'.evo()->getConfig('site_key').'.header_nav')
```

## Головний логотип

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
