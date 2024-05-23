# Translations v2 User Guide

The v2 of the translation system is a floor-to-ceiling overhaul of how you manage content translations (Node). It no longer uses TMGMT and is organised around the concept of a Translation Request (used both for local and remote translations).

Although this system can be used on other entity types as well, this guide will be using Node as an example.

## Translation Dashboard

The entry point of the new system is the content translation dashboard, located on the `Translate` tab of the Node.

On this page you can find:

* A list of the existing Node translations
* The ongoing local translations (the ones that have been started already but not yet finished (_synchronised_))
* The ongoing remote (ePoetry) translations

The dashboard also has a submenu from where you can access the individual dashboards of the local and remote translations.

## Access

Access to the "Translate" tab is restricted to the Translator role.

## Local translations

Local translations allow to translate content manually. From the local translation dashboard, you can use the "New translation" link for each language to create a
new translation. If the Node already has a translation in a previous version, the translation fields will be pre-filled with the values from the previous version translation.

A local translation can be saved as **Draft** (to be continued later), as **Accepted** (to mark that it's in a ready state) and/or directly **Synchronised** (more info on the concept of _synchronisation_ below).

## ePoetry translations

### Configuration

The ePoetry configurator is located on the ePoetry Remote Translator configuration page: `/admin/structure/remote-translation-provider/epoetry/edit`.

You **must** have the following configured:

* Default contacts (that can be overridden per translation request):
  * Recipient (the person who will receive notifications about the status of the request and the availability of the translations)
  * Webmaster (the person that DGT can contact for information on the request and to negotiate the deadline)
  * Editor (the author of the page to be translated – the translator might contact this person if they have questions about the content of the page)
* Request title prefix (used as a prefix string in the title of the ePoetry requests)
* Site ID (also used in the title of the ePoetry requests)

Optionally, you may configure:

* Auto-accept translations (as translations arrive from ePoetry, they get marked as accepted automatically)
* Language mapping (if you have languages whose language codes do not match the ones ePoetry expects, for example pt-pt -> PT)


### Translations

ePoetry translations requests can be started from the Remote translations dashboard.

Request values (the mandatory ones are marked accordingly on the form):

* Languages
  * You can pick as many as you like
  * If you didn't include all you needed, once the translation request has been accepted by ePoetry, you can also include others to the request
* Auto-accept translations (as translations arrive from ePoetry, they get marked as accepted automatically)
* Auto-sync translations (as translations arrive from ePoetry, they get synchronised automatically)
* Deadline (when you would like the translations to be done by)
  * Be careful not to pick a weekend in your deadline
* Message (a message to the translators at)
* Contacts (prefilled from the site-wide configuration but you can alter per request)

Once the translation request has been sent, you need to wait for ePoetry to either Accept it or Reject it. If they Reject it, the system gets unblocked and you can resubmit it. If they Accept it, the flow can continue.

**NOTE: Until ePoetry either Accepts or Rejects the request, no other request can be made to ePoetry for this Node**.

#### Statuses

ePoetry translation requests are marked with 3 different sets of Statuses:

1. Request Status (request level)
   * **Requested**
     -> the initial status set when the request is first created.
   * **Translated**
     -> set when all the individual language translations have arrived from ePoetry and their work is done on this request.
   * **Finished**
     -> set when
     * all the individual language translations have been synchronised onto the content or
     * the request has been replaced by another one via an Update request (see below) or
     * the request has failed and has been manually marked as finished
   * **Failed**
     -> set when the request has failed due to a network issue, connection issue, exception, etc.
   * **Failed & Finished**
     -> manually-set status to unblock a Failed request (see below)
2. ePoetry Status (request level)
   * **SenttoDGT**
     -> the initial status set when the request is first sent to ePoetry and everything went well.
   * **Accepted**
     -> set by ePoetry to mark that the entire request has been Accepted and that they will work on it.
   * **Rejected**
     -> set by ePoetry to mark that they reject the entire request. They can supply a message (found in the Log Messages section of the request) that indicates why they rejected the request.
     * Once rejected, a new request can be submitted.
     * When the request is Rejected by ePoetry, the Request Status is marked as Finished.
     * Rejected requests do not show up on the Remote Translation dashboard, however, you will see a message indicating that
 the previous request had been rejected and that you can submit a new one.
   * **Cancelled**
     -> set by ePoetry to cancel the request. From this moment on, the request is dead. To continue, another request needs to be made.
     * When the request is Cancelled by ePoetry, the Request Status is marked as Finished.
   * **Suspended**
     -> set by ePoetry to temporarily suspend the work on this request. They can unsuspend it.
   * **Executed**
     -> set by ePoetry to mark that they have finished all the translations (all translations should have also arrived).
3. Language (also known as Product in ePoetry) status (individual language translation level)
     * **Requested** [in ePoetry]
       -> the initial status set when the request is first created.
     * **Accepted** [in ePoetry]
       -> set by ePoetry to indicate that they Accepted the language and they will work on it. They may also provide an *Accepted date* to indicate by when they will send the translation.
     * **Ongoing** [in ePoetry]
       -> set by ePoetry to indicate that they are working on it. They may also provide an *Accepted date* to indicate by when they will send the translation.
     * **ReadyToBeSent** [in ePoetry]
       -> set by ePoetry to indicate that they are finished and the translation will be soon sent. They may also provide an *Accepted date* to indicate by when they will send the translation.
     * **Sent** [in ePoetry]
       -> set by ePoetry to indicate that they sent the translation.
     * **Closed** [in ePoetry]
       -> set by ePoetry to indicate that they are finished with this translation.
     * **Review**
       -> the translation has arrived and is ready for Review. A Review button will take you to a form to check the translation values.
     * **Accepted**
       -> the translation has been marked as Accepted by the site translator
     * **Synchronised**
       -> the translation has been Synchronised with the content.
     * **Cancelled** [in ePoetry]
       -> set by ePoetry to indicate that they cancelled this translation. The flow for this translation stops here.
     * **Suspended** [in ePoetry]
       -> set by ePoetry to indicate that they suspended this translation. They can unsuspend later.

#### Extra languages

Once a translation request has been accepted by ePoetry, you have the option to add extra languages to the request if you haven't added all of them yet. On the translation request page, you have an `Add new languages` button that will take you to a form
where you can choose the other languages. Note that you cannot remove existing languages from the request.

The added languages then go through the same flow in ePoetry like the original ones: they get marked as Accepted, Ongoing, etc.

#### Update requests

Once a translation request has been Accepted by ePoetry AND you make a new version of the content, you can make an `Update` request. This informs ePoetry that the content has changed and
that you'd like them to consider the new content. This will result in them
resending the translations they had already sent and sending updated translations for the languages they haven't yet sent.

You can do so by using the `Update` button on the translation request page. This can happen only while there is still a request for which at least
one translation has still not arrived from ePoetry. If all have arrived, then the flow is as usual (you can make a new request).

The `Update` translation request form will present you the same fields as when making the initial translation request. However, when it is sent successfully to ePoetry, it replaces the original translation request which is marked as `Finished` in its Request Status. The two requests
are linked together and this link is visible in the Log Messages of both requests, as well as on the new translation request page.

*NOTE: when using the corporate workflow, a new version of the content means that you have created a new Validated revision.*

#### Dossiers

This section is a bit technical but it's important to understand how ePoetry requests are structured.

The first request that is made to ePoetry starts a new linguistic `dossier` that has a `number` (for example 59). A dossier has multiple parts and the request for the first Node gets assigned part `0`.

* Subsequent requests (for different Nodes) add `parts` to that dossier.
* When the parts reach 30 for a given dossier, a new dossier is created and with parts starting again from 0.
* Subsequent requests (for the same Node) add `versions` to the existing part in that dossier.

The resulting ePoetry reference number for a given request is therefore in the following format: `SITE/YEAR/NUMBER/VERSION/PART/PRODUCT_TYPE`. For example: `COMM/2023/59/0/0/TRA`.

Example:

1. The first request generates the reference COMM/2023/59/0/0/TRA.
2. Update requests, or new requests for the same node, will generate COMM/2023/59/1/0/TRA, COMM/2023/59/2/0/TRA, COMM/2023/59/3/0/TRA, COMM/2023/59/4/0/TRA, and so on.
3. A request for another node will generate COMM/2023/59/0/1/TRA.
4. Subsequent requests to other nodes will increment the parts until it reaches COMM/2023/59/0/30/TRA.
5. When the parts reach 30, the subsequent request for another node will create a new dossier. For example COMM/2023/65/0/0/TRA.

In the lifecycle of a site, there will be one or more dossiers generated. These are being tracked by the site and can be seen on the ePoetry translator configuration form: `/admin/structure/remote-translation-provider/epoetry/edit`.

There is always a "current" dossier, meaning the one into which new parts or versions get created. This is indicated on the form, as well as all the previous dossiers that have run their course (either by reaching the maximum number of parts) or by manually resetting them (see next).

**Resetting a dossier**: you can check the box to reset the current dossier and save the form. This will ensure that the very next request will create a new dossier in ePoetry instead of trying to create new parts or versions. This can come in handy if there is a problem with the last request
and the situation is blocked.

#### Translation review

When a given language translation is in Review, you can click the Review button for that language and check the translation values. You can then do the following:

* Accept the translation -> saves the request with this translation marked as Accepted
* Synchronise the translation -> synchronises the translation with the Node

Note: if the request has Auto-accept or Auto-sync turned on, the above steps would happen automatically as the translation arrives from ePoetry.

#### Failed requests

If the translation request fails and there is an error shown, you need to contact the site administrator. The request is currently marked as Failed and the Log Messages on the request page should
indicate the error. Once the issue was addressed, the failed request must be marked as Failed & Finished. You can do so by going to the translations
dashboard and using the `Mark as finished` operation on the respective translation request. This will unblock the flow and a request can be tried again.

### Poetry legacy

For sites that have used already Poetry, there is a Poetry legacy table for administrators who can check the last Poetry reference that was sent for each node.

Dashboard: `/admin/content/legacy-poetry-references`

Moreover, the first time an ePoetry request is being sent, the Message value will include automatically the last Poetry reference ID of that Node so that ePoetry can find it back from the old system.

This functionality is provided by the optional `oe_translation_poetry_legacy` submodule. In order for this to work, ensure that when switching between Poetry and ePoetry, the module is on so that it can
find all the old Poetry reference IDs from the TMGMT jobs.

### ePoetry translations dashboard

Apart from the individual Node translations dashboard, you also have a dashboard where you can have an overview over all the ePoetry translation requests:

`/admin/content/epoetry-translation-requests`

## CDT translations

### Configuration

Define the configuration of the module in the ENV variables. If the mocking module is enabled, the configuration does not matter and is always valid.
```
CDT_TOKEN_API_ENDPOINT: "https://example.com/api/token"
CDT_MAIN_API_ENDPOINT: "https://example.com/api/main"
CDT_REFERENCE_DATA_API_ENDPOINT: "https://example.com/api/reference-data"
CDT_VALIDATE_API_ENDPOINT: "https://example.com/api/validate"
CDT_REQUESTS_API_ENDPOINT: "https://example.com/api/requests"
CDT_IDENTIFIER_API_ENDPOINT: "https://example.com/api/identifier/:correlationId"
CDT_STATUS_API_ENDPOINT: "https://example.com/api/status/:requestyear/:requestnumber"
CDT_FILE_API_ENDPOINT: "https://example.com/api/files/:id"
CDT_CLIENT: "test_client"
CDT_USERNAME: "test_username"
CDT_PASSWORD: "test_password"
CDT_API_KEY: "test_apikey"
```

Optionally, you may configure this in the plugin settings (`/admin/structure/remote-translation-provider/cdt/edit`):

* Language mapping (if you have languages whose language codes do not match the ones CDT expects, for example pt-pt -> PT)

### Translations

CDT translations requests can be started from the Remote translations dashboard.

Request values (the mandatory ones are marked accordingly on the form):

* **Languages** (you can pick as many as you like)
* **Comments** (a message to translators)
* **Confidentiality** (select it from the list)
* **Contact usernames** (select it from the list)
* **Deliver to** (select it from the list)
* **Department** (select it from the list)
* **Phone number** (so the translator can reach you)
* **Priority** (select it from the list)

None of these values can be manually changed later in Drupal. However, if CDT updates them on your site, Drupal will update them automatically.

Once the translation request has been submitted, you need to wait for CDT to assign a permanent ID to it. You can do this manually by clicking "Get permanent ID", or you can wait for a callback from CDT.

#### Statuses

CDT translation requests are marked with two sets of Statuses:

1. Translation Request Status
   * **Requested**
     -> the initial status set when the request is first created.
   * **Translated**
     -> set when all the individual language jobs have arrived from CDT and their work is done on this request.
   * **Finished**
     -> set when all the individual language translations have been synchronised onto the content or the request has failed.
2. Translation Job Status
   * **Requested**
	   -> the initial status set when the job is first created.
   * **Review**
	   -> the translation has arrived and is ready for Review. A Review button will take you to a form to check the translation values.
   * **Accepted**
	   -> the translation has been marked as Accepted by the site translator.
   * **Synchronised**
	   -> the translation has been Synchronised with the content.
   * **Cancelled** [in CDT]
	   -> set by CDT to indicate that they cancelled this translation. The flow for this translation stops here.
   * **Failed** [in CDT]
	   -> set by CDT to indicate that this translation has failed.

#### Translation review

When a given language translation is in Review, you can click the Review button for that language and check the translation values. You can then do the following:

* Accept the translation -> saves the request with this translation marked as Accepted
* Synchronise the translation -> synchronises the translation with the Node

## Synchronisation

Synchronisation means that the translation values will be saved onto the Node itself (creating or updating the translation).

Each translation request (both local or remote) is started from a specific Node version. This means that when the synchronisation happens, the translation values
get saved onto that specific revision. This means that if you have ongoing translations and you make a new draft, those translations will be synced onto the previous revision, the new draft
essentially losing out on the translation values.

This is because of the "upstream" impossibility. Any new draft may contain data structure changes that would be impossible to reconcile with the structure of the previous revision. Which means that
we cannot cleanly save the translations onto the new structure.

An exception to the above rule is made if the site is using the Corporate Workflow. See relevant section.

## Corporate workflow rules

* If the translation is started from a revision in Validated state, the values will be synchronised onto the Published revision of the same major version (if one has been made in the meantime).
* No translation can be started until there is a Validated or Published version (new major version) of the Node.
* If there are translation started (but not yet finalised), on a Published version (for ex version 1):
  * and there is a new Draft created of the node:
    * starting new translations (local or remote) will still happen for the Published version.
    * synchronising the started translations (or any new translations that may start) will use the Published version.
    * the two points above mean that version 2 (when that gets created) will miss out on these translation values.
  * and there is a new major version created of the node (either Validated or Published), for example version 2.
    * you can start new translations for both versions (1 and 2)
    * translations that have started  before version 2 was started with its first Draft will be synced only on version 1
