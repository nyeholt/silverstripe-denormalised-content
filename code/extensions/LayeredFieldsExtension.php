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

            if (!$this->layers) {
                $this->layers = ArrayList::create();
            }
        }

        return $this->layers;
    }

    public function ShowLayers() {
        $out = '';
        foreach ($this->getLayers() as $l) {
            $out .= $l->forTemplate();
        }
        return $out;
    }

    public function getLayer($layer) {
        $all = $this->getLayers();
        foreach ($all as $l) {
            if ($l->getFriendlyName() == $layer) {
                return $layer;
            }
        }
    }

    public function updateCMSFields(\FieldList $fields)
    {
        $layers = $this->owner->getLayers();

        foreach ($layers as $l) {
            $fields->addFieldsToTab('Root.' . $l->getFriendlyName(), $l->getCMSFields());
        }
    }
    
}
