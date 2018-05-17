# Composer Artifacts

Composer plugin that allows to download a specified artifact instead of the default package `dist` while downloading
all the required project dependencies. 

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
        },
    }
}
```

Available tokens are:

* version
* name
* stability
* type
* checksum
