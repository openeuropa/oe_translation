# OpenEuropa Translation Remote

This submodule allows site owners to add remote translation providers (see [OpenEuropa Translation ePoetry](modules/oe_translation_epoetry/README.md)
for the integration of the ePoetry service) for translating the content.

***

### Description
For each translation provider, it's required to provide an ID, a label, select one of the available plugins
(e.g. ePoetry) and provide its specific configuration.

On nodes of content types having the content translation enabled, the submodule alters the translation
<strong>Dashboard</strong> for adding the ongoing remote translation requests information such as its translator, the status
of the request and the revision ID, if any. For sending a new remote translation request, users need to
navigate to the <strong>Remote translations</strong> tab provided by the submodule and specify which translation provider
to be used and the target languages. The request holds this information along with its status and once available,
the translated data.
The translated data can be reviewed separately for each of the requested target languages and accepted/synchronised
on the node.
No new requests can be started while there is an active one.

***

### Usage
Grant the following permissions to the desired user roles to be able to use the remote translation system:
- translate any entity
- accept translation request
- sync translation request

***

### Testing
For testing purposes only, the test module `oe_translation_remote_test` can be enabled to provide dummy translation
providers. The <em><strong>Remote one</strong></em> plugin can be used for creating new remote translation requests,
and it will translate the content appending the language code of the chosen language for each field containing original
values.

***

### Development
In order to use the remote translation system, at least one remote translator provider plugin needs to be provided. To
create a new plugin, extend the `RemoteTranslationProviderBase` class.

<strong>OpenEuropa Translation</strong> component provides integration with the ePoetry service for remote translations.
Check the [oe_translation_epoetry](https://github.com/openeuropa/oe_translation/tree/2.x/modules/oe_translation_epoetry)
submodule for more information.

In order to enable the remote translation functionality on other entity types, the `oe_translation_translators`
additional configuration has to be set. For achieving that, implement `hook_entity_type_alter` and provide the required
translators configuration for local translation as follows:
```
$entity_type->set('oe_translation_translators', ['remote' => ['epoetry']);
```

Where `epoetry` is an example of a remote translator provider plugin ID.
