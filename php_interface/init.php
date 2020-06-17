<?php
use \Bitrix\Main\Application,
    \Bitrix\Main\Web\Uri,
    \Bitrix\Main\Web\HttpClient;


define('API_LOGIN', 'apiforlanding');
define('API_PASS', 'e1YZEq.Qx:q(a]5%EX');
define('API_ADDRESS', 'https://torin-dev.com');
define('API_STRING_SEND', 'xhxpjpywuwpnpkqtvqwgzsjkgybsljsowlbkytttkwlgbshpbbdufszlpkbm');
define('API_STRING_STATUS', 'xhxpjpywuwpnpkqtvqwgzsjkgybsljsowlbkytttkwlgbshpbbdufszlpkbm');
define('API_STRING_SMS', 'sfnlkksdfglbjlbsdlbjasjigecbghsdvajdvsdfhavajfvejdkafkshdvakd');

function getIblockIdByCode($arIblockCode) {
    $arIblockCode = (string)$arIblockCode;
    $cache = \Bitrix\Main\Data\Cache::createInstance();
    $cacheId = md5($arIblockCode);
    $cacheInitDir = 'iblock_id_by_code';

    if ($cache->initCache(36000, $cacheId, $cacheInitDir))
    {
        $arIblockID = $cache->getVars();
    } elseif ($cache->startDataCache()) {
        if (\Bitrix\Main\Loader::includeModule('iblock')) {
            if ($rsIblocks = \CIBlock::GetList([], ['CODE' => $arIblockCode,'CHECK_PERMISSIONS' => 'N'])) {
                if ($iblock = $rsIblocks->Fetch()) {
                    $arIblockID = $iblock['ID'];
                }
            }
        }
        $cache->endDataCache($arIblockID);
    }
    return $arIblockID;
}

function apiAuthorize(){
    if(defined('API_LOGIN') && defined('API_PASS') && defined('API_ADDRESS')){
        $httpClient = new \Bitrix\Main\Web\HttpClient;
        $result = $httpClient->get(
            API_ADDRESS.'/api/account/auth/?email='.API_LOGIN.'&password='.API_PASS
        );
        $result = json_decode($result, true);
        if($result['status'] && $result['session']){
            return $httpClient->getCookies()->toArray();
        }else{
            return false;
        }
    }else{
        return false;
    }
}

function sendApiPredscoring($arCookies, $arFields){
    $optionsHttpClient=array(
        "redirect"=>true,
        "redirectMax"=>5,
        "waitResponse"=>true,
        "socketTimeout"=>120,
        "streamTimeout"=>0,
        "version"=>\Bitrix\Main\Web\HttpClient::HTTP_1_1,
        "proxyHost"=>"",
        "proxyPort"=>"",
        "proxyUser"=>"",
        "proxyPassword"=>"",
        "compress"=>false,
        "charset"=>"",
        "disableSslVerification"=>true,
    );
    if($arCookies){
        if($arFields['name']){
            $arFields['NAME'] = $arFields['name'];
            $arFields['LAST_NAME'] = $arFields['surname'];
            $arFields['SECOND_NAME'] = $arFields['second_name'];
        }
        if($arFields['PERSONAL_BIRTHDAY']) $arFields['date'] = $arFields['PERSONAL_BIRTHDAY'];
        if($arFields['PERSONAL_MOBILE']) $arFields['phone'] = $arFields['PERSONAL_MOBILE'];
        if($arFields['EMAIL']) $arFields['email'] = $arFields['EMAIL'];

        $httpClient = new \Bitrix\Main\Web\HttpClient($optionsHttpClient);
        $httpClient->setCookies($arCookies);
        if(strlen($arFields['phone']) == 11){
            $arFields['phone'] = substr($arFields['phone'], 1);
        }
        $url = API_ADDRESS.'/apilk/predscoring/'.API_STRING_SEND.'/?firstname='.$arFields['NAME'].'&lastname='.$arFields['LAST_NAME'].'&middlename='.$arFields['SECOND_NAME'].'&birthdate='.$arFields['date'].'&inn='.$arFields['inn'].'&email='.$arFields['email'].'&phone='.$arFields['phone'];
        $result = $httpClient->get($url);
        $result = json_decode($result, true);
        if($result['status'] && $result['data']['Status']){
            $arResult['status'] = $result['data']['Status'];
            $arResult['lead'] = $result['data']['Lead'];
            return $arResult;
        }else{
            return false;
        }
    }else{
        return false;
    }
}

function getPredscoringStatus($arCookies, $leadId){
    if($arCookies && $leadId){
        $httpClient = new \Bitrix\Main\Web\HttpClient;
        $httpClient->setCookies($arCookies);
        $result = $httpClient->get(
            API_ADDRESS.'/apilk/predscoring/'.API_STRING_STATUS.'/?leadnumber='.$leadId
        );
        file_put_contents($_SERVER['DOCUMENT_ROOT'].'/local/.log/getPredscoringStatus_'.date('y-m-d_H-i-s').'.log', var_export($result, true));
        $result = json_decode($result, true);
        if($result['status'] && $result['data']['Status']){
            $arResult['status'] = $result['data']['Status'];
            $arResult['lead'] = $result['data']['Lead'];
            return $arResult;
        }else{
            return false;
        }
    }else{
        return false;
    }
}

function getCompanyByInn($inn){
    $arFilter = [
        'IBLOCK_ID'=>getIblockIdByCode('company'),
        'ACTIVE'=>'Y',
        '=PROPERTY_INN'=> $inn,
    ];
    $res = CIBlockElement::GetList(["ID"=>"DESC"], $arFilter, false, ['nTopCount' => 1], ['ID', 'NAME', 'IBLOCK_ID']);
    if($arCompany = $res->GetNext(true, false)){
        return $arCompany['ID'];
    }
    return false;
}

function updatePredscoringByApi(){
    if (!\Bitrix\Main\Loader::includeModule('iblock')) return "updatePredscoringByApi();";
    $iblockId = getIblockIdByCode('company');
    $arFilter = [
        'IBLOCK_ID'=>$iblockId,
        'ACTIVE'=>'Y',
        '!PROPERTY_LEAD' => false,
        '<TIMESTAMP_X' => array(ConvertTimeStamp(time()-3600*6, "FULL"))
    ];

    $res = CIBlockElement::GetList(["TIMESTAMP_X"=>"ASC"], $arFilter, false, ['nTopCount' => 10], ['ID', 'NAME', 'IBLOCK_ID','PROPERTY_LEAD']);

    if($res->SelectedRowsCount()){
        $arCookies = apiAuthorize();
        $el = new CIBlockElement;
        if($arCookies){

            while($arElement = $res->GetNext(true, false)){
                $arLog[$arElement['ID']]['ID'] = $arElement['ID'];
                $arResponse = getPredscoringStatus($arCookies, $arElement['PROPERTY_LEAD_VALUE']);
                if($arResponse){
                    $arLog[$arElement['ID']]['RESPONSE'] = $arResponse;
                    CIBlockElement::SetPropertyValuesEx($arElement["ID"],$iblockId,array('STATUS'=>$arResponse['status'],'LEAD'=>$arResponse['lead']));
                }
                $el->Update($arElement["ID"],Array('TIMESTAMP_X'=>true));

            }
        }
    }

    return "updatePredscoringByApi();";
}