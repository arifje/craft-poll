# Poll plugin for Craft CMS 5

Poll adds a simple poll section, an entries field for selecting polls from other content, frontend submission helpers, result summaries, and CSV export for submitted answers.

## Requirements

- Craft CMS 5.0 or later
- PHP 8.2 or later

## Installation

This fork is intended for Craft 5 projects. Until it is available from a package registry, add the fork as a VCS repository and require the package:

```bash
composer config repositories.craft-poll vcs https://github.com/arifje/craft-poll
composer require twentyfourhoursmedia/poll:^3.0
php craft plugin/install poll
```

## Setup

After the plugin is installed and enabled, go to **Utilities -> Poll** and run setup. The utility creates:

- a poll section
- a Matrix field for poll answers
- a poll selector Entries field

Craft 5 stores Matrix content as nested entries rather than Matrix blocks. New setup therefore creates an answer entry type for the answers Matrix. Existing templates can continue to read the answer label as `answer.label`.

If you want to customize generated handles before setup, copy `poll.dist.php` to `config/poll.php` and adjust the values.

## Usage

Create polls in the Poll section and add answers to the poll answers Matrix field.

To attach a poll to another entry, add the generated `selectedPoll` Entries field to that entry type.

The plugin exposes Twig helpers for rendering hidden form inputs, answer field names/values, participation checks, and result summaries. Existing integrations using `craft.poll`, `pollInputs()`, `generatePollAnswerFieldName()`, and `generatePollAnswerFieldValue()` remain supported.

## Craft 5 Notes

Version 3.x is Craft 5-only. It replaces Craft 3/4 Matrix block APIs with Craft 5 nested entry APIs and uses Craft 5 entry-type and utility registration services.

---

Originally brought to you by [24hoursmedia](https://www.24hoursmedia.com).
