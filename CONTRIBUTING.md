# Contributing

## Developer Instructions

Before you make changes to the code, understand how the repo is organised, so any new pull requests can be accepted easily.

## Pre-requisites

#### ApiDoc.js is a Node.js module. So you need to install Node.js first and then install ApiDoc.js.

```
npm install -g apidoc
```

### PHP-CS-Fixer

PHP-CS-Fixer is a tool to automatically fix PHP coding standards issues. Follow the instructions at [PHP-CS-Fixer](https://cs.symfony.com/doc/installation.html) to install it.

## PCov

PCov is a PHP extension that provides code coverage information. Follow the instructions at [PCov](https://github.com/krakjoe/pcov/blob/develop/INSTALL.md) to install it.

With Apple Silicon, you may come across a few errors. Here are a few common ones.

- `"'pcre2.h' file not found"`

    ```
    # Install pcre2
    brew install pcre2
  
    # Get the pcre2 version from above
  
    # Create a symlink to the file (check and use correct version numbers on paths below)
    ln -s /opt/homebrew/Cellar/pcre2/10.42/include/pcre2.h /opt/homebrew/Cellar/php@8.1/8.1.16/include/php/ext/pcre/pcre2.h
    ```

- `ERROR: failed to mkdir /usr/local/Cellar/php/7.3.3/pecl/20180731`

	```
	pecl config-get ext_dir | pbcopy

	mkdir -p {paste clipboard value}
	```

## Overview

The project is organised as following.

- Api\Docs\DocBuilder
  
  `DocBuilder` is the main `container` of everything. This is where all references are held in memory until docs are completed.
  
    - Api\Docs\ApiCall
    
        You add an `ApiCall` object through `document()` function to `DocBuilder`. Think of each `ApiCall` as a REST endpoint.
        
        - Api\Docs\Param
        
            A `Param` is a parameter that you send to the REST endpoint.
            
## How it Works

1. When you call `php artisan generate:docs` command, it will set an environment variable called `DOCUMENTATION_MODE`.
2. Then it will call all API endpoints defined in `api.php` routes file.' 
3. When `document()` function is reached, it will find and register the `ApiCall` object for that function.
4. The `DOCUMENTATION_MODE` variable prevents executing the real logic of the function. Because of that, you should put `document()` function at the beginning of a method.

## Command Flow

- Get $user, where --user-id=3

```
$this->hitRoutesAndLoadDocs();
$this->createDocSourceFiles();
$this->createSwaggerJson('api');
$this->createSwaggerJson('postman');
```


## Pull Requests

- **[PSR-2 Coding Standard](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md)** - Check the code style with `composer check-style` and fix it with `composer fix-style`.

- **Create feature branches** - Don't commit to master branch.

- **One pull request per feature** - If you want to do more than one thing, send multiple pull requests.

- **Send coherent history** - Make sure each individual commit in your pull request is meaningful. If you had to make multiple intermediate commits while developing, please [squash them](http://www.git-scm.com/book/en/v2/Git-Tools-Rewriting-History#Changing-Multiple-Commit-Messages) before submitting.
