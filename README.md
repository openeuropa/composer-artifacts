# Composer Artifacts

[![Build Status](https://drone.fpfis.eu/api/badges/openeuropa/composer-artifacts/status.svg?branch=master)](https://drone.fpfis.eu/openeuropa/composer-artifacts)

Composer plugin that allows to download a specified artifact instead of the default `dist` URL, this allows
to download all artifact dependencies too. 

## Usage

In your `extra` section add the following:

```json
{
    "require": {
        "foo/bar": "0.1.0",
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

Valid `type` values are `tar` and `zip`

Available tokens are:

- `{name}`: the full package's name without version info, e.g. `foo/bar`
- `{vendor-name}`: just the vendor name, e.g. `foo`
- `{project-name}`: just the project name, e.g. `bar`
- `{pretty-version}`: the pretty (i.e. non-normalized) version string of this package, e.g. `0.1.0`
- `{version}`: the full version of this package, e.g. `0.1.0.0`
- `{stability}`: the stability of this package, e.g. `dev`, `alpha`, `beta`, `RC` or `stable`
- `{type}`: the package type, e.g. `library`
- `{checksum}`: the SHA1 checksum for the distribution archive of this version

