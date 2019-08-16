<?php

namespace api\controllers;

use Yii;
use yii\data\ActiveDataProvider;
use yii\data\Pagination;
use yii\web\NotFoundHttpException;
use common\enums\StatusEnum;
use common\core\ActiveController;

/**
 * 用户信息基类，所关联的表需要带user_id，只允许所属用户操作
 * Class CommonController
 *
 * @package api\controllers
 */
class MController extends ActiveController
{
    /**
     * 关联用户主键，可更改为其他字段
     * @var string
     */
    public $userId = 'user_id';


    public function actions()
    {
        $actions = parent::actions();
        // 注销系统自带的实现方法
        unset($actions['index'], $actions['update'], $actions['create'], $actions['delete'], $actions['view']);
        // 自定义数据indexDataProvider覆盖IndexAction中的prepareDataProvider()方法
        // $actions['index']['prepareDataProvider'] = [$this, 'indexDataProvider'];
        return $actions;
    }

    /**
     * 首页
     *
     * @return ActiveDataProvider
     */
    public function actionIndex()
    {
        $modelClass = $this->modelClass;

        $query = method_exists($modelClass, 'search')
            ? $modelClass::search(Yii::$app->request->getQueryParams())
            : $modelClass:: find();
        $query->andWhere([
            $this->userId => Yii::$app->user->identity->user_id,
        ]);
        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => new Pagination([
                'pageSizeLimit' => [1, Yii::$app->params['page.max.limit']]
            ])
        ]);
    }

    /**
     * 创建
     *
     * @return bool
     */
    public function actionCreate()
    {
        $model = new $this->modelClass();
        $model->attributes = Yii::$app->request->post();
        $model->{$this->userId} = Yii::$app->user->identity->user_id;
        if (!$model->save()) {
            return $this->setResponse($model->getFirstErrorAsString());
        }

        return $model;
    }


    /**
     * 更新
     * @param $id
     * @return mixed
     * @throws NotFoundHttpException
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $model->attributes = Yii::$app->request->post();
        if (!$model->save()) {
            return $this->setResponse($model->getFirstErrorAsString());
        }

        return $model;
    }

    /**
     * 删除
     *
     * @param $id
     * @return mixed
     * @throws NotFoundHttpException
     */
    public function actionDelete($id)
    {
        $result = $this->findModel($id)->delete();
        if ($result) {
            return '';
        } else {
            $this->setResponse(Yii::t('common', '删除失败'));
        }
    }

    /**
     * 显示单个
     *
     * @param $id
     * @return mixed
     * @throws NotFoundHttpException
     */
    public function actionView($id)
    {
        return $this->findModel($id);
    }

    /**
     * 返回模型
     *
     * @param $id
     * @return mixed
     * @throws NotFoundHttpException
     */
    protected function findModel($id)
    {
        $id = (int)$id;
        if ($id <= 0) {
            throw new NotFoundHttpException(Yii::t('api', '请求的数据不存在'));
        }
        $primaryKey = $this->modelClass::primaryKey();
        if ($model = $this->modelClass::find()->where([
            reset($primaryKey) => $id,
            $this->userId => Yii::$app->user->identity->user_id,
        ])->one()) {
            return $model;
        }

        throw new NotFoundHttpException(Yii::t('api', '请求的数据不存在'));
    }
}
