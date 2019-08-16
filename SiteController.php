<?php

namespace api\controllers;

use Yii;
use api\models\LoginForm;
use yii\web\NotFoundHttpException;

/**
 * 默认登录控制器
 * Class SiteController
 *
 * @package api\controllers
 */
class SiteController extends AController
{

    public $modelClass = '';

    /**
     * TOKEN 鉴权类，由USER组件的配置决定，支持MySQL 和 Redis
     * @var string
     */
    public $accessTokenClass = '';

    public function beforeAction($action)
    {

        $this->accessTokenClass = Yii::$app->user->identityClass;

        return parent::beforeAction($action);
    }

    /**
     * 登录根据用户信息返回accessToken
     * 默认是系统会员
     * 其他类型自行扩展
     *
     * @param int $group
     *
     * @return array|void
     * @throws NotFoundHttpException
     * @throws \yii\base\Exception
     */
    public function actionLogin($group = 1)
    {

        if (Yii::$app->request->isPost) {

            $model = new LoginForm();

            $model->attributes = Yii::$app->request->post();

            if ($model->validate()) {

                $user = $model->getUser();

                return $this->accessTokenClass::setMemberInfo($group, $user['id']);
            } else {

                // 返回数据验证失败
                return $this->setResponse($this->analysisError($model->getFirstErrors()));
            }
        }

        throw new NotFoundHttpException('请求出错!');
    }

    /**
     * 重置令牌
     *
     * @param $refresh_token
     *
     * @return array
     * @throws NotFoundHttpException
     * @throws \yii\base\Exception
     */
    public function actionRefresh($refresh_token)
    {

        $user = $this->accessTokenClass::find()->where(['refresh_token' => $refresh_token])->one();

        if (!$user) {
            throw new NotFoundHttpException('令牌错误，找不到用户!');
        }

        return $this->accessTokenClass::setMemberInfo(1, $user['user_id']);
    }

    /**
     * 退出
     *
     * @return string
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function actionLogout()
    {

        if (Yii::$app->user->identity->logout()) {

            $user = Yii::$app->user->identity->getUser();

            $user->clearDeviceId();

            return Yii::t('api', '退出成功');
        }

        return $this->setResponse(Yii::t('api', '退出失败'));
    }

    /**
     * 页面404输出空
     *
     * @return string
     */
    public function actionError()
    {

        return '';
    }

}
