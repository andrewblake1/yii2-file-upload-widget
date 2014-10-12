<?php
/**
 * @copyright Andrew Blake
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 */
namespace common\components;

use Yii;
use yii\web\UploadedFile;
use dosamigos\fileupload\File;

/**
 * FileActiveRecordTrait adds file upload functionality to an ActiveRecord. To
 * be used in conjuction with FileControllerTrait and FileUploadUIAR,
 * a Yii2 Widget encapsulating http://blueimp.github.io/jQuery-File-Upload/
 *
 * @author Andrew Blake <admin@newzealandfishing.com>
 * @package dosamigos\fileupload
 */
trait FileActiveRecordTrait
{

    /**
     * @inheritdoc. Unobtrusively clean out any uploaded files related to a
     * model deleted from the database. Ideally a database trigger would do
     * this but not possible in MySQL hence this is the best can do. Potentially
     * a slightly better option could be to add an after delete trigger to each
     * table and have this write the model name and pk to another table that
     * serves as a feed to a cron job that handles the deletes on s3.
     * Cascading deletes unnessary due to the path tree structure
     * of the files in this application
     * @param type $event
     */
    public function afterDelete()
    {
        if ($return = parent::delete()) {
            // if there is no soft delete
            if (!isset(static::getTableSchema()->columns['deleted'])) {
                // hard deleted so remove this branch of files
                Yii::$app->resourceManager->deleteMatching($prefix);
            }
        }

        return $return;
    }

    /**
     * Load file info array into a model attribute, containing potentially existing
     * loaded files, new files, and perhaps ignore some
     * $this->path would need to be created for other projects
     * @param string $attribute the model attribute to load files into
     * @param array $delete a list of files to ignore for this attribute i.e. don't load existing info for
     */
    public function loadFileAttribute($attribute, $ignore = [])
    {
        // get the attributes path on storage of if attribute is files then files are against whole model
        $path = $this->path . '/' . $attribute;

        // get existing from storage - just the names and details but not the files
        $files = Yii::$app->resourceManager->listFiles($path . '/' . File::LARGE_IMAGE . '/');

        // handle posted deletes
        foreach ($files as $key => $file) {
            // if file is requested to be deleted
            if (in_array($file['name'], $ignore)) {
                // remove from our files array
                unset($files[$key]);
            }
        }

        $this->$attribute = array_merge($files, UploadedFile::getInstancesByName($attribute));
    }

    /**
     * Load file info array into a model attributes, containing potentially existing
     * loaded files, new files, and perhaps ignore some
     * @param array $delete a list of files to ignore i.e. don't load existing info for
     */
    public function loadFileAttributes($ignore = [])
    {
        foreach ($this->fileAttributes as $attribute) {
            $this->loadFileAttribute($attribute, isset($ignore[$attribute]) ? $ignore[$attribute] : []);
        }
    }

}

?>
