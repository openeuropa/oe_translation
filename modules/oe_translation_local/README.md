# OpenEuropa Translation Local

This submodule allows privileged users to translate content locally.

***

### Description
It alters the translation <strong>Dashboard</strong> to add information regarding open local translation requests.
Each request contains information such as the target language, status, node title, revision ID and
if it's the default one, node version and moderation state.
For translating the content, users need to navigate to the <strong>Local translations</strong> tab provided
by the submodule and create a new request for the desired available language.

When translating content, the translation fields are prefilled with the original value and the user
can see each value for which field belongs to. Each request can be saved as draft and edited any time either
from the <strong>Dashboard</strong> or from the <strong>Local translations</strong>. Once the translation
is finalised, it can be saved and synchronized with the node.

***

### Usage
Grant the following permissions to the desired user roles to be able to use the local translation system:
- translate any entity
- accept translation request
- sync translation request

***

### Development
By using <strong>OpenEuropa Translation</strong> component, the <strong>Node entity type</strong> will automatically be using the
local translation system provided in `oe_translation_local` submodule.
- In order to enable the local translation functionality on other entity types, the `oe_translation_translators`
additional configuration has to be set. For achieving that, implement `hook_entity_type_alter` and provide the required
translators configuration for local translation as follows:
```
$entity_type->set('oe_translation_translators', ['local' => TRUE]);
```
- If the Dashboard overview needs to be altered, this can be done be subscribing to the `TranslationLocalControllerAlterEvent`
event.
