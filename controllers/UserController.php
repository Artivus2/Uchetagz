<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use app\models\LoginForm;
//use yii\web\UploadedFile;
use app\models\User;
//use app\models\UserWords;


//use app\models\AuthCode;
//use app\models\UserTwoFactor;
//use app\models\AffiliateHistory;
use yii\helpers\Url;
//use app\components\GoogleAuthenticator;
//use app\models\Currency;
//use app\helpers\FcmPushNotify;

/**
 * Default controller for the `api` module
 */
class UserController extends BaseController
{

    /**
     * Uploads user photo
     *
     * Загружает фотографию для аутентифицированного пользователя. Допускаются только форматы файлов JPG, PNG и JPEG.
     *
     * @SWG\Post(
     *    path = "/user/upload-photo",
     *    tags = {"User"},
     *    summary = "Загрузка фото",
     *    security={{"access_token":{}}},
     *    @SWG\Parameter(
     *      name="image",
     *      in="body",
     *      required=true,
     *      @SWG\Schema(
     *          @SWG\Property(
     *              property="image",
     *              type="file",
     *              description="Фото"
     *          )
     *      )
     *     ),
     *	  @SWG\Response(
     *      response = 200,
     *      description = "OK",
     *      @SWG\Schema(
     *          type="object",
     *          @SWG\Property(
     *              type="string",
     *              property="image",
     *              description="URL загруженной фотографии"
     *          )
     *      )
     *    ),
     *    @SWG\Response(
     *      response = 400,
     *      description = "Ошибка запроса",
     *      @SWG\Schema(
     *          type="object",
     *          @SWG\Property(
     *              type="boolean",
     *              property="success",
     *              description="Результат загрузки",
     *              example=false
     *          ),
     *          @SWG\Property(
     *              type="string",
     *              property="message",
     *              description="Строка с содержанием ошибки",
     *              example="Вы можете прикрепить jpg, png, jpeg"
     *          ),
     *      )
     *    ),
     *    @SWG\Response(
     *      response = 401,
     *      description = "Ошибка авторизации",
     *      @SWG\Schema(
     *         type="object",
     *         @SWG\Property(
     *              type="boolean",
     *              property="success",
     *              description="Success state - false",
     *              example=false
     *         ),
     *         @SWG\Property(
     *              type="string",
     *              property="message",
     *              description="Строка с содержанием ошибки",
     *              example="Token не найден"
     *         ),
     *      )
     *    ),
     *)
     * @throws HttpException
     */
    public function actionUploadPhoto()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        if (!$this->user) {
            Yii::$app->response->statusCode = 401;
            return ["success" => false, "message" => "Token не найден"];
        }

