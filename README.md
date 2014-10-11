BlueImp File Upload Widget for Yii2 ActiveRecord attributes
===========================================================

Widget to render the jQuery File Upload UI plugin similar to 
[its demo](http://blueimp.github.io/jQuery-File-Upload/index.html)
for ActiveRecord attributes. Allows multiple widgets for one ActiveRecord
and allows multiple files per attribute. Relies on special controller
actions to generate the expected responses, and some custom file
validation.
 
The POST request generated when sending files, sends all files in a single
request along with other form data, allowing for files to act the same as any
other input in the form i.e. the files not need be saved until all inputs are
validated, and the database has been successfully updated. Supports validation
across all files for an attribute e.g. maxFiles, and also supports per file
validation e.g. matching mime types etc.

Usage
-----

Attach the controller trait to controller, and the ActiveRecordTrait to the ActiveRecord and alter as
necassary.

```
<?php
use dosamigos\fileupload\FileUpload;

// without UI
?>

<?= FileUpload::widget([
	'model' => $model,
	'attribute' => 'image',
	'url' => ['media/upload', 'id' => $model->id], // your url, this is just for demo purposes,
	'options' => ['accept' => 'image/*'],
	'clientOptions' => [
		'maxFileSize' => 2000000
	]
]);?>

<?php

// with UI

use dosamigos\fileupload\FileUploadUI;
?>
<?= FileUploadUI::widget([
	'model' => $model,
	'attribute' => 'image',
	'url' => ['media/upload', 'id' => $tour_id],
	'gallery' => false,
	'fieldOptions' => [
    		'accept' => 'image/*'
	],
	'clientOptions' => [
    		'maxFileSize' => 2000000
	]
]);
?>
```

Further Information
-------------------
Please, check the [jQuery File Upload documentation](https://github.com/blueimp/jQuery-File-Upload/wiki) for further
information about its configuration options.


> [![2amigOS!](http://www.gravatar.com/avatar/55363394d72945ff7ed312556ec041e0.png)](http://www.2amigos.us)  
<i>Web development has never been so fun!</i>  
[www.2amigos.us](http://www.2amigos.us)
