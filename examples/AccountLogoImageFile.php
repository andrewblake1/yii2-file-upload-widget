<?php

namespace common\models;

/**
 * @inheritdoc
 * This contains the rules for the "logo_image" file attribute for the "AccountActiveRecord" model.
 */
class AccountLogoImageFile extends \common\components\File
{

    public $privacy = self::ISPRIVATE;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['file'], 'file', 'extensions' => 'jpg, png', 'mimeTypes' => 'image/jpeg, image/png']
        ];
    }

}