# OpenEuropa Translation Poetry

This submodule integrates with the DGT Poetry system to provide translations services.

For using the Mock, enable the OE Translation Poetry Mock submodule and add the following to your settings file:

```
$config['tmgmt.translator.poetry']['settings']['service_wsdl'] = 'http://localhost:8080/build/poetry-mock/wsdl';
```

## Requirements

If you want to use this submodule, you need to add the following packages to your site:

* ec-europa/oe-poetry-client
