<?php
/**
 * Created by PhpStorm
 * User: eapbachman
 * Date: 22/01/2020
 */

namespace twentyfourhoursmedia\poll\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\enums\PropagationMethod;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Entries;
use craft\fields\Matrix;
use craft\fields\PlainText;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use twentyfourhoursmedia\poll\models\SetupReport;
use twentyfourhoursmedia\poll\Poll;
use twentyfourhoursmedia\poll\services\traits\InstallServiceHelperTrait;


class InstallService extends Component
{

    use InstallServiceHelperTrait;


    /**
     * Ensures there is a 'select poll field'
     * @param $validateOnly
     * @param SetupReport $report
     * @return bool|\craft\base\FieldInterface|null
     * @throws \Throwable
     */
    private function ensureSelectPollField($validateOnly, SetupReport $report)
    {

        $config = Poll::$plugin->pollService->getConfig();
        $fieldHandle = $config[PollService::CFG_FIELD_SELECT_POLL_HANDLE];

        if ($validateOnly) {
            if (!$this->hasFieldTypeWithHandle($fieldHandle)) {
                $report->warn("There is no field type with handle {$fieldHandle}");
                return false;
            } else {
                $report->ok("There is a field type with handle {$fieldHandle}");
                return true;
            }
        }

        return $this->enforceFieldTypeWithHandle($fieldHandle, function () use ($config, $fieldHandle) {
            $sectionHandle = $config[PollService::CFG_POLL_SECTION_HANDLE];
            $section = Craft::$app->entries->getSectionByHandle($sectionHandle);

            $field = new Entries();
            $field->handle = $fieldHandle;
            $field->name = 'Select poll';
            $field->allowLimit = true;
            $field->maxRelations = 1;
            $field->allowMultipleSources = false;
            $field->sources = ['section:' . $section->uid];
            return $field;
        });

    }


    /**
     * Checks if a section has the answers matrix in it's entry type
     * @param Section $section
     * @param bool $validateOnly
     * @param SetupReport $report
     * @return bool
     * @throws \Throwable
     * @throws \craft\errors\EntryTypeNotFoundException
     * @throws \yii\base\InvalidConfigException
     */
    private function ensureSectionHasAnswersMatrix(Section $section, bool $validateOnly, SetupReport $report)
    {
        $config = Poll::$plugin->pollService->getConfig();
        $sectionHandle = $config[PollService::CFG_POLL_SECTION_HANDLE];
        $fieldHandle = $config[PollService::CFG_FIELD_ANSWER_MATRIX_HANDLE];
        $types = $section->entryTypes;
        $type = $types[0] ?? null;


        if (!$type) {
            $report->warn('No entry type for section ' . $sectionHandle . ' found.');
            if ($validateOnly) {
                return false;
            }
        }

        $matrixField = $type->getFieldLayout()->getFieldByHandle($fieldHandle);
        if (!$matrixField) {
            if ($validateOnly) {
                $report->warn("Entry type in section {$sectionHandle} does not contain matrix field with handle {$fieldHandle}");
                return false;
            } else {

                $matrix = Craft::$app->fields->getFieldByHandle($fieldHandle);
                $tabs = $type->getFieldLayout()->getTabs();
                $tab = $tabs[0] ?? null;
                if (!$tab) {
                    $tab = new FieldLayoutTab();
                    $tab->name = 'Poll';
                    $type->getFieldLayout()->setTabs([$tab]);
                }

                $newElement = [
                    'type' => CustomField::class,
                    'fieldUid' => $matrix->uid,
                    'required' => false,
                ];
                $tab->setElements(array_merge($tab->getElements(), ['new1' => $newElement]));

                $tabs[0] = $tab;
                $type->getFieldLayout()->setTabs($tabs);
                $success = Craft::$app->fields->saveLayout($type->getFieldLayout());
                if ($success) {
                    $report->ok("Created in Section {$sectionHandle}: entry type with handle {$fieldHandle}");
                } else {
                    $report->danger("FAILED: Created in Section {$sectionHandle}: entry type with handle {$fieldHandle}");
                }
                return $success;
            }
        }
        return true;


    }

