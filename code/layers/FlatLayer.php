<?php

/**
 * @author marcus
 */
class FlatLayer extends ViewableData
{
    private static $source_vals       = array('ID', 'LastEdited', 'Created');
    private static $virtual_db        = array(
        'Title' => 'Varchar(255)',
    );
    private static $virtual_layers    = array();
    private static $virtual_has_one   = array();
    private static $virtual_many_many = array();
    protected $dataSource             = null;
    protected $name;
    protected $config                 = array();
    protected $realisedName;
    protected $layers;

    public function __construct($name, $dataSource)
    {
        $this->name         = $name;
        $this->realisedName = $name;
        $this->dataSource   = $dataSource;
        parent::__construct();
    }

    public function getLayers()
    {
        if (!$this->layers) {
            $this->layers = LayerManager::layers_for($this, $this->dataSource);
        }

        return $this->layers;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getFriendlyName()
    {
        return preg_replace('/_(\d+)/', '', $this->name);
    }

    public function setRealisedName($path)
    {
        $this->realisedName = $path;
    }

    public function getRealisedName()
    {
        return $this->realisedName;
    }

    public function getCMSFields()
    {
        $scaffolder                   = FormScaffolder::create($this);
        $scaffolder->includeRelations = true;
        $scaffolder->ajaxSafe         = true;
        
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

    public function fullFieldName($field)
    {
        return $this->realisedName.LayerManager::FIELD_SEPARATOR.$field;
    }

    public function fieldLabel($field)
    {
        $bits = explode(LayerManager::FIELD_SEPARATOR, $field);
        return array_pop($bits);
    }

    /**
     * Proxy the field requests to the underlying data source
     *
     * @param mixed $property
     * @return mixed
     */
    public function __get($property)
    {
        if ($this->dataSource && $this->dataSource->ID) {
            // check for the fully realised field name
            $name = strpos($property, LayerManager::FIELD_SEPARATOR) ?
                $property :
                $this->realisedName.LayerManager::FIELD_SEPARATOR.$property;

            $val = $this->dataSource->__get($name);
            if ($val) {
                return $val;
            }

            // check has_one and many_many
            if ($this->dataSource->hasMethod($name)) {
                return $this->dataSource->$name();
            }

            // otherwise, just send the base property reference through
            $val = $this->dataSource->__get($property);
            if ($val) {
                return $val;
            }
        }
        return parent::__get($property);
    }

    public function __set($property, $value)
    {
        if ($this->dataSource && $this->dataSource->ID) {
            $name = strpos($property, LayerManager::FIELD_SEPARATOR) ?
                $property :
                $this->realisedName.LayerManager::FIELD_SEPARATOR.$property;

            $this->dataSource->$name = $value;
        }
    }

    public function __call($method, $arguments)
    {
        if ($this->dataSource && $this->dataSource->ID) {

            $name = $this->realisedName.LayerManager::FIELD_SEPARATOR.$method;
            if ($this->dataSource->hasMethod($name)) {
                return $this->dataSource->__call($name, $arguments);
            }

            if ($this->dataSource->hasMethod($method)) {
                return $this->dataSource->__call($method, $arguments);
            }
        }

        parent::__call($method, $arguments);
    }

    public function getComponent($componentName)
    {
        if ($this->dataSource && $this->dataSource->ID) {
            $name = strpos($componentName, LayerManager::FIELD_SEPARATOR) ?
                $componentName :
                $this->realisedName.LayerManager::FIELD_SEPARATOR.$componentName;

            return $this->dataSource->getComponent($name);
        }
    }
    /**
     * DataObject mimicking below. Be _VERY_ careful about what you change down here!
     */
    protected $dbFields;

    public function db($fieldName = null)
    {
        if (!$this->dbFields) {
            $this->dbFields = LayerManager::layer_db(get_class($this), $this->getRealisedName());
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

    public function many_many($component = null)
    {
        $spec = LayerManager::layer_relationships(get_class($this), $this->name);
        $rels = $spec['many_many'];

        if ($component && isset($rels[$component])) {
            return $rels[$component];
        }
        return $rels;
    }

    public function has_many()
    {
        
    }

    /**
     */
    public function dbObject($fieldName)
    {
        if ($helper = $this->db($fieldName)) {
            $obj = Object::create_from_string($helper, $fieldName);
            $obj->setValue($this->$fieldName, $this->dataSource, false);
            return $obj;

            // Special case for has_one relationships
        } else if (preg_match('/ID$/', $fieldName) && $this->has_one(substr($fieldName, 0, -2))) {
            $val = $this->$fieldName;
            return DBField::create_field('ForeignKey', $val, $fieldName, $this);
        }
    }
}