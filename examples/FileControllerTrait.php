<?php
/**
 * @copyright Andrew Blake
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 */
namespace common\components;

use Yii;
use yii\helpers\Url;
use common\models\Model;
use kartik\helpers\Html;
use yii\web\UploadedFile;
use yii\web\Response;
use dosamigos\fileupload\File;
use backend\components\DetailView;
use yii\helpers\Inflector;

/**
 * FileControllerTrait adds file upload functionality to a controller. To be used
 * in conjuction with FileActiveRecordBehavior and FileUploadUIAR
 *
 * @author Andrew Blake <admin@newzealandfishing.com>
 * @package dosamigos\fileupload
 */
trait FileControllerTrait
{

    /**
     * Produce json response for jquery file upload plugin, containing existing
     * stored files, for display within the widge
     * 
     * @param string $id The primary key value for the model
     * @param string $attribute The name of the attribute within the model that
     * is the files 
     * @return array the response as an array but with headers and response
     * format set to json
     */
    public function actionGetexistingfiles($id = null, $attribute = null)
    {
        $response = [];

        if ($id) {
            $model = $this->findModel($id);
            $inputName = $attribute;

            // $model->path is the base path in a file system for uploads for this model
            $path = $model->path . '/' . $attribute;
            $manager = Yii::$app->resourceManager;
            $privacy = File::ISPUBLIC;

            // get list of existing files from storage
            foreach ($manager->listFiles($path . '/' . File::LARGE_IMAGE . '/') as $file) {
                $response[$inputName . '[]'][] = [
                    'name' => $file['name'],
                    'type' => $file['type'],
                    'size' => $file['size'],
                    'url' => $manager->getUrl($file['path'], $privacy),
                    'thumbnailUrl' => $manager->getUrl(
                        $path . '/' . File::SMALL_IMAGE . '/' . $file['name'],
                        $privacy
                    ),
                    // not actually url to delete but the file itself - easiest hack
                    'deleteUrl' => $file['name'],
                    'deleteType' => 'POST'
                ];
            }
        }

        return $this->response($response);
    }

    /**
     * Set the response format to json - pass thru the response array for
     * readability
     * @param array $response
     * @return type
     */
    private function response($response)
    {
        Yii::$app->response->getHeaders()->set('Vary', 'Accept');
        Yii::$app->response->format = Response::FORMAT_JSON;

        return $response;
    }

    /**
     * When generating the response for jquery-file-upload, need to be careful
     * to return a similarly indexed array as this is what jquery file
     * upload expects - with a few extrax for the FileUPloadUIAR widget
     * like deletes, redirect, and form errors
     * @param ActiveRecord $model
     * @param array $deleteFiles in form [attribute => pathName]
     * @param bool $save true if good to save changes
     */
    private function buildResponse($model, $save, $deleteFiles = [])
    {
        $response = [];

        foreach ($model->fileAttributes as $attribute) {
            foreach ($model->$attribute as $uploadedFile) {
                // the files array may contain existing uploads which will be
                // arrays rather than UploadedFile's
                if (!$uploadedFile instanceof UploadedFile) {
                    // skip existing
                    continue;
                }

                $file = new File([
                    'file' => $uploadedFile,
                    'basePath' => $model->path . '/' . $attribute
                ]);
                // validate this individual file
                $file->validate();
                // build response
                $response[$attribute . '[]'][] = $file->jqueryFileUploadResponse();

                if ($save) {
                    $file->save();
                }

                // remove temporary file
                @unlink($file->file->tempName);
            }

            // deletes
            $manager = Yii::$app->resourceManager;
            foreach ($deleteFiles as $attribute => $fileNames) {
                // if saving
                if ($save) {
                    foreach ($fileNames as $name) {
                        $fileClass = $model->modelName
                            . Inflector::id2camel($attribute, '_')
                            . 'File';
                        $file = new $fileClass([
                            'name' => $name,
                            'basePath' => $model->path . '/' . $attribute
                        ]);
                        // remove from storage
                        $file->delete();
                        // response not important as redirect will occurr when saving
                    }
                } else { // response is important as not redirecting
                    // did validation fail for this attribute
                    if ($model->getErrors($attribute)) {
                        // did validataion fail possibly because of a mandatory requirement
                        foreach ($model->getActiveValidators($attribute) as $validator) {
                            // if required validator
                            if ($validator instanceof \yii\validators\FileValidator) {
                                // if we have no files left
                                if ($validator->skipOnEmpty === false && empty($model->$attribute)) {
                                    // indicate response to restore it
                                    $response['restore'][$attribute] = $fileNames;
                                }
                            }
                        }
                    }
                }
            }
        }

        // save errors is property added to ActiveRecord to hold errors caught
        // from attempting to save to database inside transaction that got past
        // normal validation. These errors will only occurr on a a delete, insert
        // or update of database and are potentially trigger related e.g.
        // validating that an adjacency list doesn't create endless loop (force
        // a trigger error and detect here)
        if ($model->saveErrors) {
            // send these thru but format the html here - an error block above form
            $response['nonattributeerrors'] = Html::listGroup(
                $model->saveErrors,
                ['class' => 'list-group'],
                'ul',
                'li class="list-group-item list-group-item-danger"'
            );
        }

        // add form errors for blueimp fileuploadfinished callback - borrowed
        // from \yii\widgets\ActiveForm::validate()
        foreach ($model->getErrors() as $attribute => $errors) {
            $response['activeformerrors'][Html::getInputId($model, $attribute)]
                = $errors;
        }

        return $this->response($response);
    }

