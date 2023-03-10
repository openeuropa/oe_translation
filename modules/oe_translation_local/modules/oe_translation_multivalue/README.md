# OpenEuropa Translation Multivalue

This submodule can be used to solve a very particular, but annoying, issue with the way the local translation system works.

## The problem (scenario)

* You have a node with a translatable multivalue field, with 2 values. The key here is that it needs to be multivalue, but not an entity reference. A regular one like Textfield or Link.
* You translate the node and its multivalue field values.
* You edit the node and you reorder the values in the multivalue field.
* You translate again using local translation and the pre-filled translation values no longer matches the delta for the multivalue field. Because in the previous version the deltas were reversed. So if you save the translation without paying
attention, you'll end up with mixed up translation values.

This problem is caused by the fact on the local translation form, the system does a best effort to pre-fill with translation values from the previous content version. Most of the time, it manages. Even with values that are within
multiple referenced entities. It cannot, however, do so on simple multivalue fields because it cannot guess that they were reversed or any added. All it can check is the delta.

## The solution

Installing the current module gives the possibility to add a new column to multivalue fields called `translation_id`. So whenever a value is saved, a unique ID is generated for it. And based on this ID, the
system can then track which value is at which delta to prefill.

## How it works

It works by overriding the field item class for a given field and adding a new table column and property to track this translation ID. Moreover, it does the handling for saving this ID when synchronising the translations
as well.

## How to use

Go to the storage settings of a multivalue translatable field and check the box `Translation multivalue`. This will create the column and add the property.

Note that this only works for fields which don't have any data inside.

If you already have data in the field, or you have an existing site where you need to turn this feature one, you need to do an update path. For this, there is a helper method: `TranslationMultivalueColumnInstaller::installColumn`.

To this helper you need to pass the field name in the format `entity_type.field_name`. If you do this on multiple fields with lots of data, make sure you batch this per field to avoid problems.

**Note that for the update, the process creates a backup table, truncates the field tables, adds the column and then sets
back the values. So make sure you thoroughly test your process before deploying to production to avoid any issues**.

## Support

Currently, only certain field types are supported. You can check `oe_translation_multivalue_field_types()` for which field types are altered for this.

If you need support for other field types, open an issue or a ticket on the EWPP board.

