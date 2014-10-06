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
use yii\helpers\Url;

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
     * @var string the url for the controller get existing files action
     */
	public $urlGetExistingFiles;

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

		// keeping consistent with how urls are specified in the parent class
		$this->urlGetExistingFiles = Url::to($this->urlGetExistingFiles);

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

		// once in doc ready
		$view->registerJs(';var filesList = [], paramNames = [], elem = $("form");');
		
		// per target in doc ready
        $options = Json::encode($this->clientOptions);
        $view->registerJs(";$('$fileUploadTarget').fileupload($options);", View::POS_READY, $fileUploadTarget);

		// once on window load - using window load as the fileupload plugin needs ataching to target elements first before this code will work
		$jsLoad = <<<HERE
			$('div[id$="-files-container"]').on("fileuploaddestroy", function(e, data){
				e.preventDefault();
				var name = $('input[type="file"]',  $(e.target).closest('div[id$="-files-container"]')).attr('name');
				// create a hidden input to post this file to delete
				var button = $(e.toElement);
				$('<input>').attr({
					type: 'hidden',
					value: data.url,
					name: 'delete[' + name.replace(/[\[\]']+/g,'') + '][]'
				}).insertAfter(button);
				// hide this row
				button.closest('tr').toggleClass('in').hide('slow');
			});

			// save reference to the file in a global array so that we can access later to send
			$('div[id$="-files-container"]').on("fileuploadadd", function(e, data){
				filesList.push(data.files[0]);
				paramNames.push(e.delegatedEvent.currentTarget.name);
			});

			// block submit thru file upload - will happen by direct call above - even though would have though taking over click would have
			// done this it does so guiessing send calls submit first or something and perhaps empties the q
			$('div[id$="-files-container"]').bind('fileuploadsubmit', function (e, data) {
				e.preventDefault();
			});

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
			
			// deal with click events ourselves on the main save button - there is also a hidden save button if no files
			$('.fileupload-buttonbar .start').on('click',function () { 
				// if there are some files in our upload q
				if(filesList.length) {
					// send them programatically
					$('$fileUploadTarget').fileupload('send', {files:filesList, paramName: paramNames});
				} else {
					// TODO try just a submit thru yiiActiveForm plugin - might not noeed the button and the click
					$('#activFormSave').click();
				}
			});

			// because cancelled are added by client we don't easily have access to the click function here so use event bubbling to pick it up
			// at the form the check if the original element clicked was a cancel button  - allow normal processing afterwards
			$('form').click(function(e) {
				if($(e.target).hasClass('cancel')) {
					var target = $(e.target);
					// need to figure out which file to remove from our globalFiles list added to in add
					// get paramName - which is the name nearest file input field above this element in the dom
					var name = $('input[type="file"]', target.closest('div[id$="-files-container"]')).attr('name');
					// get the row number this element resides in within this table
					var rowIndex = $('tr').index(target.closest('tr'));
					// remove this from fileList and paramNames, paired arrays but not grouped
					var atRow = 0;
					$(paramNames).each(function (i, paramName) {
						if(name == paramName) {
							if(atRow == rowIndex) {
								paramNames.splice(i, 1);
								filesList.splice(i, 1);
							} else {
								atRow++;
							}
						}
					});
				}
			});

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

			// set call back for when upload process done - to block removal of the file input in case of error
			// basically we do want to show file upload errors but return others to there pre-upload state
			$('$fileUploadTarget').bind('fileuploaddone', function (e, data) {
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
								// set an error for display
								error = file.error;
							}
							// am assuming any empty result can only come from create within updateAction where ActiveRecord:save() failed
							// but will get into this else as well if no error - discarding empty result error in jqeury.fileupload-ui.js done
							// handler
							else {
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
        $view->registerJs($jsLoad, View::POS_LOAD, $fileUploadTarget);
    }
} 
