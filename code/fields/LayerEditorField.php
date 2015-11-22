<?php

/**
 * Description of LayerEditorField
 *
 * @author marcus
 */
class LayerEditorField extends FormField
{
    protected $layers;

    /**
     *
     * @var FieldList
     */
    protected $children;

    public function __construct($name, $record, $title = null, $layers = null)
    {
        parent::__construct($name, $title);

        $this->children = FieldList::create();
        $this->layers  = ArrayList::create();
        if ($layers) {
            foreach ($layers as $l) {
                $this->addLayer($l, $record);
            }
        }
    }

    public function isComposite()
    {
        return true; // parent::isComposite();
    }

    public function collateDataFields(&$list, $saveableOnly = false)
    {
        foreach ($this->children as $field) {
            if ($field->isComposite()) {
                $field->collateDataFields($list, $saveableOnly);
            }

            $isIncluded = $field->hasData() && !$saveableOnly;
            if ($isIncluded) {
                $list[$field->getName()] = $field;
            }
        }
    }

    public function removeByName($fieldName, $dataFieldOnly = false)
    {
        return $this->children->removeByName($fieldName, $dataFieldOnly);
    }

    /**
     * Retrieves the list of records that have been edited and return to the user
     *
     * @return ArrayList
     */
    public function getLayers()
    {
        return $this->layers;
    }

    public function setForm($form)
    {
        parent::setForm($form);

        foreach ($this->children as $child) {
            $child->setForm($form);
        }
    }

    public function addLayer($layer, $record)
    {
        $fields = null;
        $this->layers->push($layer);
        if (method_exists($layer, 'multiEditor')) {
            $editor = $layer->multiEditor();
            // add its records to 'me'
            $this->addMultiEditor($editor, $layer, true);
            return;
        } elseif (method_exists($layer, 'multiEditFields')) {
            $fields = $layer->multiEditFields()->dataFields();
        } else {
            $fields = $layer->getCMSFields()->dataFields();
        }
        /* @var $fields FieldList */

        $layer->extend('updateMultiEditFields', $fields);

        $this->children->push(HeaderField::create('RecordHeader'.$layer->getName(), $layer->Title));
        foreach ($fields as $field) {
            $original = $field->getName();

            // if it looks like a multieditor field, let's skip for now.
            if (strpos($original, '__') > 0) {
                continue;
            }

            if ($field instanceof MultiRecordEditingField) {
                $this->addMultiEditor($field, $layer);
                continue;
            }

            $exists = (
                isset($layer->$original) ||
                $layer->hasMethod($original) ||
                ($layer->hasMethod('hasField') && $layer->hasField($original))
                );

            $val = null;
            if ($exists) {
                $val = $layer->__get($original);
            }

            $field->setValue($val, $layer);

            // re-write the name to the multirecordediting name for later retrieval
            // this cannot be done earlier as otherwise, fields that load data from the
            // record won't be able to find the information they're expecting
            $name = $this->getFieldName($field, $layer);
            $field->setName($name);

            if (method_exists($field, 'setRecord')) {
                $field->setRecord($record);
            }

            $this->children->push($field);
        }
    }

    protected function addMultiEditor($editor, $fromRecord, $addHeader = false)
    {
        if ($addHeader) {
            $this->children->push(HeaderField::create('RecordHeader'.$fromRecord->ID, $fromRecord->Title));
        }

        foreach ($editor->getRecords() as $r) {
            $this->addRecord($r);
        }
    }

    protected function getFieldName($field, $record)
    {
        $name = $field instanceof FormField ? $field->getName() : $field;

        return sprintf(
            '%s__%s__%s__%s', $this->getName(), $record->ClassName, $record->ID, $name
        );
    }

    public function saveInto(\DataObjectInterface $record)
    {
        $v = $this->Value();

//		if (is_array($v)) {
        $allItems = array();
        foreach ($this->children as $field) {
            $fieldname = $field->getName();
            if (strpos($fieldname, '__') > 0) {
                $bits = array_reverse(explode('__', $fieldname));
                if (count($bits) > 3) {
                    list($dataFieldName, $id, $classname) = $bits;
                    if (!isset($allItems["$classname-$id"])) {
                        $item                       = $this->records->filter(array('ClassName' => $classname, 'ID' => $id))->first();
                        $allItems["$classname-$id"] = $item;
                    }
                    $item = $allItems["$classname-$id"];
                    if ($item) {
                        if ($field) {
                            $field->setName($dataFieldName);
                            $field->saveInto($item);
                        }
                    }
                }
            }
        }

        foreach ($allItems as $item) {
            $item->write();
        }

        parent::saveInto($record);
    }

    public function FieldHolder($properties = array())
    {
        return $this->children;
    }
}