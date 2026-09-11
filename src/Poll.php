<?php
/**
 * Poll plugin for Craft CMS 5.x
 *
 * @link      https://www.24hoursmedia.com
 * @copyright Copyright (c) 2020 24hoursmedia
 */

namespace twentyfourhoursmedia\poll;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\events\RegisterComponentTypesEvent;
use craft\fields\Matrix;
use craft\helpers\UrlHelper;
use craft\services\Elements;
use craft\services\Gc;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;
use twentyfourhoursmedia\poll\elements\Poll as PollElement;
use twentyfourhoursmedia\poll\models\Settings;
use twentyfourhoursmedia\poll\services\Facade;
use twentyfourhoursmedia\poll\services\InstallService;
use twentyfourhoursmedia\poll\services\PollService;
use twentyfourhoursmedia\poll\services\ResultService;
use twentyfourhoursmedia\poll\twigextensions\PollTwigExtension;
use twentyfourhoursmedia\poll\utilities\PollUtility;
use twentyfourhoursmedia\poll\variables\PollVariable;
use yii\base\Event;
use yii\base\ModelEvent;
use yii\web\ForbiddenHttpException;

/**
 * Poll plugin
 *
 * @author    24hoursmedia
 * @package   Poll
 * @since     1.0.0
 *
 * @property-read PollService $pollService
 * @property-read ResultService $resultService
 * @property-read InstallService $installService
 * @property-read Facade $facade
 * @method    Settings getSettings()
 */
class Poll extends Plugin
{
    public const LOG_CATEGORY = 'poll_plugin';

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * Poll::$plugin
     */
    public static ?Poll $plugin = null;

    public string $schemaVersion = '1.1.1';

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        $this->setComponents([
            'installService' => InstallService::class,
            'resultService' => ResultService::class,
            'facade' => Facade::class,
        ]);

        // Twig extension (pollInputs(), generatePollAnswerFieldName(), ...)
        Craft::$app->getView()->registerTwigExtension(new PollTwigExtension());

        // Register the Poll element type (backs the "Poll results" index in the control panel)
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            static function(RegisterComponentTypesEvent $event) {
                $event->types[] = PollElement::class;
            }
        );

        // Register the setup utility
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            static function(RegisterComponentTypesEvent $event) {
                $event->types[] = PollUtility::class;
            }
        );

        // Register the `craft.poll` template variable
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('poll', PollVariable::class);
            }
        );

        // Remove submitted answers when a poll entry is permanently deleted.
        // Trashed (soft-deleted) polls keep their answers, so restoring the entry restores its results.
        // Note: in Craft 5 this event also fires for nested (Matrix) entries, which have no section.
        Event::on(Entry::class, Entry::EVENT_AFTER_DELETE, static function(Event $event) {
            /** @var Entry $entry */
            $entry = $event->sender;
            if (!$entry->hardDelete) {
                return;
            }
            $service = self::$plugin->pollService;
            if ($service->isAPollEntry($entry)) {
                $numRemoved = $service->removeAnswersForPoll($entry);
                Craft::info(sprintf('Removed %d poll submissions because poll entry with ID %d was permanently deleted', $numRemoved, $entry->id), self::LOG_CATEGORY);
            }
        });

        // Garbage collection hard-deletes trashed elements without firing element events,
        // so clean up answers whose poll entry no longer exists.
        Event::on(Gc::class, Gc::EVENT_RUN, static function() {
            $numRemoved = self::$plugin->pollService->removeOrphanedAnswers();
            if ($numRemoved) {
                Craft::info(sprintf('Removed %d orphaned poll submissions during garbage collection', $numRemoved), self::LOG_CATEGORY);
            }
        });

        // Block certain settings on poll answer matrices
        Event::on(Matrix::class, Matrix::EVENT_BEFORE_VALIDATE, static function(ModelEvent $event) {
            $service = self::$plugin->pollService;
            if ($service->isAnAnswerMatrix($event->sender)) {
                $event->isValid = $service->validateAnswerMatrixField($event->sender);
            }
        });
    }

    /**
     * Optionally blocks removal of the plugin to prevent data loss (see the "Block plugin uninstall" setting).
     * Throwing here aborts the uninstall; Craft rolls the transaction back.
     *
     * @throws ForbiddenHttpException
     */
    protected function beforeUninstall(): void
    {
        if ($this->getSettings()->blockPluginRemoval) {
            throw new ForbiddenHttpException(sprintf(
                'The Poll plugin cannot be uninstalled because “Block plugin uninstall” is enabled in its settings (%s). ' .
                'Uninstalling removes all submitted poll data, so back up your database first.',
                UrlHelper::cpUrl('settings/plugins/poll')
            ));
        }

        parent::beforeUninstall();
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('poll/settings', [
            'settings' => $this->getSettings(),
        ]);
    }
}
