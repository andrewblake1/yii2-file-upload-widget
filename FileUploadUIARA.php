<?php
/**
 * @copyright Copyright (c) 2013 2amigOS! Consulting Group LLC
 * @link http://2amigos.us
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 */
namespace dosamigos\fileupload;

use dosamigos\gallery\GalleryAsset;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\web\View;

/**
 * FileUploadUI
 *
 * Widget to render the jQuery File Upload UI plugin as shown in
 * [its demo](http://blueimp.github.io/jQuery-File-Upload/index.html)
 *
 * @author Antonio Ramirez <amigo.cobos@gmail.com>
 * @link http://www.ramirezcobos.com/
 * @link http://www.2amigos.us/
 * @package dosamigos\fileupload
 */
class FileUploadUIARA extends FileUploadUIAR
{
    /**
     * @var bool whether to use the Bootstrap Gallery on the images or not
     */
    public $gallery = false;
    /**
     * @var string the form view path to render the JQuery File Upload UI
     */
    public $formView = '@vendor/2amigos/yii2-file-upload-widget/views/formUIARA';
    /**
     * @var string the upload view path to render the js upload template
     */
    public $uploadTemplateView = '@vendor/2amigos/yii2-file-upload-widget/views/uploadUIAR';
    /**
     * @var string the download view path to render the js download template
     */
    public $downloadTemplateView = '@vendor/2amigos/yii2-file-upload-widget/views/downloadUIAR';

    /**
     * Registers required script for the plugin to work as jQuery File Uploader UI
     */
    public function registerClientScript()
    {
        $view = $this->getView();

        if ($this->gallery) {
            GalleryAsset::register($view);
        }

		$fileUploadTarget = '#' . str_replace('[]', '', $this->name) . '-files-container';
		FileUploadUIAsset::register($view);

		// per target in doc ready
        $options = Json::encode($this->clientOptions);
        $view->registerJs(";$('$fileUploadTarget').fileupload($options);", View::POS_READY, $fileUploadTarget);

		// once on window load - using window load as the fileupload plugin needs ataching to target elements first before this code will work
		$jsLoad = <<<HERE
			// custom getFilesFromResponse due to possible multiple widgets
			$('$fileUploadTarget').fileupload(
				'option',
				'getFilesFromResponse',
				function (data) {
					if (data.result && $.isArray(data.result['{$this->name}'])) {
						return data.result['{$this->name}'];
					}
					return [];
				}
			);
			
			// Load existing files
			$('$fileUploadTarget').each(function() {
				$(this).addClass('fileupload-processing');
				$.ajax({
					// Uncomment the following to send cross-domain cookies:
					//xhrFields: {withCredentials: true},
					url: '{$this->urlGetExistingFiles}',
					dataType: 'json',
					context: $(this)[0]
				}).always(function () {
					$(this).removeClass('fileupload-processing');
				}).done(function (result) {
					$(this).fileupload('option', 'done')
						.call(this, $.Event('done'), {result: result});
				});
			});
HERE;
		
		// this needs to come after the fileUpload attachement to the file inputs which are in doc ready
		// the key makes this only once overall for the page/model
        $view->registerJs($jsLoad, View::POS_LOAD, $fileUploadTarget);
    }
} 
