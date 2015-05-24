<?php
/**
 * @copyright Copyright (c) 2013 2amigOS! Consulting Group LLC
 * @link http://2amigos.us
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 */
namespace dosamigos\fileupload;

use yii\helpers\Json;
use yii\helpers\Url;
use yii\helpers\StringHelper;
use Yii;

/**
 * FileUploadUIAR
 *
 * Widget to render the jQuery File Upload UI plugin similar to 
 * [its demo](http://blueimp.github.io/jQuery-File-Upload/index.html)
 * for ActiveRecord attributes. Allows multiple widgets for one ActiveRecord
 * and allows multiple files per attribute. Relies on special controller
 * actions to generate the expected responses, and some custom file
 * validation.
 * 
 * The POST request generated when sending files, sends all files in a single
 * request along with other form data, allowing for files to act the same as any
 * other input in the form i.e. the files not need be saved until all inputs are
 * validated, and the database has been succesfully updated.
 *
 * @author Andrew Blake <admin@newzealandfishing.com>
 * @package dosamigos\fileupload
 */
class FileUploadUIAR extends \yii\widgets\InputWidget
{
    /**
     * @var string|array upload route
     */
    public $url;
    /**
     * @var array the plugin options. For more information see the jQuery File Upload options documentation.
     * @see https://github.com/blueimp/jQuery-File-Upload/wiki/Options
     */
    public $clientOptions = [
        'maxFileSize' => 2000000,
    ];
   /**
     * @var array the HTML attributes for the file input tag.
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $fieldOptions = [
        'accept' =>'image/*',
        'multiple' =>true,
    ];
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
        parent::init();

        $model = $this->model;
        // if read only access - this line would change with different projects
        if(!Yii::$app->user->can(StringHelper::basename($this->model->className()))) {
            $this->formView .= 'Read';
            $this->uploadTemplateView .= 'Read';
            $this->downloadTemplateView .= 'Read';
        }

        $this->options['id'] = $this->model->formName();    
        $this->name = $this->options['name'] = $this->attribute . '[]';

        // controller action url to get existing files
        $this->urlGetExistingFiles = Url::to([    
            strtolower($this->model->formName()) . '/getexistingfiles',
            'id' => $this->model->id,
            'attribute' => $this->attribute,
        ]);
    
        // container element to hold the file input and the file information of uploaded files, and pending file uploads
        $this->clientOptions['filesContainer'] = '#' . str_replace('[]', '', $this->name) . '-files-container tbody.files';
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        echo $this->render($this->uploadTemplateView);
        echo $this->render($this->downloadTemplateView);
        echo $this->render($this->formView);

        $view = $this->getView();

        FileUploadUIARAsset::register($view);

        $options = Json::encode($this->clientOptions);
        $fileUploadTarget = '#' . str_replace('[]', '', $this->name) . '-files-container';
        $view->registerJs(";fileuploaduiar ($options, '$fileUploadTarget', '{$this->name}', '{$this->urlGetExistingFiles}');");
    }
} 
