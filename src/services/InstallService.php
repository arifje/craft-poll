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
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Entries;
use craft\fields\Matrix;
use craft\fields\PlainText;
use craft\models\FieldLayout;
use craft\models\EntryType;
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
            $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);

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


    private function createPollEntryType(): EntryType
    {
        $config = Poll::$plugin->pollService->getConfig();
        $matrix = Craft::$app->getFields()->getFieldByHandle($config[PollService::CFG_FIELD_ANSWER_MATRIX_HANDLE]);
        $fieldLayout = new FieldLayout(['type' => Entry::class]);

        if ($matrix) {
            $fieldLayout->setTabs([
                [
                    'name' => 'Poll',
                    'elements' => [
                        [
                            'type' => EntryTitleField::class,
                            'required' => true,
                        ],
                        [
                            'type' => CustomField::class,
                            'fieldUid' => $matrix->uid,
                            'required' => false,
                        ],
                    ],
                ],
            ]);
        }

        $entryTypeHandle = $config[PollService::CFG_POLL_SECTION_HANDLE] . 'Entry';
        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($entryTypeHandle) ?? new EntryType();
        $entryType->name = 'Poll';
        $entryType->handle = $entryTypeHandle;
        $entryType->showSlugField = false;
        $entryType->setFieldLayout($fieldLayout);

        return $entryType;
    }

    private function createAnswerEntryType(): EntryType
    {
        $config = Poll::$plugin->pollService->getConfig();
        $answerLabelField = $this->enforceFieldTypeWithHandle(
            $config[PollService::CFG_FIELD_ANSWER_LABEL_HANDLE],
            static function () use ($config) {
                $field = new PlainText();
                $field->handle = $config[PollService::CFG_FIELD_ANSWER_LABEL_HANDLE];
                $field->name = 'Poll answer label';
                return $field;
            }
        );
        if (!$answerLabelField) {
            throw new \RuntimeException('Could not save the poll answer label field.');
        }

        $fieldLayout = new FieldLayout(['type' => Entry::class]);
        $fieldLayout->setTabs([
            [
                'name' => 'Content',
                'elements' => [
                    [
                        'type' => CustomField::class,
                        'fieldUid' => $answerLabelField->uid,
                        'handle' => 'label',
                        'required' => false,
                    ],
                ],
            ],
        ]);

        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($config[PollService::CFG_MATRIXBLOCK_ANSWER_HANDLE]) ?? new EntryType();
        $entryType->name = 'Answer';
        $entryType->handle = $config[PollService::CFG_MATRIXBLOCK_ANSWER_HANDLE];
        $entryType->showSlugField = false;
        $entryType->showStatusField = true;
        $entryType->setFieldLayout($fieldLayout);

        return $entryType;
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
        $types = $section->getEntryTypes();
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

                $matrix = Craft::$app->getFields()->getFieldByHandle($fieldHandle);
                $fieldLayout = $type->getFieldLayout();
                $tabs = $fieldLayout->getTabs();
                $tab = $tabs[0] ?? null;
                if (!$tab) {
                    $fieldLayout->setTabs([
                        [
                            'name' => 'Poll',
                            'elements' => [
                                [
                                    'type' => CustomField::class,
                                    'fieldUid' => $matrix->uid,
                                    'required' => false,
                                ],
                            ],
                        ],
                    ]);
                } else {
                    $tab->setElements(array_merge($tab->getElements(), [
                        new CustomField($matrix, ['required' => false]),
                    ]));
                    $tabs[0] = $tab;
                    $fieldLayout->setTabs($tabs);
                }

                $success = Craft::$app->getEntries()->saveEntryType($type);
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
                $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);
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

        $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);
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

        $entryType = $this->createPollEntryType();
        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            $report->danger("Couldn't save entry type for section {$sectionHandle}");
            return false;
        }
        $section->setEntryTypes([$entryType]);

        $success = Craft::$app->getEntries()->saveSection($section, true);
        if (!$success) {

            $report->danger("Couldn't save section {$sectionHandle}");
            return false;
        }

        $this->ensureSectionHasAnswersMatrix($section, $validateOnly, $report);
        $report->ok('Section ' . $sectionHandle . ' created.');
        return true;
    }


    /**
     * Ensures a matrix field for containing answers is present
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
            function () use ($fieldHandle) {
                $entryType = $this->createAnswerEntryType();
                if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
                    throw new \RuntimeException('Could not save the poll answer entry type.');
                }

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
    public function check(?SetupReport $setupReport = null)
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
     * @throws \craft\errors\MissingComponentException
     * @throws \craft\errors\SectionNotFoundException
     */
    public function setup(?SetupReport $setupReport = null)
    {
        if (!$setupReport) {
            $setupReport = new SetupReport();
        }

        $success = $this->apply(false, $setupReport);
        //if ($success) {
        //    Craft::$app->getSession()->setNotice(Craft::t('app', 'Poll installation seems ok.'));
        //} else {
        //    Craft::$app->getSession()->setNotice(Craft::t('app', 'Poll installation failed.'));
        //}

        return $success;
    }


}
