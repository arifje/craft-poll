# Poll Changelog

## 3.1.1 - 2026-09-11

### Changed
- The `master` branch is retired; `v5` is the Craft 5 line. The plugin's changelog URL now follows the repository's default branch.

## 3.1.0 - 2026-09-11

Craft 5 compatibility audit (tested against Craft 5.11.1, PHP 8.2, PHPStan level 5 and the Craft 5 Rector set).

### Fixed
- Deleting or trashing *any* entry that contains Matrix content threw “Attempt to read property "handle" on null” (Craft 5 also fires the after-delete event for nested entries, which have no section). The cleanup hook now ignores nested entries.
- Trashing a poll no longer deletes its votes; restoring the poll restores its results. Votes are removed when a poll is permanently deleted, and votes of polls that were hard-deleted by garbage collection are cleaned up on the next `gc/run`.
- The **Poll results** element index in the control panel: rows link to the results page again and are read-only (no slideout editor), a *Votes* column was added, and the leftover *Test1/Test2* sub-navigation is gone.
- Poll lookups in the control panel use the selected site (Craft 5 no longer sets a site cookie); front-end lookups use the current site instead of always site 1.
- The uninstall block (“Block plugin uninstall” setting) no longer `die()`s with raw HTML; it aborts the uninstall with a message, from the control panel and the CLI.
- Saving the answers Matrix field with propagation method “none” now shows a validation error on the field (the session flash used before is not displayed by Craft 5).
- Results/CSV actions in the control panel require the *Access Poll* permission and return a 404 for unknown polls.
- Fresh installs get the `(userId, pollId)` and `(dateCreated)` indexes (they were only added by a migration that fresh installs skipped).
- The setup utility no longer reports “OK” when a check failed, guards against a missing section or entry type, and gives new answer entry types an automatic title (`{label}`) plus the block view mode with an “Add an answer” button.
- `ResultService`: replaced the deprecated `anyStatus()` query param; use the correct `username` column (PostgreSQL).
- Removed the custom insert logic of the `PollAnswer` record (Craft 5 only fills the audit columns that exist).
- The participation cookie honours `defaultCookieDomain`, `useSecureCookies` and `sameSiteCookieValue`.
- Removed PHP 8.1+/8.4 deprecations (`trim(null)`, implicit nullable parameters, `fputcsv()` escape parameter).
- Empty submissions no longer cause “array offset on string” errors in the submit action.

### Changed
- `PollSubmittedEvent::$user` is now the `craft\elements\User` element (it used to be the `craft\web\User` component).
- Removed leftovers that no longer exist in Craft 5’s APIs: `Poll::getIsEditable()`, `PollUtility::iconPath()`, the no-op *Create report* element action and the empty asset bundles. The utility uses the plugin icon.
- `PollService::getPoll()`, `getAnswers()` and `hasParticipated()` are null-safe and typed; `getConfigOption()` returns `null` for unknown keys.

## 3.0.0

### Changed
- Added Craft CMS 5 compatibility and raised requirements to PHP 8.2+ and Craft 5.
- Updated Matrix answer handling for Craft 5 nested entries instead of Matrix blocks.
- Updated setup to use Craft 5 entry type, section, field, and utility APIs.
- Updated README installation and setup notes for the Craft 5 fork.

## 2.1.0

### Fixed
- Improve multi-site suport (thanks to MR #34 from @adrienne)
- Fixes issue with multisite poll results in the CP (thanks to MR#34 from @adrienne)
- Fix potential issue when multiple field ids are returned by a matrix (MR#40, @kringkaste)

## 2.0.1
### Changed
- Craft 4 compatibility release

## 1.7.0 - 2021-02-28
### Added
- Now it's possible to add user input text fields to answers. [see the docs](https://io.24hoursmedia.com/craftcms-poll/add-textual-user-input-to-poll-choices)

## 1.6.3 - 2021-02-09

### Fixed
- A potential block in gc/run (garbage cleanup) due to a Craft error is now ignored.

## 1.6.1 - 2020-10-28

### Fixed
- Fix problems on case sensitive file systems (i.e. linux vs osx/win)

## 1.6.0 - 2020-10-27

### Fixed
- composer v2 compatibility

## 1.5.0 - 2020-10-14

### Fixed
- Carft 3.5 compatibility fixes

## 1.2.5 - 2020-02-20

### Fixed
- Fixed problem viewing poll results in Admin CP

## 1.2.4 - 2020-02-09
### Fixed
- fixed installation button for content in craft 3.4

## 1.2.3 - 2020-02-09

### Added
- Added a PollSubmittedEvent to hook into caching or do external processing - see https://io.24hoursmedia.com/craftcms-poll/poll-events

### Modified
- When retrieving results, user count now refers to num users per answer instead of poll total
- Optimized database indices

## 1.2.2 - 2020-02-06

### Fixed
- Fixed ordering of users by participation date

## 1.2.1 - 2020-02-06

### Fixed
- MySQL 5.7 compatibility

## 1.2.0 - 2020-02-05

### Added
- Get participating users for a poll in twig/frontend
- Get user votes by answer in twig/frontent
- Added percentage in poll results by answer

### Modified
- Added a craft.poll variable that exposes public methods to manage and get data from a poll
- Added getResults and more to craft.poll, replacing legacy twig filters

## 1.1.2 - 2020-02-02

### Added
- Added control panel section for polls
- Download raw data for polls for marketing analysis / segmentation

## 1.0.3 - 2020-01-29

### Fixed
- Poll plugin blocked removal of other plugins

## 1.0.1 - 2020-01-29

### Modified
- Configuration options
- Safety block against accidental uninstall

### Modified
- [#1 Poll submissions will be deleted when a poll entry is deleted](https://github.com/24hoursmedia-craftcms/poll/issues/1)

## 1.0.0 - 2020-01-22
### Added
- Initial release