    /**
     * Ensure a polls section is present
     *
     * @param bool $validateOnly
     * @param SetupReport $report
     * @return bool
     * @throws \Throwable
     * @throws \craft\errors\SectionNotFoundException
     */
    private function ensureSection(bool $validateOnly, SetupReport $report): bool
    {
        $config = Poll::$plugin->pollService->getConfig();
        $sectionHandle = $config[PollService::CFG_POLL_SECTION_HANDLE];

        $hasSection = $this->hasSectionWithHandle($sectionHandle);

        if ($validateOnly) {
            if ($hasSection) {
                $report->ok(
                    sprintf('There is a section in Craft with handle %s', $sectionHandle)
                );

                // additional check
                $section = Craft::$app->entries->getSectionByHandle($sectionHandle);
                $hasMatrix = $this->ensureSectionHasAnswersMatrix($section, $validateOnly, $report) ? true : false;
                if ($hasMatrix) {
                    $report->ok(
                        sprintf("The entry type in section $sectionHandle contains the answers matrix", $sectionHandle)
                    );
                } else {
                    $report->warn(
                        sprintf("The entry type in section $sectionHandle does not contain the answers matrix", $sectionHandle)
                    );
                }

                return $hasMatrix;
            } else {
                $report->warn(
                    sprintf('There is no section in Craft with handle %s', $sectionHandle)
                );
                return false;
            }
        }

        $section = Craft::$app->entries->getSectionByHandle($sectionHandle);
        if ($section) {
            return $this->ensureSectionHasAnswersMatrix($section, $validateOnly, $report);
        }


        // create a new poll section
        $section = new Section([]);
        $section->handle = $sectionHandle;
        $section->name = 'Polls';
        $section->type = Section::TYPE_CHANNEL;
        $section->enableVersioning = false;
        $section->propagationMethod = PropagationMethod::All;

        // Craft 5 requires at least one entry type on the section before saving.
        // The entry type must be saved first (to get a UID) before it can be
        // referenced in the section's project config.
        $defaultEntryType = new EntryType();
        $defaultEntryType->name = 'Poll';
        $defaultEntryType->handle = 'poll';
        $defaultEntryType->hasTitleField = true;
        Craft::$app->entries->saveEntryType($defaultEntryType);
        $section->setEntryTypes([$defaultEntryType]);

        $allSiteSettings = [];
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $settings = new Section_SiteSettings();
            $settings->siteId = $site->id;
            $settings->uriFormat = null;
            $settings->enabledByDefault = true;
            $settings->hasUrls = false;
            $allSiteSettings[$site->id] = $settings;
        }
        $section->setSiteSettings($allSiteSettings);
        $success = Craft::$app->entries->saveSection($section, true);
        if (!$success) {

            $report->danger("Couldn't save section {$sectionHandle}");
            return false;
        }

        $this->ensureSectionHasAnswersMatrix($section, $validateOnly, $report);
        $report->ok('Section ' . $sectionHandle . ' created.');
        return true;
    }


    /**
     * Ensures a matrix field for containing answers is present.
     * In Craft 5, Matrix blocks are replaced by nested entries (EntryTypes).
     *
     * @param bool $validateOnly
     * @param SetupReport $report
     * @return bool|\craft\base\FieldInterface|null
     * @throws \Throwable
     */
    private function ensureMatrix(bool $validateOnly, SetupReport $report)
    {
        $config = Poll::$plugin->pollService->getConfig();
        $fieldHandle = $config[PollService::CFG_FIELD_ANSWER_MATRIX_HANDLE];

        if ($validateOnly) {
            if ($this->hasFieldTypeWithHandle($fieldHandle)) {
                $report->ok(
                    sprintf('There is a matrix field with handle %s', $fieldHandle)
                );
                return true;
            } else {
                $report->warn(
                    sprintf('There is no matrix field in Craft with handle %s', $fieldHandle),
                    'Run setup to initialize the matrix field'
                );
                return false;
            }
        }

        return $this->enforceFieldTypeWithHandle(
            $fieldHandle,
            function () use ($config, $fieldHandle) {
                $answerTypeHandle = $config[PollService::CFG_MATRIXBLOCK_ANSWER_HANDLE];

                // Create a PlainText label field for the answer entry type
                $labelField = Craft::$app->fields->getFieldByHandle('label');
                if (!$labelField) {
                    $labelField = new PlainText();
                    $labelField->name = 'Label';
                    $labelField->handle = 'label';
                    Craft::$app->fields->saveField($labelField);
                }

                // Create the answer entry type
                $entryType = new EntryType();
                $entryType->name = 'Answer';
                $entryType->handle = $answerTypeHandle;
                $entryType->hasTitleField = false;

                $fieldLayout = new FieldLayout();
                $tab = new FieldLayoutTab(['name' => 'Content', 'sortOrder' => 1]);
                // setLayout() must be called before setElements() so the tab knows its parent layout
                $tab->setLayout($fieldLayout);
                $tab->setElements([new CustomField($labelField)]);
                $fieldLayout->setTabs([$tab]);
                $entryType->setFieldLayout($fieldLayout);

                Craft::$app->entries->saveEntryType($entryType);

                // Create the Matrix field using the new entry type
                $matrix = new Matrix();
                $matrix->handle = $fieldHandle;
                $matrix->name = 'Poll answers';
                $matrix->propagationMethod = PropagationMethod::All;
                $matrix->setEntryTypes([$entryType]);
                return $matrix;
            }
        );
    }

    /**
     * @param bool $validateOnly
     * @param SetupReport $setupReport
     * @return bool
     * @throws \Throwable
     * @throws \craft\errors\SectionNotFoundException
     */
    private function apply(bool $validateOnly, SetupReport $setupReport)
    {
        $success = true;
        $success = ($success || $validateOnly) && $this->ensureMatrix($validateOnly, $setupReport);
        $success = ($success || $validateOnly) && $this->ensureSection($validateOnly, $setupReport);
        $success = ($success || $validateOnly) && $this->ensureSelectPollField($validateOnly, $setupReport);
        return $success;
    }

    /**
     * Checks validation
     * @param SetupReport|null $setupReport
     * @return bool
     * @throws \Throwable
     * @throws \craft\errors\SectionNotFoundException
     */
    public function check(SetupReport $setupReport = null)
    {
        if (!$setupReport) {
            $setupReport = new SetupReport();
        }
        $success = $this->apply(true, $setupReport);
        return $success;
    }

    /**
     * Applies setup
     *
     * @param SetupReport|null $setupReport
     * @return bool
     * @throws \Throwable
     * @throws \craft\errors\SectionNotFoundException
     */
    public function setup(SetupReport $setupReport = null)
    {
        if (!$setupReport) {
            $setupReport = new SetupReport();
        }

        $success = $this->apply(false, $setupReport);

        return $success;
    }


}
