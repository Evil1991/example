<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * @var CBitrixComponentTemplate $this
 * @var CatalogElementComponent $component
 * @var array $arResult
 */
use Bitrix\Main\Loader;
use Bitrix\Highloadblock as HL;
use Bitrix\Main\Entity;

Loader::includeModule("highloadblock");

$idMainColors = array();

foreach ($arResult['OFFERS'] as $key => $offer) {
    $idMainColors[] = $offer['PROPERTIES']['MAIN_COLOR']['VALUE'];
}

$hlbl = 3; // Таблица цветов
$hlblock = HL\HighloadBlockTable::getById($hlbl)->fetch();

$entity = HL\HighloadBlockTable::compileEntity($hlblock);
$entity_data_class = $entity->getDataClass();

$rsData = $entity_data_class::getList(array(
    "select" => array("*"),
    "order" => array("ID" => "ASC"),
    "filter" => array("UF_XML_ID"=> $idMainColors)
));
$arResult['MAIN_COLORS'] = array();
while($arData = $rsData->Fetch()){
    $arResult['MAIN_COLORS'][] = $arData;
}

$component = $this->getComponent();
if ($arResult["PROPERTIES"]["TYPE_ITEM"]["VALUE_XML_ID"] == "tool" || $arResult["PROPERTIES"]["TYPE_ITEM"]["VALUE_XML_ID"] == "wax"){
    $arParams = $component->applyTemplateModifications();
}

foreach ($arResult['MAIN_COLORS'] as $mainColorBlock) {
    foreach ($arResult['OFFERS'] as $keyOffer => $offer) {
        if ($offer['PROPERTIES']['MAIN_COLOR']['VALUE'] == $mainColorBlock['UF_XML_ID']  && $offer['PROPERTIES']['TYPE_SHOW']['VALUE_XML_ID'] == "TOP") {
            $arResult[$mainColorBlock['UF_XML_ID']]['SHOW_TOP'] = "Y";
        }
        if ($offer['PROPERTIES']['MAIN_COLOR']['VALUE'] == $mainColorBlock['UF_XML_ID']  && $offer['PROPERTIES']['TYPE_SHOW']['VALUE_XML_ID'] == "DOWN") {
            $arResult[$mainColorBlock['UF_XML_ID']]['SHOW_DOWN'] = "Y";
        }
    }
}

foreach ($arResult["PROPERTIES"]["PHOTO_360"]["VALUE"] as $photo){
    $arResult["PHOTO_360"][] = CFile::getPath($photo);
}

if ($arResult["PROPERTIES"]["TYPE_ITEM"]["VALUE_XML_ID"] == "ground"){
    global $USER;

    $hlblFavourites = 2; // Таблица Фаворитов
    $hlblockFavourites = HL\HighloadBlockTable::getById($hlblFavourites)->fetch();

    $entityFavourites = HL\HighloadBlockTable::compileEntity($hlblockFavourites);
    $entity_data_classFavourites = $entityFavourites->getDataClass();

    $rsDataFavourites = $entity_data_classFavourites::getList(array(
        "select" => array("*"),
        "order" => array("ID" => "ASC"),
        "filter" => array("UF_ID_USER"=> $USER->GetID())
    ));
    $arResult['FAVOURITES'] = array();
    while($arDataFavourites = $rsDataFavourites->Fetch()){
        $arResult['FAVOURITES'][] = $arDataFavourites;
    }
}

$arSelect = Array("ID", "ACTIVE", "IBLOCK_ID", "NAME", "DATE_CREATE", "DATE_ACTIVE_FROM", "PREVIEW_TEXT", "PROPERTY_ID_ITEM", "PROPERTY_EVALUATION", "PROPERTY_ID_USER", "PROPERTY_COMMENT", "PROPERTY_USER_NAME", "PROPERTY_PHOTO");
$arFilter = Array("IBLOCK_ID"=> 5, "PROPERTY_ID_ITEM" => $arResult['ID'], 'ACTIVE' => 'Y');
$res = CIBlockElement::GetList(Array('created' => 'desc'), $arFilter, false, false, $arSelect);
$totalRev = 0;
$countRev = 0;
while($ob = $res->GetNext())
{
    if (!$arResult['REVIEWS']['COMMENTS'][$ob['ID']]) {
        $arResult['REVIEWS']['COMMENTS'][$ob['ID']]['DATA'] = $ob;
        if ($ob['PROPERTY_PHOTO_VALUE']) {
            $arResult['REVIEWS']['COMMENTS'][$ob['ID']]['PHOTOS'][] = $ob['PROPERTY_PHOTO_VALUE'];
        }
        $arResult['REVIEWS']['COMMENTS'][$ob['ID']]["DATE_CREATE"] = explode(" ", $arResult['REVIEWS']['COMMENTS'][$ob['ID']]['DATA']["DATE_CREATE"]);
        if ($ob['PROPERTY_EVALUATION_VALUE'] <= 5) {
            $totalRev += $ob['PROPERTY_EVALUATION_VALUE'];
            $countRev++;
        }
    } else {
        $arResult['REVIEWS']['COMMENTS'][$ob['ID']]['PHOTOS'][] = $ob['PROPERTY_PHOTO_VALUE'];
    }


}
if ($totalRev != 0 && $countRev != 0) {
    $arResult['REVIEWS']['AVERAGE'] = round($totalRev/$countRev, 1, PHP_ROUND_HALF_UP);
    $arResult['REVIEWS']['COUNT'] = $countRev;
}

