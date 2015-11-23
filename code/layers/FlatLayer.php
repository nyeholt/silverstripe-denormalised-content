<?php

/**
 * @author marcus
 */
class FlatLayer extends ViewableData
{

    private static $virtual_db = array(
        'Title' => 'Varchar(255)',
    );
    private static $virtual_layers = array();
    private static $virtual_has_one = array();
    private static $virtual_many_many = array();
    
    protected $dataSource = null;

    protected $name;
    
    protected $config = array();

    protected $realisedName;

    protected $layers;

    public function __construct($name, $dataSource)
    {
        $this->name = $name;
        $this->realisedName = $name;
        $this->dataSource = $dataSource;
        parent::__construct();
    }

    public function setRealisedName($path) {
        $this->realisedName = $path;
    }

    public function getRealisedName() {
        return $this->realisedName;
    }

    public function getLayers() {
        if (!$this->layers) {
            $this->layers = LayerManager::layers_for($this, $this->dataSource);

            // can we inflate? Do so if we can
//            if ($this->dataSource && $this->dataSource->ID) {
//                $definedFields = $this->db();
//                foreach ($definedFields as $name => $type) {
//                    //grab out of our data source if specified
//                    $v = $this->dataSource->$name;
//                }
//            }
        }
        
        return $this->layers;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getCMSFields()
    {
        $scaffolder = FormScaffolder::create($this);
        return $scaffolder->getFieldList();
    }


    public function setConfig($name, $value)
    {
        $this->config[$name] = $value;
    }

    public function getConfig($name, $default = null)
    {
        if (isset($this->config[$name])) {
            return $this->config[$name];
        }
        return $default;
    }


    public function fieldLabel($field)
    {
        $bits = explode(LayerManager::FIELD_SEPARATOR, $field);
        return array_pop($bits);
    }


    public function loadData() {
        
    }

    public function __get($property)
    {
        if ($this->dataSource && $this->dataSource->ID) {
            $name = $this->realisedName . LayerManager::FIELD_SEPARATOR . $property;
            $val = $this->dataSource->__get($name);
            if ($val) {
                return $val;
            }

            // check has_one and many_many
            if ($this->dataSource->hasMethod($name)) {
                return $this->dataSource->$name();
            }
        }
        return parent::__get($property);
    }
    
    public function __set($property, $value)
    {
        if ($this->dataSource && $this->dataSource->ID) {
            $name = $this->realisedName . LayerManager::FIELD_SEPARATOR . $property;
            $this->dataSource->$name = $value;
        }
    }

    public function __call($method, $arguments)
    {
        $rel = $this->has_one($method);

        parent::__call($method, $arguments);
    }

    public function getComponent($componentName) {

        $o = 1;

        return;
    }

    /**
     * DataObject mimicking below. Be _VERY_ careful about what you change down here!
     */

    protected $dbFields;

    public function db($fieldName = null)
    {
        if (!$this->dbFields) {
            $this->dbFields = LayerManager::layer_db(get_class($this), $this->name);
        }
        if ($fieldName) {
            return isset($this->dbFields[$fieldName]) ? $this->dbFields[$fieldName] : null;
        }
        return $this->dbFields;
    }

    public function has_one($component = null)
    {
        $spec = LayerManager::layer_relationships(get_class($this), $this->name);
        $rels = $spec['has_one'];

        if ($component && isset($rels[$component])) {
            return $rels[$component];
        }
        return $rels;
    }

    public function many_many($component = null) {
        $spec = LayerManager::layer_relationships(get_class($this), $this->name);
        $rels = $spec['many_many'];

        if ($component && isset($rels[$component])) {
            return $rels[$component];
        }
        return $rels;
    }


    /**
     */
    public function dbObject($fieldName)
    {
        if ($helper = $this->db($fieldName)) {
            $obj = Object::create_from_string($helper, $fieldName);
            $obj->setValue($this->$fieldName, $this->record, false);
            return $obj;

            // Special case for has_one relationships
        } else if (preg_match('/ID$/', $fieldName) && $this->has_one(substr($fieldName, 0, -2))) {
            $val = $this->$fieldName;
            return DBField::create_field('ForeignKey', $val, $fieldName, $this);
        }
    }

}
