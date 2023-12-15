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