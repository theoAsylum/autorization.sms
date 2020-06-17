<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use \Bitrix\Main\Page\Asset;

class SmsAuthorizationFourPx extends CBitrixComponent
{
    public function endWordSeconds($count){
        switch ($count):
            case 1:case 21:case 31:case 41:case 51:
            return 'у';
            case 2:case 3:case 4:case 22:case 23:case 24:case 32:case 33:case 34:case 42:case 43:case 44:case 52:case 53:case 54:
            return 'ы';
            default:
                return '';
        endswitch;
    }

    public function checkPhone($phone){
        $phone = str_replace([' ', '(', ')', '-', '_', '+'], '', $phone);
        $first = $phone[0];
        if($first == '8'){
            $phone = '7'.substr($phone, 1);
        }
        if(strlen($phone) == 11 && $phone[0] == '7'){
            return $phone;
        }else{
            return false;
        }
    }

    public function checkCodeFromSms($code){
        if(strlen($code) == 6 && ctype_digit($code)){
            return $code;
        }else{
            return false;
        }
    }

    public function getUserByPhone($phone){
        $resUser = CUser::GetList($by = "SORT", $order = "ASC", array("PERSONAL_MOBILE" => $phone, "ACTIVE" => "Y"), ['SELECT' => ['ID','UF_SHORT_PASS','UF_SHORT_PASS_TIME']]);
        if($arUser = $resUser->Fetch()){
            $arResult['UF_SHORT_PASS_TIME'] = (int)$arUser['UF_SHORT_PASS_TIME'];
            $arResult['UF_SHORT_PASS'] = $arUser['UF_SHORT_PASS'];
            $arResult['ID'] = $arUser['ID'];
            return $arResult;
        }else{
            return false;
        }
    }

    public function checkGoogleRecaptcha($response){
        $httpClient = new \Bitrix\Main\Web\HttpClient;
        $result = $httpClient->post(
            'https://www.google.com/recaptcha/api/siteverify',
            [
                'secret' => GOOGLE_RECAPTCHA_SECRET_KEY,
                'response' => $response,
                'remoteip' => $_SERVER['HTTP_X_REAL_IP']
            ]
        );
        $result = json_decode($result, true);

        if ($result['success'] !== true) {
            return false;
        }else{
            return true;
        }
    }

    public function sendCodeBySms($phone, $code, $apiKey = false)
    {
        if(strlen($phone) == 11){
            $phone = substr($phone, 1);
        }
        $arCookies = apiAuthorize();
        if($arCookies){
            $httpClient = new \Bitrix\Main\Web\HttpClient;
            $httpClient->setCookies($arCookies);
            $url = API_ADDRESS.'/apilk/predscoring/'.API_STRING_SMS.'/?phonenumber='.$phone.'&smstext=Ваш%20код%20'.$code;
            $result = $httpClient->get($url);
            $result = json_decode($result,true);
            if($result['status'] && $result['message'] == 'Ok'){
                return true;
            }else{
                return false;
            }
        }
    }

