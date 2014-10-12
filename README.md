BlueImp File Upload Widget for Yii2 ActiveRecord attributes
===========================================================

Widget to render the jQuery File Upload UI plugin similar to 
[its demo](http://blueimp.github.io/jQuery-File-Upload/index.html)
for ActiveRecord attributes. Allows multiple widgets for one ActiveRecord
and allows multiple files per attribute. Relies on custom controller
actions to generate the expected responses, and some custom file
validation.
 
The POST request generated when sending files, sends all files in a single
request along with other form data, allowing for files to act the same as any
other input in the form i.e. the files shoult't be need be saved until all inputs are
validated, and the database has been successfully updated. Supports validation
across all files for an attribute e.g. maxFiles, and also supports per file
validation e.g. matching mime types etc.

This is currently untested in applications other than the one I am currently
working on. In this project the files are arranged hierachically as per navigation
within the system i.e. Client > 1 > Project > 3 > Task > 5 forms the directory
path (actually a key in Amazon s3) Client/1/Project/3/Task/5 and attributes are
added where necassary e.g. Client/1/logo-image/logoimage1.jpg. This allows for simple
cleanup of obsolete files when a model is deleted i.e. in a unix file system
rm -rf Client/1 when ClientActiveRecord::findOne(1)->delete() or on s3 remove
all objects fromt the bucket where prefix is Client/1/ and then remove Client/1
(don't want to remove Client/10 etc)

Usage
-----

Attach the controller trait to your controller, and the ActiveRecordTrait to your
ActiveRecord and alter as necassary.

```

class AccountController extends \backend\components\Controller
{
    use \common\components\FileControllerTrait;
    
    ...

```

Within your form set

```

enctype='multipart/form-data'

```

Set the submit button as follows (an ordinary submit button in the form will not work)

```

<?= $this->context->renderPartial('@vendor/2amigos/yii2-file-upload-widget/views/saveButtonBar.php'); ?>

```

Add the widget for each attribute file attribute

```

<?= dosamigos\fileupload\FileUploadUIAR::widget([
    'model' => $model,
    'attribute' => 'image',
    'options' => ['accept' => 'image/*'],
    'clientOptions' => [
        'maxFileSize' => 2000000
    ]
]);?>

Copy, alter and use as needed the example traits for active record and controller.
These won't work out of the box in a new project but are noted in comments where
they will likely need changing, mainly todo with the hierachical navigation within
the application this currently written for.

Add 'getexistingfiles' to the allowed actions within your controllers rules

In your ActiveRecord, add file properties, getFileAttributes() method and add
rules that apply to the attribute as a whole (per file validation is explained
below)

```
class Account extends \common\components\ActiveRecord
{
    use \common\components\FileActiveRecordTrait;

    /**
     * @var string $logo_image is a file attribute
     */
    public $logo_image;

    /**
     * Get the attribute names for files
     *
     * @return array or strings - file attribute names
     */
    public function getFileAttributes()
    {
        return [
            'logo_image',
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['logo_image'], '\dosamigos\fileupload\FileValidator', 'skipOnEmpty' => false, 'maxFiles' => 2],
            ....
        ];
    }

    ...

```

Copy and alter for each file attribute example file dosamigos\fileupload\AccountLogoImageFile.php which
contains the validation rule applicable per file

This widget is configured to use dosamigos resource mananger - currently
configured for amazon s3 hence alter your local config similarly to

```

    'components' => [
        'resourceManager' => [
            'class' => 'dosamigos\resourcemanager\AmazonS3ResourceManager',
            'key' => 'your key',
            'secret' => 'your secret',
            'bucket' => 'your bucket'
        ],
        ...

```

Further Information
-------------------
Please, check the [jQuery File Upload documentation](https://github.com/blueimp/jQuery-File-Upload/wiki) for further
information about its configuration options.