$countItem = 0;
$arVolume = array();
$arVolumeCheck = array();
foreach ($arResult['OFFERS'] as $offer) {
    $countItem += $offer['PRODUCT']['QUANTITY'];
    if (!$minPrice) {
        $minPrice = $offer['MIN_PRICE']['ROUND_VALUE_VAT'];
    } elseif ($minPrice > $offer['MIN_PRICE']['ROUND_VALUE_VAT']) {
        $minPrice = $offer['MIN_PRICE']['ROUND_VALUE_VAT'];
    }

    if (!in_array($offer['PROPERTIES']['VOLUME']['VALUE'], $arVolumeCheck) && !empty($offer['PROPERTIES']['VOLUME']['VALUE'])) {
        $arVolumeCheck[] = $offer['PROPERTIES']['VOLUME']['VALUE'];
        $arVolume[$offer['ID']]['PRICE']['VALUE'] = $offer['MIN_PRICE']['VALUE'];
        $arVolume[$offer['ID']]['PRICE']['DISCOUNT_VALUE'] = $offer['MIN_PRICE']['DISCOUNT_VALUE'];
        $arVolume[$offer['ID']]['PRICE']['VALUE'] = $offer['MIN_PRICE']['VALUE'];
        $arVolume[$offer['ID']]['VALUE'] = $offer['PROPERTIES']['VOLUME']['VALUE'];
        $arVolume[$offer['ID']]['TYPE_GROUND'] = $offer['PROPERTIES']['TIPE_GROUND']['VALUE_XML_ID'];
    }
}
foreach ($arVolume as $keyVolume => &$volume) {
    foreach ($arResult['OFFERS'] as $offer) {
        if ($volume['VALUE'] ==  $offer['PROPERTIES']['VOLUME']['VALUE']) {
            $volume['ID_ITEMS'][] = $offer['ID'];
        }
    }
}
unset($volume);
usort($arVolume, function($a, $b){
    return ($a['VALUE'] - $b['VALUE']);
});
unset ($arVolumeCheck);
$arResult['VOLUME_OFFERS'] = $arVolume;

$arResult['MIN_PRICE_OFFERS'] = array();
$arResult['COUNT_ITEM'] = $countItem;
$arResult['MIN_PRICE_OFFERS'] = $minPrice;
$arResult['ITEM_OFFERS_MEASURE'] = $arResult['OFFERS'][0]['ITEM_MEASURE']['TITLE'];

$cp = $this->__component;

if (is_object($cp))
{
    $cp->arResult['ID'] = $arResult['ID'];
    $cp->arResult['FAVOURITES'] = $arResult['FAVOURITES'];
    $cp->arResult['GET_EFFECT'] = $arResult['PROPERTIES']['GET_EFFECT']['VALUE'];
    $cp->arResult['SIMILAR_ITEMS'] = $arResult['PROPERTIES']['SIMILAR_ITEMS']['VALUE'];
    $cp->SetResultCacheKeys(array('ID','FAVOURITES','SIMILAR_ITEMS','GET_EFFECT'));

    $arResult['ID'] = $cp->arResult['ID'];
    $arResult['FAVOURITES'] = $cp->arResult['FAVOURITES'];
    $arResult['GET_EFFECT'] = $cp->arResult['GET_EFFECT'];
    $arResult['SIMILAR_ITEMS'] = $cp->arResult['SIMILAR_ITEMS'];
    if ($arResult["PROPERTIES"]["TYPE_ITEM"]["VALUE_XML_ID"] == "tool"){
        $cp->arResult['IS_TOOLS'] = $arResult['PROPERTIES']['TYPE_ITEM']['VALUE_XML_ID'];
        $arResult['IS_TOOLS'] = $cp->arResult['IS_TOOLS'];
    }
}
if ($arResult["PROPERTIES"]["TYPE_ITEM"]["VALUE_XML_ID"] == "detail_effect"){
    $arResult['IS_FAVOURITES'] = false;
    foreach ($arResult['FAVOURITES'] as $favourite) {
        if ($arResult['ID'] == $favourite['UF_ID_ITEM']) {
            $arResult['IS_FAVOURITES'] = true;
            break;
        }
    }
}


$arResult["RECOMMENDED_PRODUCT"] = array();
if (!empty($arResult["PROPERTIES"]["RECOMMENDED_PRODUCTS"]["VALUE"])) {
    $arResult["RECOMMENDED_PRODUCT"] = $arResult["PROPERTIES"]["RECOMMENDED_PRODUCTS"]["VALUE"];
}

