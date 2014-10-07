<?php
/** @var \dosamigos\fileupload\FileUploadUI $this */
use yii\helpers\Html;

$context = $this->context;
?>
<div id="<?= str_replace('[]', '', $context->name);?>-files-container" >
	<div class="col-xs-2">
	<!-- The fileinput-button span is used to style the file input field as button -->
	<span class="btn btn-success fileinput-button">
		<i class="glyphicon glyphicon-plus"></i>
		<span>Add files...</span>

		<?= Html::fileInput($context->name, $context->value, $context->fieldOptions);?>

	</span>
	</div>
	<div class="col-xs-9">
	<table role="presentation" class="table table-striped">
		<tbody id="<?= strtolower($context->model->formName()) . '-' . str_replace('[]', '', $context->name);?>" class="files"></tbody>
	</table>
	</div>
</div>
