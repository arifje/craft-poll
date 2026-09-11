<?php
/**
 * Poll plugin for Craft CMS 5.x
 *
 * @link      https://www.24hoursmedia.com
 * @copyright Copyright (c) 2020 24hoursmedia
 */

namespace twentyfourhoursmedia\poll\elements\db;

use craft\elements\db\EntryQuery;
use craft\models\Section;
use twentyfourhoursmedia\poll\Poll;
use twentyfourhoursmedia\poll\services\PollService;

/**
 * Entry query that is always limited to the poll section(s).
 */
class PollQuery extends EntryQuery
{
    /**
     * The cached poll sections
     *
     * @var Section[]|null
     */
    private ?array $pollSections = null;

    private PollService $pollService;

    public function __construct($elementType, array $config = [])
    {
        $this->pollService = Poll::$plugin->pollService;
        parent::__construct($elementType, $config);
    }

    /**
     * Returns the poll sections, cached
     *
     * @return Section[]
     */
    private function getPollSections(): array
    {
        if ($this->pollSections === null) {
            $this->pollSections = $this->pollService->getPollSections();
        }
        return $this->pollSections;
    }

    /**
     * Resets section ids so only poll sections are returned
     */
    private function resetSectionIds(): void
    {
        $this->sectionId = array_map(static fn(Section $section) => $section->id, $this->getPollSections());
    }

    /**
     * @inheritdoc
     * The section param is ignored: a poll query is always limited to the poll section(s).
     */
    public function section(mixed $value): static
    {
        $this->resetSectionIds();
        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->resetSectionIds();
        return parent::beforePrepare();
    }
}
