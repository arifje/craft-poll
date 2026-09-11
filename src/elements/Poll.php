<?php
/**
 * Poll plugin for Craft CMS 5.x
 *
 * @link      https://www.24hoursmedia.com
 * @copyright Copyright (c) 2020 24hoursmedia
 */

namespace twentyfourhoursmedia\poll\elements;

use Craft;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\UrlHelper;
use twentyfourhoursmedia\poll\elements\db\PollQuery;
use twentyfourhoursmedia\poll\records\PollAnswer;

/**
 * Poll element.
 *
 * A read-only view on the entries of the poll section, used to render the "Poll results"
 * element index in the control panel. Rows link to the results page instead of the entry editor.
 */
class Poll extends Entry
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('poll', 'Poll');
    }

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return Craft::t('poll', 'poll');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('poll', 'Polls');
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('poll', 'polls');
    }

    /**
     * No reference tag handle: `{entry:…}` reference tags must keep resolving to craft\elements\Entry.
     */
    public static function refHandle(): ?string
    {
        return null;
    }

    /**
     * @inheritdoc
     * @return PollQuery
     */
    public static function find(): EntryQuery
    {
        return new PollQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context): array
    {
        if ($context !== 'index') {
            return [];
        }

        return [
            [
                'key' => '*',
                'label' => Craft::t('poll', 'All polls'),
                'criteria' => [],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'postDate' => ['label' => Craft::t('poll', 'Date')],
            'votes' => ['label' => Craft::t('poll', 'Votes')],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['postDate', 'votes'];
    }

    /**
     * @inheritdoc
     */
    protected function attributeHtml(string $attribute): string
    {
        if ($attribute === 'votes') {
            return (string)PollAnswer::find()->where(['pollId' => $this->id])->count();
        }

        return parent::attributeHtml($attribute);
    }

    /**
     * Links to the results page instead of the entry editor.
     */
    protected function cpEditUrl(): ?string
    {
        return UrlHelper::actionUrl('poll/cp-result/result', ['id' => $this->id]);
    }

    // Polls are managed as regular entries; this element type is read-only.

    /**
     * @inheritdoc
     */
    public function canView(User $user): bool
    {
        return $user->can('accessPlugin-poll');
    }

    /**
     * @inheritdoc
     */
    public function canSave(User $user): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function canDuplicate(User $user): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function canDuplicateAsDraft(User $user): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function canDelete(User $user): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function canDeleteForSite(User $user): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function canCreateDrafts(User $user): bool
    {
        return false;
    }
}