        $image = UploadedFile::getInstanceByName('image');
        if ($image->extension != "jpg" && $image->extension != "png" && $image->extension != "jpeg") {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => "Вы можете прикрепить jpg, png, jpeg"];
        }

        $path = 'yii2images/' . Yii::$app->security->generateRandomString() . '.' . $image->extension;
        $image->saveAs($path);
        $this->user->attachImage($path, true);
        @unlink($path);

        FcmPushNotify::push(
            'Изменение фото',
            'Фото пользователя успешно заменено',
            [$this->token->fcm_token]
        );

        return ["image" => Url::to([$this->user->getImage()->getUrl("75x75")], 'https')];
    }
    

   

    /**
     * @SWG\Post(
     *    path = "/user/login",
     *    tags = {"Authentication"},
     *    summary = "Аутентификация",
     *    description="Аутентификация пользователя по телефону или электронной почте. В случае успеха, на указанный телефон или электронную почту отправляется уведомление с кодом подтверждения.",
     *    consumes={"application/json"},
     *    produces={"application/json"},
     *    @SWG\Parameter(
     *        name="body",
     *        in="body",
     *        description="Данные для аутентификации пользователя",
     *        required=true,
     *        @SWG\Schema(
     *            type="object",
     *            @SWG\Property(
     *                property="phone",
     *                type="string",
     *                description="Номер телефона пользователя",
     *                example="79998885522"
     *            ),
     *            @SWG\Property(
     *                property="email",
     *                type="string",
     *                description="Адрес электронной почты пользователя",
     *                example="test@mail.com"
     *            ),
     *            @SWG\Property(
     *                property="password",
     *                type="string",
     *                description="Пароль для аутентификации",
     *                example="password"
     *            ),
     *        )
     *    ),
     *	  @SWG\Response(
     *      response=200,
     *      description = "Успешный ответ",
     *      @SWG\Schema(
     *          type="object",
     *          @SWG\Property(
     *              property="success",
     *              type="boolean",
     *              description="Флаг успешного выполнения",
     *              example=true
     *          ),
     *          @SWG\Property(
     *              property="message",
     *              type="string",
     *              description="Сообщение о результате выполнения",
     *              example="Код успешно отправлен"
     *          )
     *      )
     *    ),
     *    @SWG\Response(
     *      response = 400,
     *      description = "Ошибка запроса",
     *      @SWG\Schema(
     *          type="object",
     *          @SWG\Property(
     *              property="success",
     *              type="boolean",
     *              description="Флаг успешного выполнения",
     *              example=false
     *          ),
     *          @SWG\Property(
     *              property="message",
     *              type="string",
     *              description="Сообщение об ошибке",
     *              example="Укажите телефон или электронную почту"
     *          )
     *      )
     *    ),
     *)
     * @throws HttpException
     */
    public function actionLogin()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        //$phone = Yii::$app->request->post("phone");
        $login = Yii::$app->request->post("login");
        //$fcmToken = Yii::$app->request->post("fcm_token");

        if ($login) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => "Укажите логин"];
        }

        $password = Yii::$app->request->post("password");

        $model = new LoginForm(["email" => $email, "password" => $password]);

        if (!$model->login()) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => $this->getError($model)];
        }

        //$this->sendCode(Yii::$app->user->identity, $login);
        if ($user->status == 0) {
            $user->status = 1;
            $user->save();
        }

        $token = $user->setToken($fcmToken);

        return ["access_token" => $token->token];

    }

    /**
     * Logout action
     *
     * Позволяет пользователю выйти из системы и отозвать свой авторизационный токен.
     *
     * @SWG\Get(
     *     path="/user/logout",
     *     tags={"Authentication"},
     *     summary="Выйти и отозвать авторизационный токен",
     *     produces={"application/json"},
     *     security={{"access_token":{}}},
     *     @SWG\Response(
     *         response=200,
     *         description="OK",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true,
     *                 description="Успешно ли пользователь вышел из системы."
     *             ),
     *             @SWG\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Пользователь успешно разлогинен",
     *                 description="Сообщение, описывающее результат операции."
     *             )
     *         )
     *     ),
     *     @SWG\Response(
     *         response=400,
     *         description="Bad request",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false,
     *                 description="Успешно ли пользователь вышел из системы."
     *             ),
     *             @SWG\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Ошибка при сохранении пользователя",
     *                 description="Сообщение, описывающее возникшую ошибку."
     *             )
     *         )
     *     ),
     *     @SWG\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false,
     *                 description="Успешно ли пользователь вышел из системы."
     *             ),
     *             @SWG\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Token не найден",
     *                 description="Сообщение, описывающее возникшую ошибку."
     *             )
     *         )
     *     )
     * )
     */
    public function actionLogout()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        if (!$this->user) {
            Yii::$app->response->statusCode = 401;
            return ["success" => false, "message" => "Token не найден"];
        }

        $token = $this->user->getAuthTokens()->where(['token' => Yii::$app->request->headers->get('Authorization')])->one();

        if (!$token) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => 'Token не найден'];
        }

        if (!$token->delete()) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => 'Не удалось удалить токен'];
        }

        return ["success" => true, "message" => "Пользователь успешно разлогинен"];
    }

    /**
     * @SWG\Post(
     *    path = "/user/recover",
     *    tags = {"User"},
     *    summary = "Запрос востановления пароля",
     *    @SWG\Parameter(
     *      name="email",
     *      in="body",
     *      description="Email",
     *      required=true,
     *      @SWG\Schema(type="string")
     *     ),
     *    @SWG\Parameter(
     *      name="phone",
     *      in="body",
     *      description="Телефон",
     *      required=true,
     *      @SWG\Schema(type="string")
     *     ),
     *	  @SWG\Response(
     *      response = 200,
     *      description = "Отправка кода",
     *      @SWG\Schema(ref = "#/definitions/Result")
     *    ),
     *    @SWG\Response(
     *      response = 400,
     *      description = "Ошибка запроса",
     *      @SWG\Schema(ref = "#/definitions/Result")
     *    ),
     *)
     * @throws HttpException
     */
    public function actionRecover()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $phone = trim(Yii::$app->request->post("phone"));
        $email = trim(Yii::$app->request->post("email")); //refactor

        $user = null;
        if ($phone != "") {
            $user = User::find()->where(["phone" => preg_replace('/[^0-9]/', '', $phone)])->one();
        } else if ($email != "") {
            $user = User::find()->where(["email" => $email])->one();
        } else {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => "Укажите телефон или электронную почту"];
        }

        if (!$user) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => "Пользователь не найден"];
        }

        //$this->sendCode($user);
        

        return ["token" => Yii::$app->security->generateRandomString()];
    }

    /**
     * @SWG\Post(
     *    path = "/user/recover-password",
     *    tags = {"User"},
     *    summary = "Востановление пароля",
     *    @SWG\Parameter(
     *      name="token",
     *      in="body",
     *      description="Токен востановления",
     *      required=true,
     *      @SWG\Schema(type="string")
     *     ),
     *    @SWG\Parameter(
     *      name="code",
     *      in="body",
     *      description="Код подтверждения",
     *      required=true,
     *      @SWG\Schema(type="string")
     *     ),
     *    @SWG\Parameter(
     *      name="password",
     *      in="body",
     *      description="Новый пароль",
     *      required=true,
     *      @SWG\Schema(type="string")
     *     ),
     *	  @SWG\Response(
     *      response = 200,
     *      description = "Отправка кода",
     *      @SWG\Schema(ref = "#/definitions/Result")
     *    ),
     *    @SWG\Response(
     *      response = 400,
     *      description = "Ошибка запроса",
     *      @SWG\Schema(ref = "#/definitions/Result")
     *    ),
     *)
     * @throws HttpException
     */
    public function actionRecoverPassword() //refactor
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $token = trim(Yii::$app->request->post("token"));
        $password = trim(Yii::$app->request->post("password"));
        $code = trim(Yii::$app->request->post("code"));

        $code = AuthCode::findOne(["code" => $code]);
        if(!$code) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => "Код введен неправильно"];
        }

        $user = User::findOne($code->user_id);
        $user->password = Yii::$app->getSecurity()->generatePasswordHash($password);
        $user->save();

        return ["success" => true, "message" => "Пароль успешно изменен"];
    }

    /**
     * @SWG\Post(
     *    path = "/user/change-password",
     *    tags = {"User"},
     *    summary = "Сменить пароль",
     *    security={{"access_token":{}}},
     *    @SWG\Parameter(
     *      name="old_password",
     *      in="body",
     *      description="Старый пароль",
     *      required=true,
     *      @SWG\Schema(type="string")
     *     ),
     *     @SWG\Parameter(
     *      name="password",
     *      in="body",
     *      description="Новый пароль",
     *      required=true,
     *      @SWG\Schema(type="string")
     *     ),
     *	  @SWG\Response(
     *      response = 200,
     *      description = "Пароль успешно изменен",
     *      @SWG\Schema(ref = "#/definitions/Result")
     *    ),
     *    @SWG\Response(
     *      response = 400,
     *      description = "Ошибка запроса",
     *      @SWG\Schema(ref = "#/definitions/Result")
     *    ),
     *    @SWG\Response(
     *      response = 403,
     *      description = "Ошибка авторизации",
     *      @SWG\Schema(ref = "#/definitions/Result")
     *    ),
     *)
     * @throws HttpException
     */
    public function actionChangePassword() //refactor
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        if (!$this->user) {
            Yii::$app->response->statusCode = 401;
            return ["success" => false, "message" => "Token не найден"];
        }

        $oldPassword = trim(Yii::$app->request->post("old_password"));
        $password = trim(Yii::$app->request->post("password"));

        if (!Yii::$app->getSecurity()->validatePassword($oldPassword, $this->user->password)) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => "Неверный пароль"];
        }

        $this->user->password = Yii::$app->getSecurity()->generatePasswordHash($password);
        if (!$this->user->save()) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => $this->getError($this->user)];
        }

        FcmPushNotify::push(
            'Изменение пароля',
            'Пароль пользователя был изменен.',
            [$this->token->fcm_token]
        );

        return ["success" => true, "message" => "Пароль успешно изменен"];
    }

    /**
     * @SWG\Get(
     *    path = "/user/profile",
     *    tags = {"User"},
     *    summary = "Информация о пользователе",
     *    security={{"access_token":{}}},
     *	  @SWG\Response(
     *      response = 200,
     *      description = "Информация о пользователе",
     *      @SWG\Schema(ref = "#/definitions/User")
     *    ),
     *    @SWG\Response(
     *      response = 400,
     *      description = "Ошибка запроса",
     *      @SWG\Schema(ref = "#/definitions/Result")
     *    ),
     *    @SWG\Response(
     *      response = 403,
     *      description = "Ошибка авторизации",
     *      @SWG\Schema(ref = "#/definitions/Result")
     *    ),
     *)
     * @throws HttpException
     */
    public function actionProfile()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        if (!$this->user) {
            Yii::$app->response->statusCode = 401;
            return ["success" => false, "message" => "Token не найден"];
        }

        return $this->getProfile($this->user);
    }

    /**
     * @SWG\Post(
     *    path = "/user/code",
     *    tags = {"User"},
     *    summary = "Подтверждение пользователя",
     *    security={{"access_token":{}}},
     *    @SWG\Parameter(
     *      name="email",
     *      in="body",
     *      description="Email",
     *      required=true,
     *      @SWG\Schema(type="string")
     *     ),
     *    @SWG\Parameter(
     *      name="phone",
     *      in="body",
     *      description="Телефон",
     *      required=true,
     *      @SWG\Schema(type="string")
     *     ),
     *    @SWG\Parameter(
     *      name="password",
     *      in="body",
     *      description="Пароль",
     *      required=true,
     *      @SWG\Schema(type="string")
     *     ),
     *    @SWG\Parameter(
     *      name="code",
     *      in="body",
     *      description="Код",
     *      required=true,
     *      @SWG\Schema(type="string")
     *     ),
     *     @SWG\Parameter(
     *      name="fcm_token",
     *      in="body",
     *      description="Fcm токен",
     *      required=true,
     *      @SWG\Schema(type="string")
     *     ),
     *	  @SWG\Response(
     *      response = 200,
     *      description = "Токен авторизации",
     *      @SWG\Schema(ref = "#/definitions/Token")
     *    ),
     *    @SWG\Response(
     *      response = 400,
     *      description = "Ошибка запроса",
     *      @SWG\Schema(ref = "#/definitions/Result")
     *    ),
     *    @SWG\Response(
     *      response = 403,
     *      description = "Ошибка авторизации",
     *      @SWG\Schema(ref = "#/definitions/Result")
     *    ),
     *)
     * @throws HttpException
     */
    public function actionCode()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        //$phone = Yii::$app->request->post("phone");
        $email = Yii::$app->request->post("email");
        $fcmToken = Yii::$app->request->post("fcm_token");

        if (!$phone && !$email) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => "Укажите телефон или электронную почту"];
        }

        $password = Yii::$app->request->post("password");

        $model = new LoginForm(["phone" => $phone, "email" => $email, "password" => $password]);

        if (!$model->login()) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => $this->getError($model)];
        }

        $user = Yii::$app->user->identity;
        // if(Yii::$app->request->post("code") != '111111') {
        //     $code = AuthCode::findOne(["user_id" => $user->id, "code" => Yii::$app->request->post("code")]);
        //     if (!$code) {
        //         Yii::$app->response->statusCode = 400;
        //         return ["success" => false, "message" => "Код введен неправильно"];
        //     }

        //     $code->delete();
        // }

        if ($user->status == 0) {
            $user->status = 1;
            $user->save();
        }

        $token = $user->setToken($fcmToken);

        return ["access_token" => $token->token];
    }

    /**
     * @SWG\Post(
     *    path = "/user/update",
     *    tags = {"User"},
     *    summary = "Обновление профиля пользователя",
     *    security={{"access_token":{}}},
     *    produces={"application/json"},
     *    consumes={"application/json"},
     *    @SWG\Parameter(
     *      name="body",
     *      in="body",
     *      description="Данные для обновления профиля",
     *      @SWG\Schema(
     *          type="object",
     *          @SWG\Property(
     *              property="login",
     *              type="string",
     *              description="Логин пользователя",
     *              example="new_login"
     *          ),
     *          @SWG\Property(
     *              property="telegram",
     *              type="string",
     *              description="Телеграм",
     *              example="new_telegram"
     *          ),
     *          @SWG\Property(
     *              property="last_name",
     *              type="string",
     *              description="Фамилия",
     *              example="Иванов"
     *          ),
     *          @SWG\Property(
     *              property="first_name",
     *              type="string",
     *              description="Имя",
     *              example="Иван"
     *          ),
     *          @SWG\Property(
     *              property="patronymic",
     *              type="string",
     *              description="Отчество",
     *              example="Иванович"
     *          )
     *      )
     *    ),
     *	  @SWG\Response(
     *      response = 200,
     *      description = "OK",
     *      @SWG\Schema(ref = "#/definitions/User")
     *    ),
     *    @SWG\Response(
     *      response = 400,
     *      description = "Ошибка запроса",
     *      @SWG\Schema(
     *          type="object",
     *          @SWG\Property(
     *              type="boolean",
     *              property="success",
     *              description="Статус запроса",
     *              example=false
     *          ),
     *          @SWG\Property(
     *              type="string",
     *              property="message",
     *              description="Строка с содержанием ошибки",
     *              example="Произошла ошибка при сохранении параметра"
     *          ),
     *      )
     *    ),
     *    @SWG\Response(
     *      response = 401,
     *      description = "Ошибка авторизации",
     *      @SWG\Schema(
     *          type="object",
     *          @SWG\Property(
     *              type="boolean",
     *              property="success",
     *              description="Статус запроса",
     *              example=false
     *          ),
     *          @SWG\Property(
     *              type="string",
     *              property="message",
     *              description="Строка с содержанием ошибки",
     *              example="Token не найден"
     *          ),
     *      )
     *    ),
     *)
     * @throws HttpException
     */
    public function actionUpdate()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        if (!$this->user) {
            Yii::$app->response->statusCode = 401;
            return ["success" => false, "message" => "Token не найден"];
        }

        $post = Yii::$app->request->post();
        $fields = ["login", "telegram", "last_name", "first_name", "patronymic"];

        // Обновление полей пользователя
        foreach ($fields as $field) {
            if (isset($post[$field])) {
                $this->user->$field = trim($post[$field]);
            }
        }

        if (!$this->user->save()) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => $this->getError($this->user)];
        }

        return $this->getProfile($this->user);
    }


    
   
    /**
     * @SWG\Post(
     *    path = "/user/password",
     *    tags = {"User"},
     *    summary = "Изменение пароля",
     *    security={{"access_token":{}}},
     *    @SWG\Parameter(
     *      name="old_password",
     *      in="body",
     *      description="Старый папроль",
     *      required=true,
     *      @SWG\Schema(type="string")
     *     ),
     *    @SWG\Parameter(
     *      name="password",
     *      in="body",
     *      description="Новый пароль",
     *      required=true,
     *      @SWG\Schema(type="string")
     *     ),
     *	  @SWG\Response(
     *      response = 200,
     *      description = "Пароль успешно изменен",
     *      @SWG\Schema(ref = "#/definitions/Result")
     *    ),
     *    @SWG\Response(
     *      response = 400,
     *      description = "Ошибка запроса",
     *      @SWG\Schema(ref = "#/definitions/Result")
     *    ),
     *    @SWG\Response(
     *      response = 403,
     *      description = "Ошибка авторизации",
     *      @SWG\Schema(ref = "#/definitions/Result")
     *    ),
     *)
     * @throws HttpException
     */
    public function actionPassword()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        if (!$this->user) {
            Yii::$app->response->statusCode = 401;
            return ["success" => false, "message" => "Token не найден"];
        }

        $old_password = Yii::$app->request->post("old_password");

        if (!$this->user->validatePassword($old_password)) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => "Не верный пароль"];
        }

        $password = Yii::$app->request->post("password");
        $this->user->password = Yii::$app->getSecurity()->generatePasswordHash($password);
        $this->user->save();

        return ["success" => true, "message" => "Пароль изменен"];
    }

    /**
     * @SWG\Post(
     *    path = "/user/register",
     *    tags = {"User"},
     *    summary = "Регистрация",
     *    @SWG\Parameter(
     *      name="email",
     *      in="body",
     *      description="Электронная почта",
     *      required=true,
     *      @SWG\Schema(type="string")
     *     ),
     *    @SWG\Parameter(
     *      name="phone",
     *      in="body",
     *      description="Телефон",
     *      required=true,
     *      @SWG\Schema(type="string")
     *     ),
     *    @SWG\Parameter(
     *      name="login",
     *      in="body",
     *      description="Логин",
     *      required=true,
     *      @SWG\Schema(type="string")
     *     ),
     *    @SWG\Parameter(
     *      name="password",
     *      in="body",
     *      description="Пароль",
     *      required=true,
     *      @SWG\Schema(type="string")
     *     ),
     *    @SWG\Parameter(
     *      name="affiliate",
     *      in="body",
     *      description="Реферальный код",
     *      required=true,
     *      @SWG\Schema(type="string")
     *     ),
     *	  @SWG\Response(
     *      response = 200,
     *      description = "Отправка кода",
     *      @SWG\Schema(ref = "#/definitions/Result")
     *    ),
     *    @SWG\Response(
     *      response = 400,
     *      description = "Ошибка запроса",
     *      @SWG\Schema(ref = "#/definitions/Result")
     *    ),
     *)
     * @throws HttpException
     */
    public function actionRegister()
    {
        //Yii::warning('Регистрация');

        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $login = Yii::$app->request->post("login");
        $password = Yii::$app->request->post("password");

        if (!$login) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => "Укажите логин"];
        }
        $user = User::find(['login' => $login])->one();
        if (!$user) {
            $user = new User(["login" => $login]);
            $user->password = Yii::$app->getSecurity()->generatePasswordHash($password);
            $user->save();
        } else {
            return ["success" => false, "message" => "Такой логин уже существует"];
        }

        // $auth = Yii::$app->authManager;
        // $role = "client";
        // $rbac = $auth->getRole($role);
        // $auth->assign($rbac, $user->id);

        //$this->sendCode($user);

        return ["success" => true, "message" => "Успешно зарегистрирован"];
    }

    /**
     * Инициализация процесса смены email пользователя
     *
     * @SWG\Post(
     *     path="/user/email-change",
     *     tags={"User"},
     *     summary="Изменение email пользователя",
     *     description="Отправка кода подтверждения для смены email пользователя",
     *      security={{"access_token":{}}},
     *     produces={"application/json"},
     *      @SWG\Parameter(
     *          name="body",
     *          in="body",
     *          description="Данные для смены email",
     *          required=true,
     *          @SWG\Schema(
     *              type="object",
     *              @SWG\Property(
     *                  property="new_email",
     *                  type="string",
     *                  description="Новый email",
     *                  example="newmail@gmail.com"
     *              )
     *          )
     *      ),
     *     @SWG\Response(
     *         response=200,
     *         description="OK",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(
     *                 property="success",
     *                 type="boolean",
     *                 description="Статус запроса",
     *                 example=true
     *             ),
     *             @SWG\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Сообщение",
     *                 example="На указанную почту отправлен код для подтверждения"
     *             )
     *         )
     *     ),
     *     @SWG\Response(
     *         response=400,
     *         description="Bad request",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(
     *                 property="success",
     *                 type="boolean",
     *                 description="Статус запроса",
     *                 example=false
     *             ),
     *             @SWG\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Текст ошибки",
     *                 example="Не указан email"
     *             )
     *         )
     *     ),
     *     @SWG\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(
     *                 property="success",
     *                 type="boolean",
     *                 description="Статус запроса",
     *                 example=false
     *             ),
     *             @SWG\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Текст ошибки",
     *                 example="Token не найден"
     *             )
     *         )
     *     )
     * )
     */
    public function actionEmailChange()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        if (!$this->user) {
            Yii::$app->response->statusCode = 401;
            return ["success" => false, "message" => "Token не найден"];
        }

        $request = Yii::$app->request;
        $newEmail = trim($request->post('new_email'));

        if (!$newEmail) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => "Не указан email"];
        }

        $verificationCode = rand(100000, 999999);

        $this->user->setVerificationCode($verificationCode);
        $this->user->setVerificationCodeExpiration(time() + 3600); // Время действия кода - 1 час

        if (!$this->user->save()) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => $this->getError($this->user)];
        }

        $sended = Yii::$app->mailer->compose()
            ->setTo($newEmail)
            ->setFrom([Yii::$app->params['senderEmail'] => Yii::$app->params['senderName']])
            ->setSubject('Подтверждение смены email')
            ->setTextBody('Ваш код подтверждения для смены email: ' . $verificationCode)
            ->send();

        return ["success" => true, "message" => "На указанную почту отправлен код для подтверждения"];
    }

    /**
     * Подтверждение кода для смены email и сохранение новой почты
     *
     * @SWG\Post(
     *     path="/user/email-change-confirm",
     *     tags={"User"},
     *     summary="Подтверждение кода для смены email и сохранение новой почты",
     *     description="Проверяет переданный код подтверждения в БД и обновляет email пользователя",
     *      security={{"access_token":{}}},
     *     produces={"application/json"},
     *      @SWG\Parameter(
     *          name="body",
     *          in="body",
     *          description="Данные для смены почты",
     *          required=true,
     *          @SWG\Schema(
     *              type="object",
     *              @SWG\Property(
     *                  property="new_email",
     *                  type="string",
     *                  description="Новый email",
     *                  example="newmail@gmail.com"
     *              ),
     *              @SWG\Property(
     *                  property="verification_code",
     *                  type="string",
     *                  description="Код подтверждения",
     *                  example="123456"
     *              ),
     *              @SWG\Property(
     *                  property="two_factor",
     *                  type="string",
     *                  description="Код 2FA",
     *                  example="342451"
     *              )
     *          )
     *      ),
     *     @SWG\Response(
     *         response=200,
     *         description="OK",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(
     *                 property="success",
     *                 type="boolean",
     *                 description="Статус запроса",
     *                 example=true
     *             ),
     *             @SWG\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Сообщение",
     *                 example="Email успешно изменен"
     *             )
     *         )
     *     ),
     *     @SWG\Response(
     *         response=400,
     *         description="Bad request",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(
     *                 property="success",
     *                 type="boolean",
     *                 description="Статус запроса",
     *                 example=false
     *             ),
     *             @SWG\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Текст ошибки",
     *                 example="Не передан email"
     *             )
     *         )
     *     ),
     *     @SWG\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(
     *                 property="success",
     *                 type="boolean",
     *                 description="Статус запроса",
     *                 example=false
     *             ),
     *             @SWG\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Текст ошибки",
     *                 example="Token не найден"
     *             )
     *         )
     *     )
     * )
     */
    public function actionEmailChangeConfirm()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        if (!$this->user) {
            Yii::$app->response->statusCode = 401;
            return ["success" => false, "message" => "Token не найден"];
        }

        $request = Yii::$app->request;
        $verificationCode = trim($request->post('verification_code'));
        $twoFactorCode = trim($request->post('two_factor'));
        $newEmail = trim($request->post('new_email'));

        if (!$verificationCode) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => "Не передан код подтверждения"];
        }

        if (!$twoFactorCode) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => "Не передан код двухфакторной аутентификации"];
        }

        if (!$newEmail) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => "Не передан email"];
        }

        if (!$this->user->validateVerificationCode($verificationCode)) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => "Код подтверждения email не совпадает или истек его срок действия"];
        }

        if (User::find()->where(['email' => $newEmail])->andWhere(['<>', 'id', $this->user->id])->exists()) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => "Email уже используется"];
        }

        $twoFactor = UserTwoFactor::findOne(["user_id" => $this->user->id, "status" => 1]);
        if (!$twoFactor) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => "Секретная 2FA строка не найдена"];
        }

        $ga = new GoogleAuthenticator();
        $check = $ga->verifyCode($twoFactor->secret, $twoFactorCode, 2);
        if (!$check) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => "Неверный код 2FA"];
        }

        $oldEmail = $this->user->email;
        $this->user->email = $newEmail;
        $this->user->removeVerificationCode();

        if (!$this->user->save()) {
            Yii::$app->response->statusCode = 400;
            return ["success" => false, "message" => $this->getError($this->user)];
        }

        if ($oldEmail) {
            $this->user->block();
            FcmPushNotify::push(
                'Изменение email',
                'Ваш email был изменен. Включена блокировка аккаунта на 48 часов.',
                [$this->token->fcm_token]
            );
        }

        return ["success" => true, "message" => 'Email успешно изменен'];
    }

   
    protected function sendCode($user, $phone = null, $email = null)
    {
        // Yii::warning('Отправка кода');
        // Yii::warning('Отправка кода: ' . $email);
        // $code = rand(100000, 999999);

        // $authCode = new AuthCode(["user_id" => $user->id, "code" => $code, "date" => time()]);
        // $authCode->save();
        // if ($phone){
        //     Yii::warning('Отправка смс на номер: ' . $user->phone);
        //     $smsResponse = Yii::$app->sms->send($phone || $user->phone,  "Ваш код подтверждения: " . $code . ".");
        // } else if ($email || $user->email) {
        //     Yii::$app->mailer->compose()
        //         ->setTo($user->email)
        //         ->setFrom([Yii::$app->params['senderEmail'] => Yii::$app->params['senderName']])
        //         ->setSubject("Код подтверждения")
        //         ->setTextBody("Ваш код подтверждения: " . $code . ".")
        //         ->send();
        // } else {
        //     Yii::warning('Отправка смс на номер: ' . $user->phone);
        //     $smsResponse = Yii::$app->sms->send($user->phone,  "Ваш код подтверждения: " . $code . ".");
        // }
    }

    protected function getProfile($user)
    {
        // $chart = (object)[];
        // if ($user->chart) {
        //     $symbol = $user->chart->symbol == "USDT" ? "USDC" : $user->chart->symbol;

        //     $ch = curl_init("https://api.binance.com/api/v3/ticker/24hr?symbols=" . json_encode([$symbol . "USDT"]));
        //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        //     curl_setopt($ch, CURLOPT_HEADER, false);
        //     $res = curl_exec($ch);
        //     curl_close($ch);
        //     $result = json_decode($res);

        //     if (count($result) != 0) {
        //         $chart = [
        //             "id" => $user->chart->id,
        //             "name" => $user->chart->name,
        //             "symbol" => $user->chart->symbol,
        //             "price" => $result[0]->lastPrice,
        //             "lowPrice" => $result[0]->lowPrice,
        //             "highPrice" => $result[0]->highPrice,
        //             "percent" => $result[0]->priceChangePercent,
        //             "icon" => Url::to(["/images/icons/" . $user->chart->symbol . ".png"], "https"),
        //         ];
        //     }
        // }

        return [
            "id" => $user->id,
            "login" => $user->login,
            "email" => $user->email,
            "phone" => $user->phone,
            "first_name" => $user->first_name,
            "last_name" => $user->last_name,
            "patronymic" => $user->patronymic,
            "verify_status" => $user->verify_status,
            "telegram" => $user->telegram,
            // "currency" => [
            //     "id" => $user->currency->id,
            //     "name" => $user->currency->name,
            //     "symbol" => $user->currency->symbol,
            //     "icon" => Url::to([$user->currency->getImage()->getUrl()], "https")
            // ],
            // "chart" => $chart,
            "status" => $user->status,
            "two_factor" => (bool) UserTwoFactor::find()->where(["user_id" => $user->id, "status" => 1])->count(),
            "image" => Url::to([$user->getImage()->getUrl("100x100")], "https"),
        ];
    }
}
