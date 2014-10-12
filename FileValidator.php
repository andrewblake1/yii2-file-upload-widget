<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */
namespace dosamigos\fileupload;

use yii\web\UploadedFile;

/**
 * @inheritdoc
 * 
 * Validates array of files but skips over them if they are not UploadedFile instances i.e. placeholders
 * for existing files. Existing files don't need to be validated individually however the files array needs to have
 * the correct number of elements for maxFiles etc to be of use.
 *
 * @author Andrew Blake <admin@newzealandfishing.com>
 * @package dosamigos\fileupload
 */
class FileValidator extends \yii\validators\FileValidator
{

    /**
     * @inheritdoc. Skips over existing files - when validating i.e. not an UploadedFile as already on server
     */
    public function validateAttribute($object, $attribute)
    {
        if ($this->maxFiles > 1) {
            $files = $object->$attribute;
            if (!is_array($files)) {
                $this->addError($object, $attribute, $this->uploadRequired);

                return;
            }
            foreach ($files as $i => $file) {
                // AB altered here
                if ($file instanceof UploadedFile && $file->error == UPLOAD_ERR_NO_FILE) {
                    unset($files[$i]);
                }
            }
            // AB altered here
//            $object->$attribute = array_values($files);
            if (empty($files)) {
                $this->addError($object, $attribute, $this->uploadRequired);
            }
            if (count($files) > $this->maxFiles) {
                $this->addError($object, $attribute, $this->tooMany, ['limit' => $this->maxFiles]);
            } else {
                foreach ($files as $file) {
                    // AB altered here
                    if ($file instanceof UploadedFile) {
                        $result = $this->validateValue($file);
                        if (!empty($result)) {
                            $this->addError($object, $attribute, $result[0], $result[1]);
                        }
                    }
                }
            }
        // AB altered here
        } elseif ($file instanceof UploadedFile) {
            $result = $this->validateValue($object->$attribute);
            if (!empty($result)) {
                $this->addError($object, $attribute, $result[0], $result[1]);
            }
        }
    }

}
