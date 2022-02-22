# Composer Artifacts
[![Build Status](https://drone.fpfis.eu/api/badges/openeuropa/composer-artifacts/status.svg?branch=master)](https://drone.fpfis.eu/openeuropa/composer-artifacts)
[![Packagist](https://img.shields.io/packagist/v/openeuropa/composer-artifacts.svg)](https://packagist.org/packages/openeuropa/composer-artifacts)

Composer plugin that allows to download a specified artifact instead of the default `dist` URL, this allows
to download all artifact dependencies too.

**Note:** version 2.x of Composer Artifacts only works with Composer 2.x and only supports ZIP artifacts.

**Table of contents:**

- [Installation](#installation)
- [Usage](#usage)
- [Development setup](#development-setup)
- [Contributing](#contributing)
- [Versioning](#versioning)

## Installation

The recommended way of installing the plugin is via [Composer][2].

```bash
composer require openeuropa/composer-artifacts
```

## Usage

Edit the composer.json file and add the following in your `extra` section:

```json
{
    "extra": {
        "artifacts": {
            "foo/bar": {
              "dist": {
                "url": "https://github.com/{name}/releases/download/{version}/{project-name}-{version}.zip",
                "type": "zip"
              }
            }
        }
    }
}
```

This will fetch `dist` content from:

```
https://github.com/foo/bar/releases/download/0.1.0/bar-0.1.0.zip"
```

Valid `type` values are `tar` and `zip` while available URL replacement tokens are:

- `{name}`: the full package's name without version info, e.g. `foo/bar`
- `{vendor-name}`: just the vendor name, e.g. `foo`
- `{project-name}`: just the project name, e.g. `bar`
- `{pretty-version}`: the pretty (i.e. non-normalized) version string of this package, e.g. `0.1.0`
- `{version}`: the full version of this package, e.g. `0.1.0.0`
- `{stability}`: the stability of this package, e.g. `dev`, `alpha`, `beta`, `RC` or `stable`
- `{type}`: the package type, e.g. `library`
- `{checksum}`: the SHA1 checksum for the distribution archive of this version

## Development setup

### Using Docker Compose

Alternatively, you can build a development environment using [Docker](https://www.docker.com/get-docker) and 
[Docker Compose](https://docs.docker.com/compose/) with the provided configuration.

Docker provides the necessary services and tools needed to get the tests running, 
regardless of your local host configuration.

#### Requirements:

- [Docker](https://www.docker.com/get-docker)
- [Docker Compose](https://docs.docker.com/compose/)

#### Configuration

By default, Docker Compose reads two files, a `docker-compose.yml` and an optional `docker-compose.override.yml` file.
By convention, the `docker-compose.yml` contains your base configuration and it's provided by default.
The override file, as its name implies, can contain configuration overrides for existing services or entirely new 
services.
If a service is defined in both files, Docker Compose merges the configurations.

Find more information on Docker Compose extension mechanism on [the official Docker Compose documentation](https://docs.docker.com/compose/extends/).

#### Usage

To start, run:

```bash
docker-compose up
```

It's advised to not daemonize `docker-compose` so you can turn it off (`CTRL+C`) quickly when you're done working.
However, if you'd like to daemonize it, you have to add the flag `-d`:

```bash
docker-compose up -d
```

Then:

```bash
docker-compose exec web composer install
```

#### Running the tests

To run the grumphp checks:

```bash
docker-compose exec web ./vendor/bin/grumphp run
```

To run the phpunit tests:

```bash
docker-compose exec web ./vendor/bin/phpunit
```

#### Step debugging

To enable step debugging from the command line, pass the `XDEBUG_SESSION` environment variable with any value to
the container:

```bash
docker-compose exec -e XDEBUG_SESSION=1 web <your command>
```

Please note that, starting from XDebug 3, a connection error message will be outputted in the console if the variable is
set but your client is not listening for debugging connections. The error message will cause false negatives for PHPUnit
tests.

To initiate step debugging from the browser, set the correct cookie using a browser extension or a bookmarklet
like the ones generated at https://www.jetbrains.com/phpstorm/marklets/.

## Contributing

Please read [the full documentation](https://github.com/openeuropa/openeuropa) for details on our code of conduct,
and the process for submitting pull requests to us.

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the available versions, 
see the [tags on this repository](https://github.com/openeuropa/composer-artifacts/tags).

[2]: https://www.drupal.org/docs/develop/using-composer/using-composer-to-manage-drupal-site-dependencies#managing-contributed
