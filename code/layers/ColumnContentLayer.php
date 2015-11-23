<?php

/**
 * 
 * @author marcus
 */
class ColumnContentLayer extends FlatLayer
{
    private static $virtual_layers = array(
        'ContentColumns'    => array('type' => 'TitledImageContentLayer', 'number' => 3)
    );

    public function getCMSFields()
    {
        $fields = FieldList::create();

        $fields->push(TextField::create($this->fullFieldName('Title'), 'Title'));

        foreach ($this->getLayers() as $l) {
            $layerFields = $l->getCMSFields();
            $t = $l->Title;
            $compositeField = ToggleCompositeField::create($l->getName(), $t ? $t : $l->getFriendlyName(), $layerFields);
            $fields->push($compositeField);
        }

        return $fields;
    }
}