<?php

namespace api\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
use common\models\User;
use yii\filters\Cors;
use yii\filters\auth\CompositeAuth;
use yii\filters\auth\HttpBasicAuth;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\auth\QueryParamAuth;
use yii\web\BadRequestHttpException;
use yii\web\UnauthorizedHttpException;
use yii\web\Response;
use yii\filters\RateLimiter;

class CommonController extends ActiveController {

    /**
     * 无权限访问的方法
     *
     * @var array
     */
    public $notAuthAction = [];

    /**
     * 行为验证
     *
     * @return array
     */
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['rateLimiter'] = [
            'class' => RateLimiter::className(),
            'enableRateLimitHeaders' => true,
        ];
        // 跨域支持
        $behaviors['class'] = Cors::className();
        $behaviors['authenticator'] = [
            'class' => CompositeAuth::className(),
            'authMethods' => [
                /* 下面是三种验证access_token方式 */
                // 1.HTTP 基本认证: access token 当作用户名发送，应用在access token可安全存在API使用端的场景，例如，API使用端是运行在一台服务器上的程序。
                // HttpBasicAuth::className(),
                // 2.OAuth : 使用者从认证服务器上获取基于OAuth2协议的access token，然后通过 HTTP Bearer Tokens 发送到API 服务器。
                // HttpBearerAuth::className(),
                // 3.请求参数: access token 当作API URL请求参数发送，这种方式应主要用于JSONP请求，因为它不能使用HTTP头来发送access token
                // http://xxx.com/user/index/index?accessToken=123
                [
                    'class' => HttpBearerAuth::className(),
                    'header' => 'token',
                    'pattern' => '/^(.*?)$/',
                ],
            ],
            // 不进行认证登录
            'optional' => Yii::$app->params['user.optional']
        ];

        /**
         * limit部分，速度的设置是在User::getRateLimit($request, $action)
         * 当速率限制被激活，默认情况下每个响应将包含以下HTTP头发送 目前的速率限制信息：
         * X-Rate-Limit-Limit: 同一个时间段所允许的请求的最大数目;
         * X-Rate-Limit-Remaining: 在当前时间段内剩余的请求的数量;
         * X-Rate-Limit-Reset: 为了得到最大请求数所等待的秒数。
         * 你可以禁用这些头信息通过配置 yii\filters\RateLimiter::enableRateLimitHeaders 为false, 就像在上面的代码示例所示。
         */
        $behaviors['rateLimiter']['enableRateLimitHeaders'] = false;
        // 定义返回格式是：JSON
        $behaviors['contentNegotiator']['formats']['text/html'] = 'json';

        return $behaviors;
    }

    /**
     * 前置操作验证token有效期和记录日志和检查curd权限
     *
     * @param $action
     *
     * @return bool
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws \yii\base\InvalidConfigException
     */
    public function beforeAction($action) {
        parent::beforeAction($action);

        // 开放curd权限检查
        if (in_array($action->id, $this->notAuthAction)) {
            throw new ForbiddenHttpException('无权访问', 403);
        }

        // 判断验证token有效性是否开启
        if (Yii::$app->params['user.accessTokenValidity'] && !in_array($action->id, Yii::$app->params['user.optional'])) {
            $token = Yii::$app->request->headers->get('token');
            $user = Yii::$app->user->identity->getUser($token);
            // 验证有效期
            if ($user->expire_at <= time()) {
                //$user = Yii::$app->user->identity->getUser($token);
                //$user->clearDeviceId();
                throw new BadRequestHttpException('登录已失效', 402);
            }
        }

        // 记录接口日志
        //Yii::$app->params['debug'] == true && ApiLog::add();

        return true;
    }

    public function setResponse($message, $code = 422, $data = []) {
        $this->response = Yii::$app->getResponse();
        $this->response->setStatusCode($code, $message);
        $this->response->data = $data;
    }
    
    public function setResuccess($message, $code = 200, $data = [],$count) {
        $this->response = Yii::$app->getResponse();
        $this->response->setStatusCode($code, $message);
        $this->response->data = $data;
        $this->response->content = (int)$count;
        $this->response->version = '0.0.0';
    }

    /**
     * 获取第一条错误的字符串
     * @return mixed|string
     */
    public function getFirstErrorAsString($model) {
        $errors = $model->getFirstErrors();
        if (empty($errors)) {
            return '';
        }
        return reset($errors);
    }
    public function showImg($files) {
        return $files && file_exists(Yii::getAlias('@upload/').$files) ? Yii::$app->params['upload_url'].'/'.$files : '';
    }

}
