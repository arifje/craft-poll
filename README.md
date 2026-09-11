# Poll plugin for Craft CMS 5

Poll adds a simple poll section, an Entries field for selecting polls from other content, front-end submission
helpers, result summaries, and CSV export for submitted answers.

## Requirements

- Craft CMS 5.0 or later
- PHP 8.2 or later

## Installation

This fork is intended for Craft 5 projects. Until it is available from a package registry, add the fork as a VCS
repository and require the package:

```bash
composer config repositories.craft-poll vcs https://github.com/arifje/craft-poll
composer require twentyfourhoursmedia/poll:^3.1
php craft plugin/install poll
```

## Setup

After the plugin is installed and enabled, go to **Utilities → Poll** and run the setup. It creates:

- a **Polls** section (`pollSection`, channel) with an entry type that has a title and the answers field;
- a **Poll answers** Matrix field (`pollAnswerMatrix`) with an **Answer** entry type (`pollAnswer`) that holds a
  plain text field (`pollAnswerLabel`). The field is exposed on answers under the handle `label`, so templates read
  `answer.label`;
- a **Select poll** Entries field (`selectedPoll`) that you can add to your own entry types to attach a poll.

Craft 5 stores Matrix content as nested entries rather than Matrix blocks: each answer is an entry of the
`pollAnswer` entry type. Existing templates can keep reading the answer label as `answer.label`.

To customize the generated handles, copy `poll.dist.php` to `config/poll.php` and adjust the values *before*
running the setup. The utility only reports; it never removes or renames anything.

## Usage

Create polls in the Polls section and add answers to the poll answers Matrix field. To attach a poll to another
entry, add the `selectedPoll` field to that entry type.

### Front-end form

```twig
{% set poll = entry.selectedPoll.one() %}
{% if poll %}
    {% if craft.poll.hasParticipated(poll) %}
        {% set results = craft.poll.results(poll) %}
        <p>{{ poll.title }} — {{ results.count }} votes</p>
        <ul>
            {% for result in results.byAnswer %}
                <li>{{ result.answer.label }}: {{ result.count }}{% if result.percent is not null %} ({{ result.percent|round }}%){% endif %}</li>
            {% endfor %}
        </ul>
    {% else %}
        <form method="post" accept-charset="UTF-8">
            {{ csrfInput() }}
            {{ actionInput('poll/answer/submit') }}
            {{ redirectInput('polls/thanks') }}
            {{ pollInputs(poll) }}
            {% for answer in poll.pollAnswerMatrix.all() %}
                <label>
                    <input type="radio" name="{{ generatePollAnswerFieldName(poll, answer) }}" value="{{ generatePollAnswerFieldValue(poll, answer) }}">
                    {{ answer.label }}
                </label>
                {# optional: free text that is stored with the answer #}
                <input type="text" name="{{ generatePollAnswerTextFieldName(poll, answer) }}">
            {% endfor %}
            <button type="submit">Vote</button>
        </form>
    {% endif %}
{% endif %}
```

- `pollInputs(poll)` renders the hidden inputs (site, poll and answers-field identifiers) the submit action validates.
- The `redirect` value must be generated with `redirectInput()` (or the `hash` filter). Without a redirect the
  action responds with JSON (`{"success": true|false, "message": null|"Already participated"}`), which is convenient
  for `fetch()`/AJAX submissions.
- Only enabled polls accept submissions. A user can vote once per poll: logged-in users are checked against the
  stored votes, anonymous visitors against a cookie (see the plugin settings for its size and lifetime).

### Results

- `craft.poll.results(poll, options)` returns a `PollResults` model, see [doc/get-results.md](doc/get-results.md)
  for the options (participating users etc.).
- `craft.poll.hasParticipated(poll)` / `craft.poll.hasParticipated(poll, user)` checks participation.
- `craft.poll.getPoll(id)` returns a poll entry regardless of its status.
- In the control panel, **Poll results** lists the polls with their number of votes; each poll links to a results
  page with a CSV download of the raw data. Both require the *Access Poll* permission.

### Events

`PollEvents::POLL_SUBMITTED` is triggered on the poll entry after a vote has been stored:

```php
use craft\elements\Entry;
use twentyfourhoursmedia\poll\events\PollEvents;
use twentyfourhoursmedia\poll\events\PollSubmittedEvent;
use yii\base\Event;

Event::on(Entry::class, PollEvents::POLL_SUBMITTED, function(PollSubmittedEvent $event) {
    $event->poll;    // the poll entry
    $event->user;    // craft\elements\User or null for anonymous votes
    $event->answers; // the submitted answer entries
});
```

### Deleting polls

Trashing a poll keeps its votes, so restoring the entry restores its results. Votes are removed when a poll is
permanently deleted; votes of polls that were hard-deleted by Craft's garbage collection are cleaned up on the next
garbage collection run. The **Block plugin uninstall** setting (on by default) prevents uninstalling the plugin,
which would drop the votes table.

## Upgrading from the Craft 4 version (2.x)

1. Upgrade Craft to 5 first. Craft converts Matrix blocks to nested entries in place (element IDs are kept), so
   stored votes stay linked to their answers.
2. Update the plugin: `composer require twentyfourhoursmedia/poll:^3.1`.
3. Existing sections, fields and entry types are kept as they are; the setup utility only creates what is missing.
   Templates keep working (`answer.label`, `pollInputs()`, `generatePollAnswerFieldName()`, `craft.poll.*`).
4. Note the behaviour changes listed in the [changelog](CHANGELOG.md) (e.g. `PollSubmittedEvent::$user` is now the
   user element, and trashing a poll no longer deletes its votes).

---

Originally brought to you by [24hoursmedia](https://www.24hoursmedia.com).
