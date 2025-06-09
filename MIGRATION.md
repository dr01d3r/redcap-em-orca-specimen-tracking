# Migration Guide (v1 to v2)

This is a basic migration guide, to help get you through the initial process of re-configuring an exist set of projects from v1 to v2 (or higher).

### Box Project Changes

**Breaking Change:** New `[box_size]` field required.

This field is required to define the size of the box.

**Solution:** Data Import File

If your project was set up for `8x12` boxes in the Control Center, you can create a simple Data Import file that's just `record` and `box_size`, and for each box record, a value of `8x12`.

**Example:**

```
record_id,box_size
1,8x12
2,8x12
3,8x12
...
999,8x12
```

### Specimen Project Changes

**Breaking Change:** Required field name change from `[name]` to `[specimen_name]`.

This change was necessary for us, but luckily it should be a pretty simple update.

**Solution:** Data Export & Data Import!

1. Create the new `[specimen_name]` field, or simply create a copy of your existing `[name]` field.
1. Create a Data Export that contains just your `[record_id]` and `[name]` fields.
1. Open and modify the export file, renaming the `name` header to `specimen_name` and adding `name` to the end.
    - The goal is to tell REDCap to update the `[specimen_name]` field and blank out the `[name]` field, so you aren't left with orphan data.
    - It's best to do this step in Excel.  A text editor can get you there, but extra manual work might need to be done.
1. Import the file through the Data Import tool, ensuring you force blank values to overwrite.
1. Once the data has been verified, you can delete the old `[name]` field.

### Configuration Changes

**Breaking Change:** Everything!

That might sound rough, but the Dashboard Configuration interface hopefully does a good job of making the new configuration process a lot easier, once you get familiar with it.

We had to get the configuration out of the Control Center, and the existing module config was not designed to cover such specific and complex needs, so we build a fully customized interface to replace it.

- Review the README and the built-in documentation within the configuration interface
- Configure one interface (color-matched columns) at a time and test as you go