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
}