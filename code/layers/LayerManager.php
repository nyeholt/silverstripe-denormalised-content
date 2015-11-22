<?php

/**
 * @author marcus
 */
class LayerManager
{
    const FIELD_SEPARATOR = '__';

    protected static $_cache_db        = array();
    protected static $_cache_layers_db = array();

    public static function layer_db($classType, $layerName, $fieldName = null)
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

                $layersDb = self::db_for_layers($class);
                foreach ($layersDb as $lName => $lTitle) {
                    $dbItems[$layerName.self::FIELD_SEPARATOR.$lName] = $lTitle;
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

        return $items;
    }

    public static function layer_relationships($classType, $layerName, $fieldName = null)
    {
        // and now, add in those from any related items
        $related = Config::inst()->get($class, 'virtual_relations', Config::UNINHERITED);
        if ($related) {
            foreach ($related as $rel) {
                
            }
        }
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

    public static function layers_for($type)
    {
        if (is_object($type)) {
            $type = get_class($type);
        }
        $layers = ArrayList::create();
        $def    = Config::inst()->get($type, 'virtual_layers');
        if ($def) {
            foreach (Config::inst()->get($type, 'virtual_layers') as $id => $config) {
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
                    $layer     = $type::create($layerName);
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
            $scaffolder = FormScaffolder::create($layer);
            $scaffolded = $scaffolder->getFieldList();
            $fullList->merge($scaffolded);
        }
        return $fullList;
    }
}