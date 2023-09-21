# sMultisite for Evolution CMS 3
![sMultisite](https://repository-images.githubusercontent.com/683186810/d71c1c9b-f143-4000-8125-5104eeee067b)
[![Latest Stable Version](https://img.shields.io/packagist/v/seiger/sMultisite?label=version)](https://packagist.org/packages/seiger/smultisite)
[![CMS Evolution](https://img.shields.io/badge/CMS-Evolution-brightgreen.svg)](https://github.com/evolution-cms/evolution)
![PHP version](https://img.shields.io/packagist/php-v/seiger/smultisite)
[![License](https://img.shields.io/packagist/l/seiger/smultisite)](https://packagist.org/packages/seiger/smultisite)
[![Issues](https://img.shields.io/github/issues/Seiger/sMultisite)](https://github.com/Seiger/sMultisite/issues)
[![Stars](https://img.shields.io/packagist/stars/Seiger/smultisite)](https://packagist.org/packages/seiger/smultisite)
[![Total Downloads](https://img.shields.io/packagist/dt/seiger/smultisite)](https://packagist.org/packages/seiger/smultisite)

**sMultisite** Collection of Multisite Tools for Evolution CMS.

## Install by artisan package installer

Go to You /core/ folder:

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
