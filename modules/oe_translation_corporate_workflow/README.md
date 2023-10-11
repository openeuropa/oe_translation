# OpenEuropa Translation Corporate Editorial Workflow

This submodule provides integration with the corporate workflow from the [OpenEuropa Editorial](https://github.com/openeuropa/oe_editorial)
component.

***

### Requirements:
In order to install this module, you'll need to require the following dependencies in your project:

```
composer require openeuropa/oe_editorial
composer require drupal/entity_version
```

***

### Description

The rules for translating content with Corporate Editorial Workflow enabled apply for both local and remote translation
requests.
The content <strong>cannot</strong> be translated until it has been moved into <strong>Validated</strong> state.
For nodes having multiple versions available, meaning there is a <strong>Published</strong> version along with a
<strong>Validated</strong> draft, users can create new requests for each version. However, the translation will be
synchronized for the Published version of the node.
