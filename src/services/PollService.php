<?php
/**
 * Poll plugin for Craft CMS 5.x
 *
 * @link      https://www.24hoursmedia.com
 * @copyright Copyright (c) 2020 24hoursmedia
 */

namespace twentyfourhoursmedia\poll\services;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Entry;
use craft\elements\User as UserElement;
use craft\enums\PropagationMethod;
use craft\fields\Matrix;
use craft\helpers\Cp;
use craft\models\Section;
use twentyfourhoursmedia\poll\events\PollEvents;
use twentyfourhoursmedia\poll\events\PollSubmittedEvent;
use twentyfourhoursmedia\poll\Poll;
use twentyfourhoursmedia\poll\records\PollAnswer;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\web\Cookie;

/**
 * PollService
 *
 * Core business logic: configuration handles, participation tracking, submitting answers.
 *
 * @author    24hoursmedia
 * @package   Poll
 * @since     1.0.0
 */
class PollService extends Component
{
    // constants to refer to configuration keys
    public const CFG_POLL_SECTION_HANDLE = 'CFG_POLL_SECTION_HANDLE';
    public const CFG_FIELD_GROUP_NAME = 'CFG_FIELD_GROUP_NAME';
    public const CFG_FIELD_SELECT_POLL_HANDLE = 'CFG_FIELD_SELECT_POLL_HANDLE';
    public const CFG_FIELD_ANSWER_MATRIX_HANDLE = 'CFG_FIELD_ANSWER_MATRIX_HANDLE';
    public const CFG_FIELD_ANSWER_LABEL_HANDLE = 'CFG_FIELD_ANSWER_LABEL_HANDLE';
    public const CFG_MATRIXBLOCK_ANSWER_HANDLE = 'CFG_MATRIXBLOCK_ANSWER_HANDLE';

    public const CFG_FORM_POLLID_FIELDNAME = "CFG_FORM_POLLID_FIELDNAME";
    public const CFG_FORM_POLLUID_FIELDNAME = "CFG_FORM_POLLUID_FIELDNAME";
    public const CFG_FORM_POLLANSWER_FIELDNAME = "CFG_FORM_POLLANSWER_FIELDNAME";
    public const CFG_FORM_POLLANSWERTEXT_FIELDNAME = "CFG_FORM_POLLANSWERTEXT_FIELDNAME";
    public const CFG_FORM_SITEID_FIELDNAME = "CFG_FORM_SITEID_FIELDNAME";
    public const CFG_FORM_SITEUID_FIELDNAME = "CFG_FORM_SITEUID_FIELDNAME";
    public const CFG_FORM_ANSWERFIELDID_FIELDNAME = "CFG_FORM_ANSWERSFIELDID_FIELDNAME";
    public const CFG_FORM_ANSWERFIELDUID_FIELDNAME = "CFG_FORM_ANSWERSFIELDUID_FIELDNAME";

    /**
     * Name of the cookie that tracks anonymous participations
     */
    public const COOKIE_NAME = '_pollids';

    /**
     * Handle under which the answer label is exposed on answer entries (layout-level handle)
     */
    public const ANSWER_LABEL_HANDLE = 'label';

    private array $config = [
        // section, fieldtype, .. handles
        self::CFG_POLL_SECTION_HANDLE => 'pollSection',
        self::CFG_FIELD_ANSWER_MATRIX_HANDLE => 'pollAnswerMatrix',
        self::CFG_FIELD_ANSWER_LABEL_HANDLE => 'pollAnswerLabel',
        self::CFG_MATRIXBLOCK_ANSWER_HANDLE => 'pollAnswer',
        self::CFG_FIELD_SELECT_POLL_HANDLE => 'selectedPoll',

        // legacy: Craft 3/4 field group name (field groups no longer exist in Craft 5)
        self::CFG_FIELD_GROUP_NAME => 'Poll',

        // form field names
        self::CFG_FORM_SITEID_FIELDNAME => '__site_id',
        self::CFG_FORM_SITEUID_FIELDNAME => '__site_uid',
        self::CFG_FORM_POLLID_FIELDNAME => '__poll_id',
        self::CFG_FORM_POLLUID_FIELDNAME => '__poll_uid',
        self::CFG_FORM_ANSWERFIELDID_FIELDNAME => '__answerfield_id',
        self::CFG_FORM_ANSWERFIELDUID_FIELDNAME => '__answerfield_uid',
        self::CFG_FORM_POLLANSWER_FIELDNAME => '__answer',
        self::CFG_FORM_POLLANSWERTEXT_FIELDNAME => '__text',
    ];

