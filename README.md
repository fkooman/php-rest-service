# Notes
**DEPRECATED**: This library is deprecated and replaced by separate libraries.
If you used any of the functionality provided by this library please switch to
the separate libraries, and preferably depend on them using 
[Composer](http://getcomposer.org).

You could also switch to [Silex](http://silex.sensiolabs.org/) or 
[Slim](http://www.slimframework.com/) if you prefer small HTTP/REST libraries. 
For most use cases Silex and Slim are better choices for your application due 
to them being better tested and more widely used!

## HTTP and REST library
Switch to [fkooman/php-lib-rest](https://github.com/fkooman/php-lib-rest). For 
the `OutgoingHttpRequest` please switch to [Guzzle](http://guzzlephp.org/).

## JSON
Switch to [fkooman/php-lib-json](https://github.com/fkooman/php-lib-json).

## Configuration
Switch to [fkooman/php-lib-config](https://github.com/fkooman/php-lib-config).

## Logging
Switch to [Monolog](https://github.com/Seldaek/monolog).

# Introduction
This is a PHP library written to make it easy to develop REST applications. 

# Features
The library has the following features:
* Wrapper HTTP request and response class to make it very easy to test your
  applications
* RESTful router support
* Config class to handle `ini`-files;
* Logger class to implement logging to file or to mail;

Furthermore extensive tests are available written in PHPUnit.

# Tests
You can run the PHPUnit tests if PHPUnit is installed:

    $ phpunit tests/

# Examples
Some simple sample applications can be found in the `examples/` directory. 
Please refer to them to see how to use this library. The examples should work
"as is" when placed in a directory reachable through a web server.

# License
Licensed under the Apache License, Version 2.0;

   http://www.apache.org/licenses/LICENSE-2.0
