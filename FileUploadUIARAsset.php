<?php
/**
 * @copyright Copyright (c) 2013 2amigOS! Consulting Group LLC
 * @link http://2amigos.us
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 */
namespace dosamigos\fileupload;

use yii\web\AssetBundle;

/**
 * FileUploadUIARAsset
 *
 * @author Andrew Blake <admin@newzealandfishing.com>
 * @package dosamigos\fileupload
 */
class FileUploadUIARAsset extends AssetBundle
{
    public $sourcePath = '@vendor/';

    public $css = [
        'bower/jquery-file-upload/css/jquery.fileupload.css',
        '2amigos/yii2-file-upload-widget/assets/css/fileuploaduiar.css',
    ];

    public $js = [
        'bower/jquery-file-upload/js/vendor/jquery.ui.widget.js',
        'bower/blueimp-tmpl/js/tmpl.min.js',
        'bower/blueimp-load-image/js/load-image.all.min.js',
        'bower/blueimp-canvas-to-blob/js/canvas-to-blob.js',
        'bower/jquery-file-upload/js/jquery.iframe-transport.js',
        'bower/jquery-file-upload/js/jquery.fileupload.js',
        'bower/jquery-file-upload/js/jquery.fileupload-process.js',
        'bower/jquery-file-upload/js/jquery.fileupload-image.js',
        'bower/jquery-file-upload/js/jquery.fileupload-audio.js',
        'bower/jquery-file-upload/js/jquery.fileupload-video.js',
        'bower/jquery-file-upload/js/jquery.fileupload-validate.js',
        'bower/jquery-file-upload/js/jquery.fileupload-ui.js',
        '2amigos/yii2-file-upload-widget/assets/js/jquery.fileuploaduiar.js',

    ];

    public $depends = [
        'yii\web\JqueryAsset',
        'yii\bootstrap\BootstrapPluginAsset',
    ];
} 