    public function __construct($config = [])
    {
        parent::__construct($config);

        $settings = Poll::$plugin->getSettings();
        $this
            ->applyConfig(self::CFG_POLL_SECTION_HANDLE, $settings->sectionHandle)
            ->applyConfig(self::CFG_FIELD_ANSWER_MATRIX_HANDLE, $settings->answerMatrixFieldHandle)
            ->applyConfig(self::CFG_FIELD_SELECT_POLL_HANDLE, $settings->selectPollFieldHandle)
            ->applyConfig(self::CFG_MATRIXBLOCK_ANSWER_HANDLE, $settings->matrixBlockAnswerHandle);
    }

    /**
     * Sets a value in $config if val does not evaluate to an empty string
     */
    private function applyConfig(string $key, mixed $val): self
    {
        $val = trim((string)$val);
        if ('' === $val) {
            return $this;
        }
        $this->config[$key] = $val;
        return $this;
    }

    /**
     * @return array = $this->config
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function getConfigOption(string $handle): mixed
    {
        return $this->config[$handle] ?? null;
    }

    /**
     * Returns the poll IDs stored in the participation cookie of the current request.
     *
     * @return int[]
     */
    public function getCookiePollIds(): array
    {
        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest()) {
            return [];
        }
        $participatedPolls = explode(',', (string)$request->getCookies()->getValue(self::COOKIE_NAME, ''));
        $participatedPolls = array_map('intval', $participatedPolls);
        return array_values(array_filter($participatedPolls));
    }

    /**
     * Adds a poll id to a cookie to keep track of anonymous participations
     */
    public function addPollIdToCookie(int $pollId): void
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            return;
        }
        $settings = Poll::$plugin->getSettings();
        $cookiePollIds = $this->getCookiePollIds();
        array_unshift($cookiePollIds, $pollId);
        $cookiePollIds = array_slice($cookiePollIds, 0, (int)$settings->numCookieParticipations);
        $cookiePollIds = array_values(array_unique($cookiePollIds));

        // Craft::cookieConfig() applies defaultCookieDomain, useSecureCookies and sameSite from the general config
        $cookie = new Cookie(Craft::cookieConfig([
            'name' => self::COOKIE_NAME,
            'value' => implode(',', $cookiePollIds),
            'expire' => time() + 86400 * (int)$settings->participationsCookieLifetime,
        ]));
        Craft::$app->getResponse()->getCookies()->add($cookie);
    }

    /**
     * Checks whether a user has participated in a poll.
     * Logged-in users are checked against the database, anonymous users against the participation cookie.
     *
     * @param Entry|int|string $pollOrPollId
     * @param mixed $user a user element, the web user component, a user ID, or null for the current user
     */
    public function hasParticipated(mixed $pollOrPollId, mixed $user = null): bool
    {
        $pollId = $pollOrPollId instanceof Entry ? (int)$pollOrPollId->id : (int)$pollOrPollId;
        $userId = $this->resolveUserId($user);
        if ($userId) {
            return PollAnswer::find()->where(['pollId' => $pollId, 'userId' => $userId])->exists();
        }
        return in_array($pollId, $this->getCookiePollIds(), true);
    }

    /**
     * Resolves a user argument (or the current user) to a user ID.
     */
    private function resolveUserId(mixed $user = null): ?int
    {
        if ($user === null) {
            $user = Craft::$app->has('user') ? Craft::$app->getUser() : null;
        }
        if ($user === null) {
            return null;
        }
        if ($user instanceof UserElement) {
            return $user->id ? (int)$user->id : null;
        }
        if (is_numeric($user)) {
            return (int)$user;
        }
        if (is_object($user) && method_exists($user, 'getId')) {
            $id = $user->getId();
            return $id ? (int)$id : null;
        }
        return isset($user->id) ? (int)$user->id : null;
    }

    /**
     * Stores a submitted answer for a poll.
     *
     * @param Entry $poll the entry in the polls section
     * @param int $siteId the site from which the form was submitted
     * @param int $answerFieldId the field id of the Matrix field that contains the answers
     * @param array $answerUids uid's of the selected answer entries (exactly one is expected)
     * @param array|null $answerTexts = ['answer_uid' => 'some comment', 'answer_uid2' => 'some other comment']
     * @return bool whether the submission was stored
     * @throws \craft\errors\InvalidFieldException
     */
    public function submit(Entry $poll, int $siteId, int $answerFieldId, array $answerUids, ?array $answerTexts = []): bool
    {
        $answerMatrix = $poll->getFieldValue($this->getConfigOption(self::CFG_FIELD_ANSWER_MATRIX_HANDLE));
        $answerTexts = is_array($answerTexts) ? $answerTexts : [];
        /** @var Entry[] $answers */
        $answers = array_values(array_filter(
            $answerMatrix ? $answerMatrix->all() : [],
            static fn(Entry $answer) => in_array($answer->uid, $answerUids, true)
        ));
        if (count($answers) !== 1) {
            return false;
        }

        $this->addPollIdToCookie((int)$poll->id);

        $userId = $this->resolveUserId();
        $request = Craft::$app->getRequest();
        $ip = $request->getIsConsoleRequest() ? null : $request->getUserIP();

        foreach ($answers as $answer) {
            $answerText = $answerTexts[$answer->uid] ?? null;
            $record = new PollAnswer([
                'pollId' => $poll->id,
                'siteId' => $siteId,
                'fieldId' => $answerFieldId,
                'answerId' => $answer->id,
                'userId' => $userId,
                'answerText' => is_scalar($answerText) ? (string)$answerText : null,
                'ip' => $ip ? (inet_pton($ip) ?: null) : null,
            ]);
            $record->save();
        }

        $poll->trigger(PollEvents::POLL_SUBMITTED, new PollSubmittedEvent([
            'poll' => $poll,
            'user' => $userId ? Craft::$app->getUser()->getIdentity() : null,
            'answers' => $answers,
        ]));

        return true;
    }

    /**
     * Returns a poll entry.
     *
     * @param Entry|int|string|null $pollOrPollId
     * @param string|string[]|null $status the status, defaults to all polls, also disabled ones.
     * @param int|null $siteId defaults to the site of the current request (the selected site in the control panel)
     */
    public function getPoll(mixed $pollOrPollId, mixed $status = null, ?int $siteId = null): ?Entry
    {
        if (!$pollOrPollId) {
            return null;
        }
        if ($pollOrPollId instanceof Entry) {
            return $this->isAPollEntry($pollOrPollId) ? $pollOrPollId : null;
        }

        if (!$siteId) {
            $request = Craft::$app->getRequest();
            if (!$request->getIsConsoleRequest() && $request->getIsCpRequest()) {
                // the site selected in the control panel (`site` query param)
                $siteId = Cp::requestedSite()?->id;
            }
            $siteId ??= Craft::$app->getSites()->getCurrentSite()->id;
        }

        return Entry::find()
            ->siteId($siteId)
            ->section($this->getConfigOption(self::CFG_POLL_SECTION_HANDLE))
            ->id((int)$pollOrPollId)
            ->status($status)
            ->one();
    }

    /**
     * Returns the section(s) that have polls
     *
     * @return Section[]
     */
    public function getPollSections(): array
    {
        $sections = [];
        foreach ([$this->getConfigOption(self::CFG_POLL_SECTION_HANDLE)] as $handle) {
            $section = Craft::$app->getEntries()->getSectionByHandle($handle);
            if ($section) {
                $sections[] = $section;
            }
        }
        return $sections;
    }

    /**
     * Returns the answer entries (nested Matrix entries) of a poll.
     *
     * @param Entry|int|string|null $pollOrPollId
     * @return Entry[]
     */
    public function getAnswers(mixed $pollOrPollId): array
    {
        $poll = $this->getPoll($pollOrPollId);
        if (!$poll) {
            return [];
        }
        $matrix = $poll->getFieldValue($this->getConfigOption(self::CFG_FIELD_ANSWER_MATRIX_HANDLE));
        return $matrix ? $matrix->all() : [];
    }

    /**
     * Returns the label of an answer entry.
     */
    public function getAnswerLabel(Entry $answer): ?string
    {
        $label = $answer->{self::ANSWER_LABEL_HANDLE} ?? $answer->{$this->getConfigOption(self::CFG_FIELD_ANSWER_LABEL_HANDLE)} ?? null;
        return is_scalar($label) ? (string)$label : null;
    }

    /**
     * Gets answer labels
     *
     * @param array $pollOrPollIds
     * @return array = [232 => 'label 1', 443 => 'label 2']
     */
    public function getAnswerLabelsIndexedById(array $pollOrPollIds): array
    {
        $labels = [];
        foreach ($pollOrPollIds as $pollOrPollId) {
            foreach ($this->getAnswers($pollOrPollId) as $answer) {
                $labels[$answer->id] = $this->getAnswerLabel($answer) ?? '(no label)';
            }
        }
        return $labels;
    }

    /**
     * Checks if a field is an answer matrix field.
     * Used to hook into the validation.
     */
    public function isAnAnswerMatrix(mixed $field): bool
    {
        if (!$field instanceof Matrix) {
            return false;
        }
        // the handle must be one of the registered handles
        return $field->handle === $this->getConfigOption(self::CFG_FIELD_ANSWER_MATRIX_HANDLE);
    }

    /**
     * Verify if something is a Poll entry.
     * Nested (Matrix) entries have no section and are never polls.
     */
    public function isAPollEntry(mixed $element): bool
    {
        if (!$element instanceof Entry || !$element->sectionId) {
            return false;
        }
        try {
            $section = $element->getSection();
        } catch (InvalidConfigException) {
            // Craft may pass entries with an invalid section during garbage collection
            // https://github.com/craftcms/cms/issues/7356
            return false;
        }
        return $section !== null && $section->handle === $this->getConfigOption(self::CFG_POLL_SECTION_HANDLE);
    }

    /**
     * When an answers Matrix field is saved, check if the settings are ok (propagation methods allowed etc).
     * An event handler of the plugin calls this method.
     *
     * @see Poll::init()
     */
    public function validateAnswerMatrixField(Matrix $matrix): bool
    {
        if (!$this->isAnAnswerMatrix($matrix)) {
            throw new \LogicException("The field to validate is not recognized as an answer matrix field!");
        }
        if ($matrix->propagationMethod === PropagationMethod::None) {
            $matrix->addError('propagationMethod', Craft::t(
                'poll',
                'The propagation method of a poll answers field cannot be set to “{method}”.',
                ['method' => $matrix->propagationMethod->value]
            ));
            return false;
        }
        return true;
    }

    /**
     * Remove all answer submissions for a poll entry.
     * Called by an event handler when a poll entry is permanently deleted.
     * First check ::isAPollEntry before calling this method.
     *
     * @param Entry|int $entry
     * @return int the number of records deleted
     * @see PollService::isAPollEntry()     to check if the entry is actually a poll
     * @see Poll::init()                    where the event handler is registered
     */
    public function removeAnswersForPoll(mixed $entry): int
    {
        $pollId = $entry instanceof Entry ? $entry->id : $entry;
        if (!$pollId) {
            throw new \LogicException('No id set');
        }
        return PollAnswer::deleteAll(['pollId' => $pollId]);
    }

    /**
     * Removes answers whose poll entry no longer exists (e.g. hard-deleted by garbage collection).
     *
     * @return int the number of records deleted
     */
    public function removeOrphanedAnswers(): int
    {
        return PollAnswer::deleteAll([
            'not in',
            'pollId',
            (new Query())->select('id')->from(Table::ELEMENTS),
        ]);
    }
}