$shades = array();
$idOffersDoubleShade = array();
foreach ($arResult['OFFERS'] as &$offer) {
    if (in_array($offer['PROPERTIES']['SHADE']['VALUE'], $shades)) {
        $offer['SHOW'] = false;
    } else {
        $offer['SHOW'] = true;
        $shades[] = $offer['PROPERTIES']['SHADE']['VALUE'];
    }

    if ($offer['PROPERTIES']['DEFAULT']['VALUE'] == "Y") {
        $mainColorDefault = $offer['PROPERTIES']['MAIN_COLOR']['VALUE'];
    }
    $idOffersDoubleShade[$offer['PROPERTIES']['SHADE']['VALUE']][] = $offer['ID'];
}
unset($offer);
foreach ($idOffersDoubleShade as $keyD => $double) {
    foreach ($arResult['OFFERS'] as &$offer) {
        if ($keyD == $offer['PROPERTIES']['SHADE']['VALUE']) {
            $offer['ID_DOUBLE_ITEMS'] =  $double;
        }
    }
}
unset($offer);
foreach ($arResult['MAIN_COLORS'] as $keyMainColor => &$mainColor){
    foreach ($arResult['OFFERS'] as $keyOffer => $offer){
        if ($offer['PROPERTIES']['MAIN_COLOR']['VALUE'] == $mainColor['UF_XML_ID']){
            $mainColor['ITEMS'][] = $offer['PROPERTIES']['MAIN_COLOR']['VALUE'];
        }
        if ($mainColor['UF_XML_ID'] == $mainColorDefault) {
            $mainColor['DEFAULT'] = true;
        } else {
            $mainColor['DEFAULT'] = false;
        }
    }
}
unset($mainColor);
if ($mainColorDefault) {
    usort($arResult['MAIN_COLORS'], function($a, $b){
        return ($b['DEFAULT'] - $a['DEFAULT']);
    });
} else {
    usort($arResult['OFFERS'], function($a, $b){
        return ($b['SORT'] - $a['SORT']);
    });
}

if ($arResult['PROPERTIES']['SHOW_DOCS_TAB']['VALUE_XML_ID'] == 'Y'){
    $arResult['PROPERTIES']['DOC']['VALUE'] = array();
    $res = CIBlockElement::GetProperty($arResult["IBLOCK_ID"], $arResult["ID"], "sort", "asc", array("CODE" => "DOC"));
    $docCount = 0;
    while ($ob = $res->GetNext())
    {
        if (!empty($ob["VALUE"]) && !empty($ob["DESCRIPTION"])){
            $docCount++;
            $arResult['PROPERTIES']['DOC']['VALUE'][] = $ob;
        }
        if ($docCount == 10){
            break;
        }
    }
}


if ($arResult['PROPERTIES']['SHOW_VIDEO_TAB']['VALUE_XML_ID'] == 'Y'){
    foreach ($arResult['PROPERTIES']['VIDEO_TAB']['VALUE'] as $key => $link){
        $link = explode('/', $link);
        if ($link['2'] == 'www.youtube.com'){
            $link['3'] = ltrim($link['3'], 'watch?v=');
        }
        $arResult['PROPERTIES']['VIDEO_TAB']['VALUE'][$key] = '
            <iframe 
                width="100%" 
                height="100%" 
                src="https://www.youtube.com/embed/' . $link['3'] . '" 
                title="YouTube video player" 
                frameborder="0" 
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                allowfullscreen>
            </iframe>
        ';
    }
}

if (!empty($arResult['PROPERTIES']['VIDEO']['VALUE'])) {
    foreach ($arResult['PROPERTIES']['VIDEO']['VALUE'] as $key => &$linkVideoSlider) {
        $link = explode('/', $linkVideoSlider);
        if ($link['2'] == 'www.youtube.com'){
            $link['3'] = ltrim($link['3'], 'watch?v=');
        }
        $linkVideoSlider = 'https://www.youtube.com/embed/' . $link['3'] . '';
    }
}

/**
 * Приоритет вывода табов на деталке.
 * Если будет меняться порядок табов на деталке - приоритет нужно также изменить.
 */
$arTabsPriority = array(
    'SHOW_DESCRIPTION_TAB' => $arResult['PROPERTIES']['SHOW_DESCRIPTION_TAB']['VALUE_XML_ID'],
    'SHOW_SPECIFICATIONS_TAB' => $arResult['PROPERTIES']['SHOW_SPECIFICATIONS_TAB']['VALUE_XML_ID'],
    'SHOW_DOCS_TAB' => $arResult['PROPERTIES']['SHOW_DOCS_TAB']['VALUE_XML_ID'],
    'SHOW_VIDEO_TAB' => $arResult['PROPERTIES']['SHOW_VIDEO_TAB']['VALUE_XML_ID'],
    'SHOW_REVIEW_TAB' => 'Y'
);
$key = array_search('Y', $arTabsPriority);
$arResult['PROPERTIES'][$key]['ACTIVE_TAB'] = 'active';

$this->__component->SetResultCacheKeys(array("RECOMMENDED_PRODUCT", 'ID','FAVOURITES'));
