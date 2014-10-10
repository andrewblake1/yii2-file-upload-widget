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
use Yii;

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
     * @inheritdoc
     */
    public function init()
    {
		// if read only access
		if(!Yii::$app->user->can($this->model->modelNameShort)) {
			$this->formView = '@vendor/2amigos/yii2-file-upload-widget/views/formUIARRead';
			$this->uploadTemplateView = '@vendor/2amigos/yii2-file-upload-widget/views/uploadUIARRead';
			$this->downloadTemplateView = '@vendor/2amigos/yii2-file-upload-widget/views/downloadUIARRead';
		}
		// form id
        $this->options['id'] = $this->model->formName();	
        $this->clientOptions['maxFileSize'] = 2000000;
        $this->fieldOptions['accept'] = 'image/*';
        $this->fieldOptions['multiple'] = true;

        parent::init();
		// this needed because of the parent setting this in an undesireable fashion for this widget
		unset($this->fieldOptions['id']); 

		// if read only access
		if(!Yii::$app->user->can($this->model->modelNameShort)) {
			$this->formView = '@vendor/2amigos/yii2-file-upload-widget/views/formUIARARead';
		}
		// html name attribute for the file input button - applies to the model as a whole and not to an attribute - for attribute 
		$this->name = $this->options['name'] = $this->attribute . '[]'; 
		// controller action url to get existing files
		$this->urlGetExistingFiles = Url::to([	
			strtolower($this->model->formName()) . '/getexistingfiles',
			'id' => $this->model->id,
			'attribute' => $this->attribute,
		]);
		$this->clientOptions['filesContainer'] = '#' . str_replace('[]', '', $this->name) . '-files-container tbody.files';
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

//		// per target in doc ready
        $options = Json::encode($this->clientOptions);
 //       $view->registerJs(";$('$fileUploadTarget').fileupload($options);", View::POS_READY, $fileUploadTarget);

		// once per target
		$jsLoad = <<<HERE
			$('$fileUploadTarget').fileupload($options);
			
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
        $view->registerJs($jsLoad);
		
		// once on window load
		$jsLoad = <<<HERE
			// keeping track the files ourselves
			var filesList = [], paramNames = [];
			
			// attach file upload handler also to the start button
			var primaryTarget = $('.fileupload-buttonbar');
			var saveButton = $('button[type="button"].start');
			primaryTarget.fileupload();

			$('div[id$="-files-container"]').on("fileuploaddestroy", function(e, data){
				e.preventDefault();
				var container = $(e.target).closest('div[id$="-files-container"]');
				var name = $('input[type="file"]', container).attr('name');
				// create a hidden input to post this file to delete
				var button = $(e.toElement);
				$('<input>').attr({
					type: 'hidden',
					value: data.url,
					name: 'delete[' + name.replace(/[\[\]']+/g,'') + '][]'
				}).insertAfter(button);
				// hide this row
				button.closest('tr').toggleClass('in').hide('slow');
				// clear out any active form error message as may no longer be relevant
				container.next('div.help-block').html('');
			});

			// on adde file, save reference to the file in a global array so that we can access later to send
			$('div[id$="-files-container"]').on("fileuploadadd", function(e, data){
				filesList.push(data.files[0]);
				paramNames.push(e.delegatedEvent.currentTarget.name);
				// clear out any active form error message as may no longer be relevant
				$(this).next('div.help-block').html('');
			});

			// block submit thru file upload - will happen by direct call above - even though would have though taking over click would have
			// done this it does so guiessing send calls submit first or something and perhaps empties the q
			$('div[id$="-files-container"]').bind('fileuploadsubmit', function (e, data) {
				e.preventDefault();
			});

			// deal with click events ourselves on the save button
			saveButton.on('click',function () {
				// add the form data
				primaryTarget.fileupload({
					formData: $('form').serializeArray()
				});
				// if there are some files in our upload q
				if(filesList.length) {
					// send them programatically
					primaryTarget.fileupload('send', {files:filesList, paramName: paramNames});
				} else {
					// fake it so that fileupload send will run
					primaryTarget.fileupload('send', {files:'dummy to make the send fire'});
				}
			});

			// because cancelled are added by client we don't easily have access to the click function here so use event bubbling to pick it up
			// at the form the check if the original element clicked was a cancel button  - allow normal processing afterwards
			$('form').click(function(e) {
				if($(e.target).hasClass('cancel')) {
					var target = $(e.target);
					var container = target.closest('div[id$="-files-container"]');
					// need to figure out which file to remove from our globalFiles list added to in add
					// get paramName - which is the name nearest file input field above this element in the dom
					var name = $('input[type="file"]', container).attr('name');
					// get the row number this element resides in within this table - only considering cancelable rows
					var rowIndex = $('button.cancel', target.closest('tbody')).closest('tr').index(target.closest('tr'));
			
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

					// clear out any active form error message as may no longer be relevant
					container.next('div.help-block').html('');
				}
			});

			// set call back for when upload process done - to block removal of the file input in case of error
			// basically we do want to show file upload errors but return others to there pre-upload state
			primaryTarget.bind('fileuploaddone', function (e, data) {
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

					// if need to restore in deleted files due to unique validator failure
					if(data.result.hasOwnProperty('restore')) {
						$.each(data.result.restore, function (paramName, fileNames) {
							var container = $('#' + paramName + '-files-container');
							$.each(fileNames, function () {
								var button = $('[data-url="' + this + '"]', container);
								button.closest('tr').addClass('in').show('slow');
							});
						});
					}
						
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
        $view->registerJs($jsLoad, View::POS_LOAD, 'button[type="button"].start');
		
    }
} 
