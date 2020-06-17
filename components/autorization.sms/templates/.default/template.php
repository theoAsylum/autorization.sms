<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */
?>

<div class="section pa-form__section">
    <div class="container">
        <h1 class="title title_center-black">Вход в Личный кабинет</h1>
        <form action="<?=$APPLICATION->GetCurPage()?>" method="POST" enctype="multipart/form-data" name="form" class="pa-form">
            <label class="pa-form__field">
                <input type="tel" class="pa-form__input js-masked js-validate" placeholder="+7 (___) ___-__-__" name="phone"<?if($arResult['REQUEST']['phone'] && !$arResult['PHONE_ERROR']){?> value="<?=$arResult['REQUEST']['phone']?>"<?}?>>
            </label>
            <?if($arResult['FORM_CODE']){
                $text_send = 'Войти';?>
                <label class="pa-form__field">
                    <input type="text" class="pa-form__input js-validate" placeholder="Код из смс" name="code">
                </label>
            <?}else{
                $text_send = 'Получить код';
            }?>
            <div class="pa-form__field">
                <button class="pa-form__btn button button_filled" type="submit"><?=$text_send?></button>
            </div>
        </form>
        <div class="pa-form__text-part">
            <?if(!$arResult['REQUEST']['phone']){?>
                <div class="pa-form__text">На указанный номер будет отправлен Код для входа в ЛК</div>
            <?}?>
            <?if($arResult['RENEW_CODE']){?>
                <div class="pa-form__resend">
                    <br> <a href="javascript:sendAgain();">Выслать код повторно</a>
                </div>
            <?}?>
            <?if($arResult['ERROR']){?>
                <div class="pa-form__error-text js-pa-error-text"><?=$arResult['ERROR']?></div>
            <?}elseif($arResult['SUCCESS']){?>
                <div class="pa-form__success-text js-pa-error-text"><?=$arResult['SUCCESS']?></div>
            <?}?>
        </div>
    </div>
</div>