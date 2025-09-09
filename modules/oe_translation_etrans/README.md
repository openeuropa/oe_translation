# OpenEuropa Translation eTrans

Provides the eTrans translation plugin to allow users to translate content via the DGT etrans API.

## Configuration

You need 3 authentication values to connect the API and those need to arrive in the settings.php file:

```
$settings["etrans.service_url"] = getenv('ETRANS_SERVICE_URL');
$settings["etrans.application_name"] = getenv('ETRANS_APPLICATION_NAME');
$settings["etrans.password"] = getenv('ETRANS_PASSWORD');
```

The service URL is in the repository in the runner.yml.dist file but the other two you need to request from DGT.

## Setup

Once configured, you can create a translator using the eTrans plugin and you can make requests to DGT.

There is also a Node boolean field storage installed with the module, called `is_translation`. Add that field to any node type you want to flag as etranslation automatically. If you use it on other entity types, add such a field, named the same way, onto that as well. Ensure the field is marked as translatable.

## How it works

After you sent the request, DGT sends back notifications with the delivery of the translations per each language. When these arrive, a queue item gets created, so you will need to have cron setup.

The queue processes the items and puts the translations into the translation request, ready for review, just like with other remote translators like ePoetry. From there, you accept/sync onto the node.