    public function executeComponent()
    {
        global $APPLICATION;

        $error = $phone_error = $renew_code = $success = false;

        if($_REQUEST['phone'] && !$_REQUEST['code'] && $_REQUEST['g-recaptcha-response']){

            $recaptcha = self::checkGoogleRecaptcha($_REQUEST['g-recaptcha-response']);
            if($recaptcha){
                $time_now = time();
                $phone = self::checkPhone($_REQUEST['phone']);
                if($phone){
                    $arUser = self::getUserByPhone($phone);
                    if($arUser){
                        if($arUser['UF_SHORT_PASS_TIME'] && $time_now - $arUser['UF_SHORT_PASS_TIME'] < 60 ){
                            $seconds_past = $time_now - $arUser['UF_SHORT_PASS_TIME'];
                            $seconds_left = 60 - $seconds_past;
                            $renew_code = true;
                            $error = 'Выслать код повторно возможно через <span id="exp_time">'.$seconds_left.'</span> секунд<span id="end_word">'.self::endWordSeconds($seconds_left).'</span>.';
                        }else{
                            $gen_code = rand(100000,999999);
                            $result = self::sendCodeBySms($phone, $gen_code);
                            if($result){
                                $user = new CUser;
                                $user->Update($arUser['ID'], array("UF_SHORT_PASS" => $gen_code, "UF_SHORT_PASS_TIME" => $time_now));
                            }else{
                                $error = 'Ошибка при отправке СМС.';
                            }
                        }

                    }else{
                        $error = 'Телефон в базе не найден. Пожалуйста, заполните <a href="/personal/">анкету</a>.';
                    }
                }else{
                    $phone_error = true;
                    $error = 'Неверный формат номера телефона.';
                }
            }else{
                $error = 'Подтвердите, что вы не робот.';
            }

        }elseif($_REQUEST['phone'] && $_REQUEST['code'] && $_REQUEST['g-recaptcha-response']){

            $recaptcha = self::checkGoogleRecaptcha($_REQUEST['g-recaptcha-response']);
            if($recaptcha){
                $time_now = time();
                $phone = self::checkPhone($_REQUEST['phone']);
                if($phone){
                    $code = self::checkCodeFromSms($_REQUEST['code']);
                    if($code){
                        $arUser = self::getUserByPhone($phone);
                        if($arUser){
                            if($arUser['UF_SHORT_PASS_TIME'] && $time_now-$arUser['UF_SHORT_PASS_TIME'] < 60){
                                if($arUser['UF_SHORT_PASS'] && $code == $arUser['UF_SHORT_PASS']){
                                    $user = new CUser;
                                    $user->Update($arUser['ID'],array("UF_SHORT_PASS"=>'',"UF_SHORT_PASS_TIME"=>''));
                                    $user->Authorize($arUser['ID']);
                                    if(!$_REQUEST['AJAX']){
                                        localredirect("/");
                                    }else{
                                        $success = 'Вы успешно авторизированы.';
                                    }
                                }else{
                                    $renew_code = true;
                                    $error = 'Неверный код.';
                                }
                            }else{
                                $error = 'Код устарел.';
                            }
                        }else{
                            $error = 'Телефон в базе не найден. Пожалуйста, заполните <a href="/personal/">анкету</a>.';
                        }
                    }else{
                        $renew_code = true;
                        $error = 'Неверный формат кода.';
                    }
                }else{
                    $phone_error = true;
                    $error = 'Неверный формат номера телефона.';
                }
            }else{
                $error = 'Подтвердите, что вы не робот.';
            }

        }elseif(empty($_REQUEST['phone']) && isset($_REQUEST['phone'])){
            $error = 'Введите номер телефона.';
        }

        if(($_REQUEST['phone'] && !$error) || ($_REQUEST['phone'] && $renew_code)){
            $this->arResult['FORM_CODE'] = true;
            $this->arResult['TEXT_SEND'] = 'Войти';
        }else{
            $this->arResult['TEXT_SEND'] = 'Получить код';
        }

        if((!$error || $renew_code) && $_REQUEST['phone']){
            $this->arResult['RENEW_CODE'] = true;
        }

        $this->arResult['REQUEST'] = $_REQUEST;
        if($error) $this->arResult['ERROR'] = $error;
        if($success) $this->arResult['SUCCESS'] = $success;
        if($phone_error) $this->arResult['PHONE_ERROR'] = $phone_error;

        if($_REQUEST["AJAX"] === "Y"){

            $APPLICATION->RestartBuffer();

            $this->includeComponentTemplate();

            CMain::FinalActions();

            die();

        }else{

            Asset::getInstance()->addCss($this->GetPath().'/style.css');
            Asset::getInstance()->addJs($this->GetPath().'/script.js');
            Asset::getInstance()->addJs('https://www.google.com/recaptcha/api.js?render='.GOOGLE_RECAPTCHA_PUBLIC_KEY);

            $this->includeComponentTemplate();

        }

    }


}