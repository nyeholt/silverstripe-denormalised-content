<?php


/**
 * @author marcus
 */
class LayeredFieldsExtension extends Extension {
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
}
