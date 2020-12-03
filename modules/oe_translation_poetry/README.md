# OpenEuropa Translation Poetry

This submodule integrates with the DGT Poetry system to provide translations services.

## Requirements

If you want to use this submodule, you need to add the following packages to your site:

* ec-europa/oe-poetry-client:dev-master

## Setup

### DGT request

To integrate with Poetry, a request has to made to register the site with DGT. See [here](https://github.com/ec-europa/platform-dev/tree/release-2.5/profiles/common/modules/features/nexteuropa_dgt_connector/#requesting-access-to-the-dgt-connector) more information about this. Note: This documentation needs an update.

### Configuration

There are a number of required configuration options needed for the Poetry integration to work. These can be added at this path: `admin/tmgmt/translators/manage/poetry`:

* Identifier code - Unless requested otherwise, this can be `WEB`.
* Request title prefix - The prefix agreed with DGT.
* Site ID - The site ID agreed with DGT.
* Application reference code - Unless requested otherwise, this can be `FPFIS`.
* Contact information - the EULogin user account of the default user to be used as a contact for making translations. These are overridable per individual translation request.
* Organisation information - Information about the DG responsible for the site. For DIGIT sites it can be, and unless otherwise requested by DGT:
  * Responsible - DIGIT
  * Author - IE/CE/DIGIT
  * Requester - IE/CE/DIGIT

### Environment variables

There are a few settings that need to end up in the `settings.php` file of the site. The values of these are, however, populated as environment variables by DevOps.

Using the runner, ensure that the following ends up in the site's `settings.php` file:

```
$settings["poetry.identifier.sequence"] = getenv('POETRY_IDENTIFIER_SEQUENCE');
$settings["poetry.service.endpoint"] = getenv('POETRY_SERVICE_ENDPOINT');
$settings["poetry.service.username"] = getenv('POETRY_SERVICE_USERNAME');
$settings["poetry.service.password"] = getenv('POETRY_SERVICE_PASSWORD');
$settings["poetry.notification.username"] = getenv('POETRY_NOTIFICATION_USERNAME');
$settings["poetry.notification.password"] = getenv('POETRY_NOTIFICATION_PASSWORD');
```

Then, request DevOps to fill those variables on the server with the following values:

* `POETRY_SERVICE_ENDPOINT` - The Poetry endpoint URL, either for Acceptance of Production.
* `POETRY_IDENTIFIER_SEQUENCE` - `NEXT_EUROPA_COUNTER`
* `POETRY_SERVICE_USERNAME` - Username provided by DGT or CEM for accessing the Poetry environment.
* `POETRY_SERVICE_PASSWORD` - Password provided by DGT or CEM for accessing the Poetry environment.
* `POETRY_NOTIFICATION_USERNAME` - Username for authenticating requests made by Poetry to the site (the notifications).
* `POETRY_NOTIFICATION_PASSWORD` - Password for authenticating requests made by Poetry to the site (the notifications).

The notifications credentials should be in lowercase, limited to 15 characters and different to Poetry service credentials. The username should identify the platform making the translation requests.

#### Poetry notification endpoint rerouting

Optionally, you can configure a setting (and associated environment variable) that allows the notifications coming from Poetry to be first sent to another URL managed by DevOps and then rerouted back to the site:

```
$settings["poetry.notification.endpoint_prefix"] = getenv('POETRY_NOTIFICATION_ENDPOINT_PREFIX');
```

If this value is set, the notification endpoint is prefixed and rerouted. Otherwise, the default site endpoint is used in Poetry requests.

## Development

For using the Mock, enable the OE Translation Poetry Mock submodule and ensure you have the following in your settings file:

```
$settings["poetry.service.endpoint"] = 'http://localhost:8080/build/poetry-mock/wsdl';
```
