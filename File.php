<?php
/**
 * @copyright Copyright (c) 2013 2amigOS! Consulting Group LLC
 * @link http://2amigos.us
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 */
namespace dosamigos\fileupload;

use Aws\S3\Enum\CannedAcl;
use yii\web\UploadedFile;
use dosamigos\fileupload\ImageHelper;
use Yii;

/**
 * File is the model for managing an UploadedFile
 *
 * @author Antonio Ramirez <amigo.cobos@gmail.com>
 * @author Andrew Blake <admin@newzealandfishing.com>
 * @package dosamigos\fileupload
 */
class File extends \yii\base\Model
{

    const LARGE_IMAGE = '400x400';
    const SMALL_IMAGE = '80x80';
    const ISPRIVATE = '+10 minutes';
    const ISPUBLIC = null;

    /**
     * @var UploadedFile|Null file attribute
     */
    public $file;
    public $name;
    public $basePath;
    public $privacy = self::ISPRIVATE;

    public function save()
    {
        if ($this->file && $this->file instanceof UploadedFile && !empty($this->file->tempName)) {
            $large = ImageHelper::saveTemporaryImage($this->file->tempName, $this->file->name, self::LARGE_IMAGE, $this->basePath);
            $small = ImageHelper::saveTemporaryImage($this->file->tempName, $this->file->name, self::SMALL_IMAGE, $this->basePath);

            $manager = Yii::$app->resourceManager;

            $options = [
                'Bucket' => $manager->bucket,
                'Key' => $this->basePath . '/' . self::LARGE_IMAGE . '/' . $this->file->name,
                'SourceFile' => $large,
                'ACL' => CannedAcl::PUBLIC_READ
            ];

            $manager->getClient()->putObject($options);

            $options['Key'] = $this->basePath . '/' . self::SMALL_IMAGE . '/' . $this->file->name;
            $options['SourceFile'] = $small;

            $manager->getClient()->putObject($options);

            @unlink($large);
            @unlink($small);

            $this->name = $this->file->name;
        }
    }

    public function getImageUrl($type = self::LARGE_IMAGE)
    {
        return Yii::$app->resourceManager->getUrl(
                $this->basePath . '/' . $type . '/' . $this->name, $this->privacy
        );
    }

    public function delete()
    {
        $manager = Yii::$app->resourceManager;
        $manager->delete($this->basePath . '/' . self::SMALL_IMAGE . '/' . $this->name);
        $manager->delete($this->basePath . '/' . self::LARGE_IMAGE . '/' . $this->name);
    }

    public function jqueryFileUploadResponse()
    {
        if (isset($file->hasErrors)) {
            // just the first error for now for simplicity
            return [
                'name' => $file->file->name,
                'size' => $file->file->size,
                "error" => $file->firstErrors['file']
            ];
        }

        return [
            'name' => $this->name = $this->file->name,
            'type' => $this->file->type,
            'size' => $this->file->size,
            'url' => $this->getImageUrl(),
            'thumbnailUrl' => $this->getImageUrl(File::SMALL_IMAGE),
            'deleteUrl' => $this->file->name,
            'deleteType' => 'POST'
        ];
    }

}
