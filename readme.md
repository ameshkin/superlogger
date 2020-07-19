# Simple Logger

For years I've used this script for logging when I'm not using PHP DEBUG.  

Super Logger is a powerful yet simple class for logging errors to log files, custom log files or the browser.

I make use of ENV files so you can have settings for different environments.  You can also use KRUMO or JSON to output your errors.

[Krumo](https://packagist.org/packages/mmucklo/krumo) is also an optional dependency which can be used to output arrays and objects in a more readable format.

This package works well with PIMP MY LOG!

## Installation

Installation is easy using composer.

```
composer require ameshkin/superlogger:dev-master
```

Place this into your `composer.json`:

``` json
{
    "require": {
        "ameshkin/superlogger": "dev-master"
    }
}
```

## Basic Usage

``` php
<?php

require 'vendor/autoload.php';

$logger = new Ameshkin\Logger\Log(__DIR__);

$array = ['array'=>'array value', 'array2'=>'array2 value'];
$obj = (object) array('object' => 'object value');

$logger->log("String", 0);
$logger->log($obj);

```

### Results

```

[2020-07-17 13:17:28.838916] [Emergency] String

[2020-07-17 1:59:51.381232] [Emergency] stdClass Object
(
    [object] => object value
)

```


# ENV Files and Config

The config for this class is controlled by an ENV file placed wherever you like it.   The class only needs to know the directory of your env file as an argument. An example file is in src/example.env.

Details about each config option is in the ENV file.

## Why use Super Logger?

You probably already use X-DEBUG which is great but sometimes it makes since to use a logging program. Specially when you don't need constant breakpoints and simply need to see the output of a script.


### Planned Improvements
```
TODO: Colors for emergencies, notice, on browser and terminal
TODO: add var_dump
TODO: Remove <br> and <pre> from output to error logs
TODO: ADD PSR logging threshold back
```
