<?php

namespace SilverCommerce\Subsites;

use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Subsites\Model\Subsite;
use SilverCommerce\OrdersAdmin\Model\Invoice;
use SilverStripe\Subsites\State\SubsiteState;
use SilverCommerce\OrdersAdmin\Model\Estimate;

class DataObjectExtension extends DataExtension
{
    private static $has_one = array(
        'Subsite' => Subsite::class
    );

    public function isMainSite()
    {
        return $this->owner->SubsiteID == 0;
    }

    public function updateCMSFields(FieldList $fields) {
        var_dump($this->owner);

        $fields->push(
            HiddenField::create(
                'SubsiteID',
                'SubsiteID',
                Subsite::currentSubsiteID()
            )
        );
    }

    /**
     * Update any requests to limit the results to the current site
     * 
     * -- This is taken from the silverstripe/subsites module
     * 
     * @param SQLSelect $query
     * @param DataQuery $dataQuery
     */
    public function augmentSQL(SQLSelect $query, DataQuery $dataQuery = null)
    {
        if (Subsite::$disable_subsite_filter) {
            return;
        }

        if ($dataQuery && $dataQuery->getQueryParam('Subsite.filter') === false) {
            return;
        }

        // If you're querying by ID, ignore the sub-site - this is a bit ugly...
        // if(!$query->where
        // || (strpos($query->where[0], ".\"ID\" = ") === false
        // && strpos($query->where[0], ".`ID` = ") === false && strpos($query->where[0], ".ID = ") === false
        // && strpos($query->where[0], "ID = ") !== 0)) {
        if ($query->filtersOnID()) {
            return;
        }

        $subsiteID = null;

        if (Subsite::$force_subsite) {
            $subsiteID = Subsite::$force_subsite;
        } else {
            $subsiteID = SubsiteState::singleton()->getSubsiteId();
        }

        if ($subsiteID === null) {
            return;
        }

        $class = $this->owner->ClassName;

        // If we are dealing with an invoice, switch to estimate
        if (class_exists(Invoice::class) && $class == Invoice::class) {
            $class = Estimate::class;
        }

        foreach ($query->getFrom() as $tableName => $info) {
            // The tableName should be SiteTree or SiteTree_Live...
            $ObjectTableName = DataObject::getSchema()->tableName($class);

            if (strpos($tableName, $ObjectTableName) === false) {
                break;
            }

            $query->addWhere("\"$tableName\".\"SubsiteID\" IN ($subsiteID)");
            break;
        }
    }

    public function onBeforeWrite()
    {
        if (!$this->owner->ID && !$this->owner->SubsiteID) {
            $this->owner->SubsiteID = SubsiteState::singleton()->getSubsiteId();
        }
        parent::onBeforeWrite();
    }
}