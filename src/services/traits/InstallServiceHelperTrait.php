<?php
/**
 * Created by PhpStorm
 * User: eapbachman
 * Date: 22/01/2020
 */

namespace twentyfourhoursmedia\poll\services\traits;
use Craft;
use craft\models\FieldGroup;

trait InstallServiceHelperTrait
{
    /**
     * @param string $handle
     * @return bool
     */
    protected function hasFieldTypeWithHandle(string $handle): bool
    {		
        $field = Craft::$app->fields->getFieldByHandle($handle);
			
        return $field ? true : false;
    }

    /**
     * @param string $handle
     * @return bool
     */
    protected function hasSectionWithHandle(string $handle): bool
    {
        $section = Craft::$app->entries->getSectionByHandle($handle);
        return $section ? true : false;
    }

    /**
     * Makes sure a field with a handle exists, if not retrieves the field from the callback and create it
     *
     * @param $handle
     * @param callable $createCallback                         callback that should return a new field if it does not exist
     * @param callable $createdCallback                        callback after field is created, wi
     * @return bool|\craft\base\FieldInterface|null
     * @throws \Throwable
     */
    protected function enforceFieldTypeWithHandle($handle, $createCallback, $createdCallback = null) {
        $field = Craft::$app->fields->getFieldByHandle($handle);
        if (!$field) {
            $field = $createCallback();
            if (!Craft::$app->fields->saveField($field, true)) {
                /* @var $field \craft\base\Field */
                return false;
            }
            $createdCallback && $createdCallback($field);
        }
        return $field;
    }

}