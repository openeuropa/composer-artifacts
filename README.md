# Composer Artifacts
[![Build Status](https://drone.fpfis.eu/api/badges/openeuropa/composer-artifacts/status.svg?branch=master)](https://drone.fpfis.eu/openeuropa/composer-artifacts)
[![Packagist](https://img.shields.io/packagist/v/openeuropa/composer-artifacts.svg)](https://packagist.org/packages/openeuropa/composer-artifacts)

Composer plugin that allows to download a specified artifact instead of the default `dist` URL, this allows
to download all artifact dependencies too. 

## Usage

In your `extra` section add the following:

```json
{
    "require": {
        "foo/bar": "^0.1.0",
        "openeuropa/composer-artifacts": "*"
    },    
    "extra": {
        "artifacts": {
            "foo/bar": {
              "dist": {
                "url": "https://github.com/{name}/releases/download/{version}/{project-name}-{version}.tar.gz",
                "type": "tar"
              }
            }
        }
    }
}
```

This will fetch `dist` content from:

```
https://github.com/foo/bar/releases/download/0.1.0/bar-0.1.0.tar.gz"
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

### Using Docker Compose

The setup procedure can be simplified by using Docker Compose.

Copy docker-compose.yml.dist into docker-compose.yml.

You can make any alterations you need for your local Docker setup. However, the defaults should be enough to set the project up.

Run:

```
$ docker-compose up -d
```

Then:

```
$ docker-compose exec web composer install
```
