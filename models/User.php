<?php

namespace app\models;

use Yii;

/**
 * @SWG\Definition(required={"login", "email"})
 *
 * @SWG\Property(property="id", type="integer", description="Идентификатор")
 * @SWG\Property(property="email", type="string", description="Электронная почта", example="mail@mail.com")
 * @SWG\Property(property="login", type="string", description="login", example="fancy_login")
 * @SWG\Property(property="token", type="string")
 * @SWG\Property(property="first_name", type="string", description="Имя", example="Иван")
 * @SWG\Property(property="last_name", type="string", description="Фамилия", example="Иванов")
 * @SWG\Property(property="patronymic", type="string", description="Отчество", example="Иванович")
 */
class User extends \yii\db\ActiveRecord implements \yii\web\IdentityInterface
{

    public $image;

    public $currentToken;

    public function behaviors()
    {
        return [
            'image' => [
                'class' => 'rico\yii2images\behaviors\ImageBehave',
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['password', 'login'], 'required'],
            ['login', 'unique'],
            [['image'], 'file', 'extensions' => 'png, jpg, jpeg'],
            [['password', 'token', 'first_name', 'last_name', 'patronymic'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'password' => 'Пароль',
            'image' => 'Аватар',
            'login' => 'Логин',
            'token' => 'Token',
            'created_at' => 'Дата создания',
        ];
    }

    public function getAuthTokens()
    {
        return $this->hasMany(AuthToken::class, ['user_id' => 'id']);
    }

    public static function findIdentity($id)
    {
        return static::findOne($id);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        if ($token === null)
            return null;

        $authToken = AuthToken::find()->where(['token' => $token])->andWhere(['>', 'expired_at', time()])->one();
        if (!$authToken) {
            return null;
        }

        return $authToken;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAuthKey()
    {
        return $this->token;
    }

    public function validateAuthKey($authKey)
    {
        return $this->token === $authKey;
    }

    public function validatePassword($password)
    {
        return Yii::$app->getSecurity()->validatePassword($password, $this->password);
    }

  	public function upload()
    {
  			$path = 'uploads/' . $this->image->baseName . '.' . $this->image->extension;
  			$this->image->saveAs($path);
  			$this->attachImage($path);
  			@unlink($path);
  	}

    public function setVerificationCode($code)
    {
        $this->verification_code = $code;
    }

    public function setVerificationCodeExpiration($time)
    {
        $this->verification_code_expiration = $time;
    }

    public function removeVerificationCode()
    {
        $this->verification_code = null;
        $this->verification_code_expiration = null;
    }

    public function validateVerificationCode($code)
    {
        return $this->verification_code == $code && $this->verification_code_expiration > time();
    }

    public function setToken($fcmToken) {
        $date = new \DateTime();
        $date->modify("+1440 minutes");

        $authToken = new AuthToken();
        $authToken->user_id = $this->id;
        $authToken->token = Yii::$app->security->generateRandomString();
        $authToken->fcm_token = !is_null($fcmToken) ? $fcmToken : null;
        $authToken->expired_at = $date->getTimestamp();
        $authToken->save();
        return $authToken;
    }

    public function block() {
        $date = new \DateTime();
        $date->modify("+30 minutes");
        $this->blocked_before = $date->getTimestamp();
        $this->save();
        AuthToken::deleteAll(['user_id' => $this->id]);
    }
}
