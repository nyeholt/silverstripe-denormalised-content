<?php

/**
 * @author marcus
 */
class TitledImageContentLayer extends ContentLayer
{
    private static $virtual_db = array(
        'URLText' => 'Varchar',
        'URL' => 'Varchar',
    );
    private static $virtual_relations = array(
        'Image' => 'Image',
    );
}