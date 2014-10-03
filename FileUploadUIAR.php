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
        $this->options['id'] = $this->model->formName();	// form id

        $this->clientOptions['maxFileSize'] = 2000000;
		$this->clientOptions['filesContainer'] = '#' . str_replace('[]', '', $this->name) . '-files-container tbody.files';

        $this->fieldOptions['accept'] = 'image/*';
        $this->fieldOptions['multiple'] = true;
//        $this->fieldOptions['id'] = ArrayHelper::getValue($this->options, 'id');

        parent::init();

		unset($this->fieldOptions['id']); 
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

		$fileUploadTarget = '#' . str_replace('[]', '', $this->name) . '-files-container';
		FileUploadUIAsset::register($view);

		// this from http://stackoverflow.com/questions/19807361/uploading-multiple-files-asynchronously-by-blueimp-jquery-fileupload
		// to stop seperate requests for each file, split into our various suitable blocks
		// once in doc ready
		$view->registerJs(';var filesList = [], paramNames = [], elem = $("form");');
		
		// per target in doc ready
        $options = Json::encode($this->clientOptions);
        $view->registerJs(";$('$fileUploadTarget').fileupload($options);", View::POS_READY, $fileUploadTarget);
		
		// once on window load - using window load as the fileupload plugin needs ataching to target elements first before this code will work
		$jsLoad = <<<HERE
			// save reference to the file in a global array so that we can access later to send
			$('input[type="file"]').on("fileuploadadd", function(e, data){
				filesList.push(data.files[0]);
				paramNames.push(e.delegatedEvent.target.name);
			});

			// block submit thru file upload - will happen by direct call above - even though would have though taking over click would have
			// done this it does so guiessing send calls submit first or something and perhaps empties the q
			$('input[type="file"]').bind('fileuploadsubmit', function (e, data) {
				e.preventDefault();
			});

			// because we want to potentially return different arrays of files for different attributes we have different names for the file
			// input and this is the array key we want - not just 'files' as the default plugin implementation assumes
			// also note that this should only attach to our model level button which means might need a hidden one if only attribute uploads
			// could perhaps attach to the save button instead - i.e this fileupload attach to save rather than the file upload input as save will
			// be there only once for the whole view if any attributes or model allows files
// TODO need to iterate and deal with all files somehow and update all inputs - may need to trigger done or something against each input?
			$('input[name="files[]"]').fileupload(
				'option',
				'getFilesFromResponse',
				function (data) {
					if (data.result && $.isArray(data.result['files[]'])) {
						return data.result['files[]'];
					}
					return [];
				}
			);
			
			// deal with click events ourselves on the main save button - there is also a hidden save button if no files
			$('.fileupload-buttonbar .start').on('click',function () { 
				// if there are some files in our upload q
				if(filesList.length) {
					// send them programatically
					$('[name="files[]"]').fileupload('send', {files:filesList, paramName: paramNames});
				} else {
					// TODO try just a submit thru yiiActiveForm plugin - might not noeed the button and the click
					$('#activFormSave').click();
				}
			});

//need to change to our new targets							
			// Load existing files - loop thru all the file inputs which will have fileupload plugin capabilities
			$('input[type="file"]').each(function() {
				$(this).addClass('fileupload-processing');
				$.ajax({
					// Uncomment the following to send cross-domain cookies:
					//xhrFields: {withCredentials: true},
					url: $(this).fileupload('option', 'url'),
					dataType: 'json',
					context: $(this)[0]
				}).always(function () {
					$(this).removeClass('fileupload-processing');
				}).done(function (result) {
					$(this).fileupload('option', 'done')
						.call(this, $.Event('done'), {result: result});
				});
			});

			// set call back for when upload process done - to block removal of the file input in case of error
			// basically we do want to show file upload errors but return others to there pre-upload state
			$('[name="files[]"]').bind('fileuploaddone', function (e, data) {
				var paramName;
				e.preventDefault();
				// if there are errors there will be no redirect member in our json response from the UploadHandler
				// allow redirect only if no errors in form data
				if(data.result.hasOwnProperty('redirect')) {
					window.location.href = data.result.redirect;
				}
				else {
					// loop thru each member of the response
					$.each(data.result, function(paramName, value){
						// skip form errors key
						if(paramName == 'activeformerrors') {
							return true;
						}
						// loop thru each of the rows in our fileupload widget
						$('tr.template-upload.fade.in').each(function(index) {
							var file = data.result[paramName][index];
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
					});

					// if form errors
					if(data.result.hasOwnProperty('activeformerrors') && !data.result.activeformerrors.hasOwnProperty('length')) {
						// use yii to deal with the error
						$('form').data('yiiActiveForm').submitting = true;
						$('form').yiiActiveForm('updateInputs', data.result.activeformerrors);
					}

					// if non attribute form errors - e.g. trigger reported errors, fk constraint errors etc
					$('#nonattributeerrors').html(data.result.hasOwnProperty('nonattributeerrors') ? data.result.nonattributeerrors : '');
				}
			});

HERE;
		
		// this needs to come after the fileUpload attachement to the file inputs which are in doc ready
		// the key makes this only once overall for the page/model
        $view->registerJs($jsLoad, View::POS_LOAD, 'files[]');
    }
} 
