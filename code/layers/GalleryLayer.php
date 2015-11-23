<?php

/**
 * Description of GalleryLayer
 *
 * @author marcus
 */
class GalleryLayer extends FlatLayer
{
    public static $virtual_many_many = array(
        'Images'        => 'Image',
    );

    public function getCMSFields()
    {
        $fields = FieldList::create(array(
            UploadField::create($this->fullFieldName('Images'), 'Images', $this->Images())
        ));
        return $fields;
    }
}