    /**
     * @inheritDoc
     * This create is designed for use in kartik-v detail view within modal
     * loaded dynamically hence the renderAjax call at the bottom - which would
     * likely need changing for other projects as would the determination of
     * redirect url inside if ($save = $model->save()){...} which is specific
     * to this application
     * @return string Either html if get request or json if post request
     */
    public function actionCreate()
    {
        $model = $this->newModelWithDefaults;
        $modelNameShort = $this->modelNameShort;
        $model->load(Yii::$app->request->get());

        if (Yii::$app->request->isPost) {
            $model->load(Yii::$app->request->post());
            $response = [];
            $model->loadFileAttributes();
            // allow rollback if any error occurrs with saving form data or file upload
            $transaction = Yii::$app->db->beginTransaction();
            if ($save = $model->save()) {
                // if this model is leaf node in navigation
                if (Model::findOne(['auth_item_name' => $this->modelNameShort])->isLeaf()) {
                    // go to the admin view of this node
                    $params[] = 'index';
                    $fullModelName = $this->modelName;
                    if ($parentAttribute = $fullModelName::parentAttribute()) {
                        $params[$parentAttribute] = $model->$parentAttribute;
                    }
                } else {
                    // go to the update view
                    $params = ['update', 'id' => $model->id];
                }
                $response += ['redirect' => Url::to($params)];
                $transaction->commit();
            } else {
                $transaction->rollback();
            }

            return array_merge($response, $this->buildResponse($model, $save));
        }

        // from http://www.yiiframework.com/wiki/690/render-a-form-in-a-modal-popup-using-ajax/
        return $this->renderAjax('//' . $this->id . '/_form', [
                'model' => $model,
                'mode' => DetailView::MODE_EDIT,
        ]);
    }

    /**
     * 
     * @param string $id The id of the model to update
     * @return string Either html if get request or json if post request will
     * be ajax initiated.
     * Inside if ($save = $model->save()) {...} would need to be altered for other
     * projects
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if (Yii::$app->request->isPost) {
            $model->load(Yii::$app->request->post());
            $response = [];
            $deleteFiles = isset($_POST['delete']) ? $_POST['delete'] : [];
            $model->loadFileAttributes($deleteFiles);

            // allow rollback if any error occurrs with saving form data or file upload
            $transaction = Yii::$app->db->beginTransaction();
            if ($save = $model->save()) {
                // set redirect back to parent index view
                $params[] = 'index';
                $fullModelName = $this->modelName;
                if ($parentAttribute = $fullModelName::parentAttribute()) {
                    $params[$parentAttribute] = $model->$parentAttribute;
                }
                $response += ['redirect' => Url::to($params)];
                $transaction->commit();
            } else {
                $transaction->rollback();
            }

            return array_merge($response, $this->buildResponse($model, $save, $deleteFiles));
        }

        return $this->render('@app/views/update', [
                'model' => $model,
        ]);
    }

}

?>