## 2.0.2
- Fixed a bug that was causing a validation error for fields using `date_mdy` or `date_dmy` validation.
- Fixed a limitation that was preventing extras from being available for `date` fields.
  - Extras validation can now use any combination of Date and DateTime fields.
- Changed some code to be PHP 7.3 compatible
## 2.0.1
- Fixed a bug that would prevent a specimen save if a specimen field was not enabled on the Specimen Entry Form, but was marked required or had Extras.
- Fixed a bug that was preventing the deletion of specimens from a box.
- Fixed a bug that was affecting the "Reset Specimen" if a field had the "Batch Mode" configuration enabled, even when batch mode was "Off" during specimen entry.
## 2.0.0
- **BREAKING CHANGES**
- This is a full rebuild that will cause breaking changes in existing projects if you update without proper preparation and configuration.
- Review the new documentation, and ideally get familiar with and test it in a dev environment before going to production. 
- There are too many changes to list here - review the README to learn more!
## 1.0.3
- SQL queries updated to support new data tables behavior (REDCap v14.0)
- Slightly changed validation behavior to cooperate with intended field navigation
- Made unit display lookup on box [sample_type] case-insensitive
- Frozen fields are no longer required, such that they can be provided at a later time after initial entry
- Specimens can now be moved within the same box (excluding '00' temp boxes)
## 1.0.2
- Fixed a bug that prevented box position context from initializing when entering a box from the search function
- Fixed a display issue with volume units if the `sample_type` component in the box name is unknown
- Fixed a bug that would prevent `enter key` navigation through the CSID/CUID fields when they were left blank
## 1.0.1
- Added support for a 10x10 box size
- Minor layout fixes related to Bootstrap 5
- Added configuration option disable the required validation of CSID/CUID
- Fixed multiple bugs related to CSID/CUID validation
## 1.0.0
- Initial Release
