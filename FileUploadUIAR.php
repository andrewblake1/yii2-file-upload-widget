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
    public $formView = '@vendor/2amigos/yii2-file-upload-widget/views/formUIAR';
    /**
     * @var string the upload view path to render the js upload template
     */
    public $uploadTemplateView = '@vendor/2amigos/yii2-file-upload-widget/views/uploadUIAR';
    /**
     * @var string the download view path to render the js download template
     */
    public $downloadTemplateView = '@vendor/2amigos/yii2-file-upload-widget/views/downloadUIAR';
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

		$js = <<<HERE
			// set up for uploading
			jQuery('#$id').fileupload($options);
			
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

			// set call back for when upload process finished
			$('#$id').bind('fileuploadfinished', function (e, data) {
				// allow redirect only if no errors in form data
				if(data.result.hasOwnProperty('redirect')) {
					window.location.href = data.result.redirect;
				}
				// otherwise if form errors
				else if(data.result.hasOwnProperty('activeformerrors')) {
					// use yii to deal with the error
					$('#$id').data('yiiActiveForm').submitting = true;
					$('#$id').yiiActiveForm('updateInputs', data.result.activeformerrors);
				}
			});

			
			// allow submit even without new files - which jquery file upload blocks by checking for file before sumitting
			$('#$id .fileupload-buttonbar .start').on('click',function () { 
				// allow update without having to upload - this courtesy of plugin author
				var form = $('form').first();
				if (!form.find('.files .start').length) {
					// submit the normal ActiveForm way - no files being uploaded i.e. just the form data
					$('#$id').submit();
				}
			});
			
			// set call back for when upload process done - to block removal of the file input in case of error
			// basically we do want to show file upload errors but return others to there pre-upload state
			$('#$id').bind('fileuploaddone', function (e, data) {
				// if there are errors there will be no redirect key in our json response from the UploadHandler
				if(!data.result.hasOwnProperty('redirect')) {
					// prevent the normal processing which will remove the file inputs for good. We want to keep them and put them back
					// to a state where the good ones can be reused without the user having to re-select
					e.preventDefault();
			
					// loop thru each of the rows in our fileupload widget
					$('tr.template-upload.fade.in').each(function(index) {
						var file = data.result.files[index];
						var error;
						// if upload was successful for this file
						if(file) {
			
							// if there was an error returned from the server
							if(file.hasOwnProperty('error')) {
								// remove the progress bar
								$('.progress', this).remove();
								// set an error for display
								error = file.error;
							}
							else {
								// reset the progress bar
								$('.progress-bar.progress-bar-success', $('.progress', this).attr('aria-valuenow', 0)).attr('style', 'width: 0%;');
								// enable the start button - even though it is hidden
								$('button.btn.btn-primary.start', this).prop('disabled', false);
							}
						}
						else {
							error = 'Empty file upload result';
						}
						
						if(error) {
							// display error
							$('.error.text-danger', this).html(error);
						}
					});
				}
			});

HERE;

        $view->registerJs($js);
    }
} 
