# sMultisite for Evolution CMS
![sMultisite](https://repository-images.githubusercontent.com/683186810/d71c1c9b-f143-4000-8125-5104eeee067b)
[![Latest Stable Version](https://img.shields.io/packagist/v/seiger/sMultisite?label=version)](https://packagist.org/packages/seiger/smultisite)
[![CMS Evolution](https://img.shields.io/badge/CMS-Evolution-brightgreen.svg)](https://github.com/evolution-cms/evolution)
![PHP version](https://img.shields.io/packagist/php-v/seiger/smultisite)
[![License](https://img.shields.io/packagist/l/seiger/smultisite)](https://packagist.org/packages/seiger/smultisite)
[![Issues](https://img.shields.io/github/issues/Seiger/sMultisite)](https://github.com/Seiger/sMultisite/issues)
[![Stars](https://img.shields.io/packagist/stars/Seiger/smultisite)](https://packagist.org/packages/seiger/smultisite)
[![Total Downloads](https://img.shields.io/packagist/dt/seiger/smultisite)](https://packagist.org/packages/seiger/smultisite)

# Welcome to sMultisite!

**sMultisite** collection of Multisite Tools for Evolution CMS.
The sMultisite package allows you to use one Evolution CMS 
installation for several independent sites managed from the same admin.

> [!IMPORTANT]
>
> To use additional domains for your site, they must be registered with your domain name registrar.

## Features

- [x] Management of several sites from one admin panel.
- [x] Adding new domains.
- [x] Authorization on all domains when logging into the admin panel.

## Requirements
- Evolution CMS **3.2.0+**
- PHP **8.2+**
- Composer **2.2+**
- One of: **MySQL 8.0+** / **MariaDB 10.5+** / **PostgreSQL 10+** / **SQLite 3.25+**

## Install by artisan package installer

Go to You /core/ folder:

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
php artisan vendor:publish --provider="Seiger\sMultisite\sMultisiteServiceProvider"
```

```console
php artisan migrate
```

[See full documentation here](https://seiger.github.io/sMultisite/)