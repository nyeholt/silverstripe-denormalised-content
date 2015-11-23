<?php


/**
 * @author marcus
 */
class LayeredFieldsExtension extends DataExtension {
    protected $layers;
    
    public function getLayers() {
        if (!$this->layers) {
            $this->layers = LayerManager::layers_for($this->owner);

            foreach ($this->layers as $l) {
                $others = $l->getLayers();
            }
            if ($this->owner && $this->owner->ID) {

            }
        }

        return $this->layers;
        
        return $allLayers;
    }

    public function updateCMSFields(\FieldList $fields)
    {
        $layers = $this->owner->getLayers();

        foreach ($layers as $l) {
//            $fields->addFieldsToTab($l->getName(), $l->getCMSFields());
            $fields->addFieldsToTab('Root.Added', $l->getCMSFields());
        }
    }
}
