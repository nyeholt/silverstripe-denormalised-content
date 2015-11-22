<?php

/**
 * @author marcus
 */
class ContentLayer extends FlatLayer
{
    private static $virtual_db = array(
        'Content'         => 'HTMLText',
    );
}