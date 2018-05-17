# Composer Artifacts

Composer plugin that allows to download a specified artifact instead of the default package `dist`.

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
                "url": "https://github.com/my/project/releases/download/0.1.0/my-project-artifact-0.1.0.tar.gz",
                "type": "tar"
              }
            }
        },
    }
}
```
