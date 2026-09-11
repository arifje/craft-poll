<?php
/**
 * Poll plugin for Craft CMS 5.x
 *
 * @link      https://www.24hoursmedia.com
 * @copyright Copyright (c) 2020 24hoursmedia
 */

namespace twentyfourhoursmedia\poll\twigextensions;

use Craft;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\helpers\Html;
use twentyfourhoursmedia\poll\models\PollResults;
use twentyfourhoursmedia\poll\Poll;
use twentyfourhoursmedia\poll\services\PollService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig functions and filters for rendering poll forms.
 *
 * @author    24hoursmedia
 * @package   Poll
 * @since     1.0.0
 */
class PollTwigExtension extends AbstractExtension
{
    /**
     * @inheritdoc
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('poll_participated', [$this, 'participatedInPoll']),
            new TwigFilter('poll_results', [$this, 'getPollResults']),
            new TwigFilter('poll_uniqid', [$this, 'createUid']),
        ];
    }

    /**
     * @inheritdoc
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('generatePollAnswerFieldName', [$this, 'generatePollAnswerFieldName']),
            new TwigFunction('generatePollAnswerTextFieldName', [$this, 'generatePollAnswerTextFieldName']),
            new TwigFunction('generatePollAnswerFieldValue', [$this, 'generatePollAnswerFieldValue']),
            new TwigFunction('pollInputs', [$this, 'getPollInputs'], ['is_safe' => ['html']]),
            new TwigFunction('pollUid', [$this, 'createUid']),
            // deprecated, use craft.poll.results():
            new TwigFunction('getPollResults', [$this, 'getPollResults']),
            // deprecated, use craft.poll.getPoll():
            new TwigFunction('getPoll', [$this, 'getPoll']),
        ];
    }

    /**
     * Renders the hidden inputs a poll form needs (site, poll and answers field identifiers).
     *
     * @param Entry $poll
     * @param EntryQuery|iterable|null $matrix the answers Matrix field value; defaults to the configured answers field
     * @throws \craft\errors\SiteNotFoundException
     */
    public function getPollInputs(Entry $poll, mixed $matrix = null): string
    {
        $service = Poll::$plugin->pollService;
        $fieldsService = Craft::$app->getFields();
        $field = null;

        if ($matrix instanceof EntryQuery) {
            $answer = $matrix->one();
        } elseif (is_iterable($matrix)) {
            $answer = iterator_to_array($matrix, false)[0] ?? null;
        } else {
            $answer = null;
        }
        if ($answer instanceof Entry && $answer->fieldId) {
            $field = $fieldsService->getFieldById($answer->fieldId);
        }

        if (!$field) {
            $field = $fieldsService->getFieldByHandle(
                $service->getConfigOption(PollService::CFG_FIELD_ANSWER_MATRIX_HANDLE)
            );
        }
        if (!$field) {
            return 'ERROR invalid answers field';
        }

        $site = $poll->getSite();

        return implode("\n", [
            Html::hiddenInput($service->getConfigOption(PollService::CFG_FORM_SITEID_FIELDNAME), (string)$site->id),
            Html::hiddenInput($service->getConfigOption(PollService::CFG_FORM_SITEUID_FIELDNAME), (string)$site->uid),
            Html::hiddenInput($service->getConfigOption(PollService::CFG_FORM_POLLID_FIELDNAME), (string)$poll->id),
            Html::hiddenInput($service->getConfigOption(PollService::CFG_FORM_POLLUID_FIELDNAME), (string)$poll->uid),
            Html::hiddenInput($service->getConfigOption(PollService::CFG_FORM_ANSWERFIELDID_FIELDNAME), (string)$field->id),
            Html::hiddenInput($service->getConfigOption(PollService::CFG_FORM_ANSWERFIELDUID_FIELDNAME), (string)$field->uid),
        ]);
    }

    /**
     * Generates the input name for a poll answer (radio button).
     */
    public function generatePollAnswerFieldName(Entry $poll, Entry $answer): string
    {
        $service = Poll::$plugin->pollService;
        return "{$service->getConfigOption(PollService::CFG_FORM_POLLANSWER_FIELDNAME)}[{$poll->uid}]";
    }

    /**
     * Generates the input name for the optional free text that goes with an answer.
     */
    public function generatePollAnswerTextFieldName(Entry $poll, Entry $answer): string
    {
        $service = Poll::$plugin->pollService;
        return "{$service->getConfigOption(PollService::CFG_FORM_POLLANSWERTEXT_FIELDNAME)}[{$poll->uid}][{$answer->uid}]";
    }

    /**
     * Generates the input value for a poll answer.
     */
    public function generatePollAnswerFieldValue(Entry $poll, Entry $answer): string
    {
        return (string)$answer->uid;
    }

    /**
     * @deprecated use craft.poll.hasParticipated()
     */
    public function participatedInPoll(mixed $poll): bool
    {
        return Poll::$plugin->pollService->hasParticipated($poll);
    }

    /**
     * @deprecated use craft.poll.results()
     */
    public function getPollResults(mixed $pollOrPollId, array $opts = []): ?PollResults
    {
        return Poll::$plugin->facade->getResults($pollOrPollId, $opts);
    }

    /**
     * Creates a unique id for use in html as element id's etc.
     */
    public function createUid(?string $prefix = null): string
    {
        return uniqid((string)$prefix, false);
    }

    /**
     * @deprecated use createUid()
     */
    public function createUniqid(?string $prefix = null): string
    {
        return $this->createUid($prefix);
    }

    /**
     * Returns a poll regardless whether it is enabled or not
     *
     * @deprecated use craft.poll.getPoll()
     */
    public function getPoll(mixed $id): ?Entry
    {
        return Poll::$plugin->pollService->getPoll($id);
    }
}
