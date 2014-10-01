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
class FileUploadUIAR extends FileUploadUI
{
    /**
     * @var bool whether to use the Bootstrap Gallery on the images or not
     */
    public $gallery = false;
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
     * @inheritdoc
     */
    public function init()
    {
        $this->fieldOptions['multiple'] = true;
        $this->fieldOptions['id'] = ArrayHelper::getValue($this->options, 'id');
        parent::init();
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

        FileUploadUIAsset::register($view);

        $options = Json::encode($this->clientOptions);
        $id = $this->options['id'];

		$js = <<<HERE
			// set up for uploading
			jQuery('#$id').fileupload($options);
			
			// this from http://stackoverflow.com/questions/19807361/uploading-multiple-files-asynchronously-by-blueimp-jquery-fileupload
			// to stop seperate requests for each file
			var filesList = [], paramNames = [], elem = $("form");
			file_upload = $('#$id').on("fileuploadadd", function(e, data){
				filesList.push(data.files[0]);
				paramNames.push(e.delegatedEvent.target.name);
			});

			// deal with click events ourselves on the main save button - there is also a hidden save button if no files
			$('#$id .fileupload-buttonbar .start').on('click',function () { 
				// if there are some files in our upload q
				if(filesList.length) {
					// send them programatically
					file_upload.fileupload('send', {files:filesList, paramName: paramNames});
				} else {
					$('#activFormSave').click();
				}
			});

			// block submit thru file upload - will happen by direct call above - even though would have though taking over click would have
			// done this it does so guiessing send calls submit first or something and perhaps empties the q
			$('#$id').bind('fileuploadsubmit', function (e, data) {
				e.preventDefault();
			});
			
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


			// set call back for when upload process done - to block removal of the file input in case of error
			// basically we do want to show file upload errors but return others to there pre-upload state
			$('#$id').bind('fileuploaddone', function (e, data) {
				e.preventDefault();
				// if there are errors there will be no redirect member in our json response from the UploadHandler
				// allow redirect only if no errors in form data
				if(data.result.hasOwnProperty('redirect')) {
					window.location.href = data.result.redirect;
				}
				else {
					// loop thru each of the rows in our fileupload widget
					$('tr.template-upload.fade.in').each(function(index) {
						var file = data.result.hasOwnProperty('files') ? data.result.files[index] : null;
						var error;
						// if an error was returned from the server
						if(file && file.hasOwnProperty('error')) {
							// remove the progress bar
							$('.progress', this).remove();
							// set an error for display
							error = file.error;
						}
						// am assuming any empty result can only come from create within updateAction where ActiveRecord:save() failed
						// but will get into this else as well if no error - discarding empty result error in jqeury.fileupload-ui.js done
						// handler
						else {
							// reset the progress bar
							$('.progress-bar.progress-bar-success', $('.progress', this).attr('aria-valuenow', 0)).attr('style', 'width: 0%;');
							// enable the start button - even though it is hidden
							$('button.btn.btn-primary.start', this).prop('disabled', false);
						}
						
						if(error) {
							// display error
							$('.error.text-danger', this).html(error);
						}
					});

					// if form errors
					if(data.result.hasOwnProperty('activeformerrors') && !data.result.activeformerrors.hasOwnProperty('length')) {
						// use yii to deal with the error
						$('#$id').data('yiiActiveForm').submitting = true;
						$('#$id').yiiActiveForm('updateInputs', data.result.activeformerrors);
					}
			
					// if non attribute form errors - e.g. trigger reported errors, fk constraint errors etc
					$('#nonattributeerrors').html(data.result.hasOwnProperty('nonattributeerrors') ? data.result.nonattributeerrors : '');
				}
			});

HERE;

        $view->registerJs($js);
    }
} 
