<?php
/**
 * Poll plugin for Craft CMS 5.x
 *
 * @link      https://www.24hoursmedia.com
 * @copyright Copyright (c) 2020 24hoursmedia
 */

namespace twentyfourhoursmedia\poll\events;

use craft\elements\Entry;
use craft\elements\User;
use yii\base\Event;

/**
 * Class PollSubmittedEvent
 *
 * Triggered on the poll entry after a vote has been stored:
 *
 * ```php
 * Event::on(Entry::class, PollEvents::POLL_SUBMITTED, function(PollSubmittedEvent $event) { ... });
 * ```
 *
 * @package twentyfourhoursmedia\poll\events
 */
class PollSubmittedEvent extends Event
{
    /**
     * @var Entry|null the poll entry
     */
    public ?Entry $poll = null;

    /**
     * @var User|null the user that submitted the answer, if logged in
     */
    public ?User $user = null;

    /**
     * @var Entry[] the submitted answer entries (nested Matrix entries)
     */
    public array $answers = [];
}
