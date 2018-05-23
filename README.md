# Composer Artifacts

[![Build Status](https://drone.fpfis.eu/api/badges/openeuropa/composer-artifacts/status.svg?branch=master)](https://drone.fpfis.eu/openeuropa/composer-artifacts)

Composer plugin that allows to download a specified artifact instead of the default `dist` URL, this allows
to download all artifact dependencies too. 

## Usage

In your `extra` section add the following:

```json
{
    "require": {
        "my/project": "0.1.0",
        "openeuropa/composer-artifacts": "*"
    },    
    "extra": {
        "artifacts": {
            "my/project": {
              "dist": {
                "url": "https://github.com/my/project/releases/download/{version}/my-project-artifact-{version}.tar.gz",
                "type": "tar"
              }
            }
        }
    }
}
```

Available tokens are:

* `version`
* `name`
* `stability`
* `type`
* `checksum`
