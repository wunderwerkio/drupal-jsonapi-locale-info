# JSON:API Locale Info

[![Lint & Test](https://github.com/wunderwerkio/drupal-jsonapi-locale-info/actions/workflows/main.yml/badge.svg)](https://github.com/wunderwerkio/drupal-jsonapi-locale-info/actions/workflows/main.yml)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=wunderwerkio_drupal-jsonapi-locale-info&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=wunderwerkio_drupal-jsonapi-locale-info)

This module adds localization metadata to JSON:API resources
that support translation and have path aliases enabled.

In particular, the following data is added in the `media` section in
the API response:

- `localeInfo`
  Is an array of languages this resource is also available (but not including the current language).
  Each array item contains the langcode and the localized path for that language.

## Example Response

Given the following settings:

- Default locale: `en`
- Additional languages: `de`

When requesting a `node` with the default language, the output is as follows:

```json
{
  "jsonapi": {
    // ...
  },
  "data": {
    "attributes": {
      // ...
      "path": {
        "alias": "/my-node",
      }
    },
    // ...
    "meta": {
      "localeInfo": [
        {
          "langcode": "de",
          "path": "/meine-node"
        }
      ]
    }
  }
}
```
