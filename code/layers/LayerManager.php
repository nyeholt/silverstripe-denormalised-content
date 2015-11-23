<?php

/**
 * @author marcus
 */
class LayerManager
{
    const FIELD_SEPARATOR = '__';

    protected static $_cache_db          = array();
    protected static $_cache_rels        = array();
    protected static $_cache_layers_db   = array();
    protected static $_cache_layers_rels = array();

    public static function init_layers()
    {
        $allTypes = func_get_args();
        foreach ($allTypes as $classType) {
            // In an ideal world, we'd recursively generate a collated set of DB Fields from all
            // of our related layers. HOWEVER - we can't create an object of type $classType, because
            // the act of doing so is what binds all the extra methods pertinent to our relationships - which haven't
            // been defined yet. So we _need_ to do it using just the string representation of the
            // classname instead.
            
//            $sng = singleton($classType);
//            foreach ($sng->getLayers() as $l) {
//                $allDb = array_merge($allDb, $l->collatedDb());
//            }
            $allDb   = LayerManager::db_for_layers($classType);
            Config::inst()->update($classType, 'db', $allDb);
            $extraRels = LayerManager::rels_for_layers($classType);
            if (count($extraRels['has_one'])) {
                Config::inst()->update($classType, 'has_one', $extraRels['has_one']);
            }
            if (count($extraRels['many_many'])) {
                Config::inst()->update($classType, 'many_many', $extraRels['many_many']);
            }
        }
    }

    public static function layer_db($classType, $layerName, $fieldName = null, $includeLayers = true)
    {
        $classes = ClassInfo::ancestry($classType, false);

        // If we're looking for a specific field, we want to hit subclasses first as they may override field types
        if ($fieldName) {
            $classes = array_reverse($classes);
        }

        $items = array();
        foreach ($classes as $class) {
            $cacheKey = $class.'_'.$layerName;
            $dbItems  = array();
            if (isset(self::$_cache_db[$cacheKey])) {
                $dbItems = self::$_cache_db[$cacheKey];
            } else {
                $rawItems = (array) Config::inst()->get($class, 'virtual_db', Config::UNINHERITED);
                foreach ($rawItems as $key => $val) {
                    $dbItems[$layerName.self::FIELD_SEPARATOR.$key] = $val;
                }

                if ($includeLayers) {
                    // check for any contained layers in the class, and load for them too
                    $layersDb = self::db_for_layers($class);
                    foreach ($layersDb as $lName => $lTitle) {
                        $dbItems[$layerName.self::FIELD_SEPARATOR.$lName] = $lTitle;
                    }
                }
                
                self::$_cache_db[$cacheKey] = $dbItems;
            }

            if ($fieldName && isset($dbItems[$fieldName])) {
                return $dbItems[$fieldName];
            }

            // Validate the data
            foreach ($dbItems as $k => $v) {
                if (!is_string($k) || is_numeric($k) || !is_string($v)) {
                    user_error("$class::\$db has a bad entry: "
                        .var_export($k, true)." => ".var_export($v, true).".  Each map key should be a"
                        ." property name, and the map value should be the property type.", E_USER_ERROR);
                }
            }

            $items = isset($items) ? array_merge((array) $items, $dbItems) : $dbItems;
        }
        if (!$fieldName) {
            return $items;
        }
    }

    public static function layer_relationships($classType, $layerName, $fieldName = null)
    {
        $cacheKey = $classType.'_'.$layerName;

        if (isset(self::$_cache_rels[$cacheKey])) {
            return self::$_cache_rels[$cacheKey];
        }

        $allRels = array('has_one' => array(), 'many_many' => array());

        // and now, add in those from any related items
        $related = Config::inst()->get($classType, 'virtual_has_one');
        if ($related) {
            foreach ($related as $name => $relatedType) {
                $allRels['has_one'][$layerName.self::FIELD_SEPARATOR.$name] = $relatedType;
            }
        }

        $related = Config::inst()->get($classType, 'virtual_many_many');
        if ($related) {
            foreach ($related as $name => $relatedType) {
                $allRels['many_many'][$layerName.self::FIELD_SEPARATOR.$name] = $relatedType;
            }
        }

        $inner = self::rels_for_layers($classType);
        foreach ($inner['has_one'] as $name => $relatedType) {
            $allRels['has_one'][$layerName.self::FIELD_SEPARATOR.$name] = $relatedType;
        }
        foreach ($inner['many_many'] as $name => $relatedType) {
            $allRels['many_many'][$layerName.self::FIELD_SEPARATOR.$name] = $relatedType;
        }


        self::$_cache_rels[$cacheKey] = $allRels;
        return $allRels;
    }

    public static function db_for_layers($type)
    {
        if (isset(self::$_cache_layers_db[$type])) {
            return self::$_cache_layers_db[$type];
        }
        $layers = self::layers_for($type);
        $fields = array();

        foreach ($layers as $l) {
            $db = $l->db();
            foreach ($db as $name => $fieldType) {
                $fields[$name] = $fieldType;
            }
        }

        self::$_cache_layers_db[$type] = $fields;
        return $fields;
    }

    public static function rels_for_layers($type)
    {
        if (is_object($type)) {
            $type = get_class($type);
        }
        if (isset(self::$_cache_layers_rels[$type])) {
            return self::$_cache_layers_rels[$type];
        }
        $layers = self::layers_for($type);
        $rels   = array('has_one' => array(), 'many_many' => array());

        foreach ($layers as $l) {
            $ones = $l->hasOne();
            foreach ($ones as $name => $relatedType) {
                $rels['has_one'][$name] = $relatedType;
            }

            $many = $l->manyMany();
            foreach ($many as $name => $relatedType) {
                $rels['many_many'][$name] = $relatedType;
            }
        }

        self::$_cache_layers_rels[$type] = $rels;
        return $rels;
    }

    /**
     *
     * @param type $type
     * @param DataObject $context
     *          The data object that actually contains the layers' content
     * @return type
     */
    public static function layers_for($type, $context = null)
    {
        $parentLayer = null;
        if (is_object($type)) {
            if ($type instanceof FlatLayer) {
                $parentLayer = $type;
            }
            if (!$context && $type instanceof DataObject) {
                $context = $type;
            }
            $type = get_class($type);
        }
        $layers = ArrayList::create();
        $def    = Config::inst()->get($type, 'virtual_layers');
        if ($def) {
            foreach ($def as $id => $config) {
                if (!is_array($config)) {
                    $config = array('type' => $config);
                }
                if (!isset($config['name'])) {
                    $config['name'] = $id;
                }
                if (!isset($config['number'])) {
                    $config['number'] = 1;
                }
                $type = $config['type'];
                for ($i = 1; $i <= $config['number']; $i++) {
                    $layerName = $config['name'].'_'.$i;
                    $layer     = $type::create($layerName, $context);
                    if ($parentLayer) {
                        $layer->setRealisedName($parentLayer->getRealisedName() . self::FIELD_SEPARATOR . $layerName);
                    }
                    $layers->push($layer);
                }
            }
        }

        return $layers;
    }

    public static function layered_form_fields_for($item)
    {
        $layers = self::layers_for($item);

        $fullList = FieldList::create();
        foreach ($layers as $layer) {
            $fullList->merge($layer->getCMSFields());
        }
        return $fullList;
    }
}