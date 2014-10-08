<?php
/** @var \dosamigos\fileupload\FileUploadUI $this */
use yii\helpers\Html;

$context = $this->context;
?>
<div id="<?= str_replace('[]', '', $context->name);?>-files-container" >
	<table role="presentation" class="table table-striped">
		<tbody id="<?= strtolower($context->model->formName()) . '-' . str_replace('[]', '', $context->name);?>" class="files"></tbody>
	</table>
</div>
