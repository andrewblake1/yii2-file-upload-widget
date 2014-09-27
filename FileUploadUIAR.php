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
class FileUploadUIAR extends FileUpload
{
    /**
     * @var bool whether to use the Bootstrap Gallery on the images or not
     */
    public $gallery = true;
    /**
     * @var array the HTML attributes for the file input tag.
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $fieldOptions = [];
    /**
     * @var string the ID of the upload template, given as parameter to the tmpl() method to set the uploadTemplate option.
     */
    public $uploadTemplateId;
    /**
     * @var string the ID of the download template, given as parameter to the tmpl() method to set the downloadTemplate option.
     */
    public $downloadTemplateId;
    /**
     * @var string the form view path to render the JQuery File Upload UI
     */
    public $formView = '@vendor/2amigos/yii2-file-upload-widget/views/form';
    /**
     * @var string the upload view path to render the js upload template
     */
    public $uploadTemplateView = '@vendor/2amigos/yii2-file-upload-widget/views/upload';
    /**
     * @var string the download view path to render the js download template
     */
    public $downloadTemplateView = '@vendor/2amigos/yii2-file-upload-widget/views/download';
    /**
     * @var string the gallery
     */
    public $galleryTemplateView = '@vendor/2amigos/yii2-file-upload-widget/views/gallery';


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $fieldOptions = [
			'multiple' => true,
			'id' => ArrayHelper::getValue($this->options, 'id'),
		];
		$this->fieldOptions = $this->fieldOptions + $fieldOptions;
 
        $options = [
			'id' => $this->options['id'] . '-form',
			'enctype' => 'multipart/form-data',
			'uploadTemplateId' => $this->uploadTemplateId ? : '#template-upload',
			'downloadTemplateId' => $this->downloadTemplateId ? : '#template-download',
		];
 		$this->options = $this->options + $options;
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        echo $this->render($this->uploadTemplateView);
        echo $this->render($this->downloadTemplateView);
        echo $this->render($this->formView);

        if ($this->gallery) {
            echo $this->render($this->galleryTemplateView);
        }

        $this->registerClientScript();
    }

    /**
     * Registers required script for the plugin to work as jQuery File Uploader UI
     */
    public function registerClientScript()
    {
        $view = $this->getView();

        if ($this->gallery) {
            GalleryAsset::register($view);
        }

        FileUploadUIARAsset::register($view);

        $options = Json::encode($this->clientOptions);
        $id = $this->options['id'];

        $js[] = "jQuery('#$id').fileupload($options);";
		
		// Would be great if blueimp would allow option when initializing to load existing files
		$js[] = <<<HERE
			// Load existing files:
			$('#$id').addClass('fileupload-processing');
			$.ajax({
				// Uncomment the following to send cross-domain cookies:
				//xhrFields: {withCredentials: true},
				url: $('#$id').fileupload('option', 'url'),
				dataType: 'json',
				context: $('#$id')[0]
			}).always(function () {
				$(this).removeClass('fileupload-processing');
			}).done(function (result) {
				$(this).fileupload('option', 'done')
					.call(this, $.Event('done'), {result: result});
			});
			
			// set call back for when upload process stops
			$('#$id').bind('fileuploadfinished', function (e, data)
			{
				// allow redirect only if no errors in form data
				if(data.result.hasOwnProperty('redirect')) {
					window.location.href = data.result.redirect;
				}
			});
			
HERE;
		
        if (!empty($this->clientEvents)) {
            foreach ($this->clientEvents as $event => $handler) {
                $js[] = "jQuery('#$id').on('$event', $handler);";
            }
        }
        $view->registerJs(implode("\n", $js));
    }
} 
