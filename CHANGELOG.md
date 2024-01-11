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
