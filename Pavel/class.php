<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Application,
    Bitrix\Main\Loader,
    Bitrix\Main\Engine\Contract\Controllerable;

Loader::includeModule('iblock');
Loader::includeModule('sale');

CJSCore::Init(array("fx", "ajax"));


class PersonalAddresses extends \CBitrixComponent implements Controllerable
{
    private $iBlockId = 18,
        $arRequiredFields = [
        'street',
        'house',
        'flat',
        'entrance',
        'floor',
    ];

    public function configureActions()
    {
        return [
            'delete' => [
                'prefilters' => []
            ],
            'addAddress' => [
                'prefilters' => [],
            ],
            'getAddress' => [
                'prefilters' => [],
            ],
        ];
    }

    /**
     * @param $id
     * @return string[]
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function deleteAction($id)
    {
        $item = $this->getAddressItem($id);
        if ($item) {
            $item->delete();
            $result = ['status' => 'success'];
        } else {
            $result = ['status' => 'error', 'message' => 'Адрес не найден'];
        }
        return $result;
    }

    /**
     * @param $id
     * @return array|string[]
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function getAddressAction($id)
    {
        $item = $this->getAddressItem($id,[
            'PREVIEW_TEXT',
            'CITY',
            'STREET',
            'HOUSE',
            'CORPS',
            'FLAT',
            'ENTRANCE',
            'FLOOR',
        ]);
        if ($item) {
            $arItem = [
                'id'=>$item->getId(),
                'preview_text'=>$item->getPreviewText(),
                'city'=>$item->getCity()?$item->getCity()->getValue():'',
                'street'=>$item->getStreet()?$item->getStreet()->getValue():'',
                'house'=>$item->getHouse()?$item->getHouse()->getValue():'',
                'corps'=>$item->getCorps()?$item->getCorps()->getValue():'',
                'flat'=>$item->getFlat()?$item->getFlat()->getValue():'',
                'entrance'=>$item->getEntrance()?$item->getEntrance()->getValue():'',
                'floor'=>$item->getFloor()?$item->getFloor()->getValue():'',
            ];
            $result = ['status' => 'success','item'=>$arItem];
        } else {
            $result = ['status' => 'error', 'message' => 'Адрес не найден'];
        }
        return $result;
    }

    /**
     * @return array|string[]
     */
    public function addAddressAction()
    {
        global $USER;
        $arData = Application::getInstance()->getContext()->getRequest()->getPostList()->toArray();
        $arErrors = $this->validateData($arData);
        if ($arErrors) {
            return ['status' => 'error', 'fields' => $arErrors, 'message' => 'Не заполнены обязательные поля'];
        }
        $el = new CIBlockElement;
        $userId = $USER->GetId();
        $arCities = $this->getCities();
        $arLoadProductArray = array(
            "MODIFIED_BY" => $userId, // элемент изменен текущим пользователем
            "IBLOCK_SECTION_ID" => false,          // элемент лежит в корне раздела
            "IBLOCK_ID" => $this->iBlockId,
            "PREVIEW_TEXT"=>$arData['preview_text'],
            "PROPERTY_VALUES" => [
                'CITY'=>$arData['city']?:$arData['modal-city'],
                'STREET'=>$arData['street'],
                'HOUSE'=>$arData['house'],
                'CORPS'=>$arData['corps'],
                'FLAT'=>$arData['flat'],
                'ENTRANCE'=>$arData['entrance'],
                'FLOOR'=>$arData['floor'],
                'USER'=>$userId,
            ],
            "NAME" => sprintf('г. %s, %s, %s',$arCities[$arData['city']]['NAME']?:$arCities[$arData['modal-city']]['NAME'],$arData['street'],$arData['house']),
            "ACTIVE" => "Y",            // активен
        );
        if(isset($arData['id'])) {
            if($arData['id']) {
                if ($el->Update($arData['id'],$arLoadProductArray)) {
                    $result = ['status' => 'success'];
                } else {
                    $result = ['status' => 'error','message'=>$el->LAST_ERROR];
                }
            } else {
                $result = ['status' => 'error','message'=>'Адрес не найден'];
            }

        } else {
            if ($el->Add($arLoadProductArray)) {
                $result = ['status' => 'success'];
            } else {
                $result = ['status' => 'error','message'=>$el->LAST_ERROR];
            }
        }

        return $result;
    }

    public function executeComponent()
    {
        $this->getPage();
        $this->includeComponentTemplate();
    }

    /**
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    private function getPage()
    {
        global $USER;
        if (!$USER->IsAuthorized()) {
            LocalRedirect('/');
        }
        $this->arResult['CITIES'] = $this->getCities();

    }

    /**
     * @param $arData
     * @return array
     */
    private function validateData($arData)
    {
        $arErrors = [];
        if ($this->arRequiredFields) {
            foreach ($this->arRequiredFields as $code) {
                if (!$arData[$code] && !$arData[sprintf('modal-%s',$code)]) {
                    $arErrors[] = $code;
                }
            }
        }
        return $arErrors;
    }

    /**
     * @param $id
     * @param array $select
     * @return \Bitrix\Main\ORM\Objectify\EntityObject|null
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    private function getAddressItem($id, $select = [])
    {
        $defaultSelect = ['ID', 'USER'];
        global $USER;
        $addressClass = $this->getAddressClass();

        return $addressClass::getByPrimary($id, [
            'select' => array_merge($defaultSelect, $select),
            'filter' => ['IBLOCK_ELEMENTS_ELEMENT_USER_ADDRESSES_USER_VALUE' => $USER->GetId()]
        ])->fetchObject();
    }

    /**
     * @return \Bitrix\Iblock\ORM\CommonElementTable|string
     */
    private function getAddressClass()
    {
        return \Bitrix\Iblock\Iblock::wakeUp($this->iBlockId)->getEntityDataClass();
    }

    private function getCities()
    {
        global $USER;
        $arCities = [];
        $addressClass = $this->getAddressClass();
        $this->arResult['ITEMS'] = $addressClass::getList([
            'select' => ['ID', 'NAME', 'USER'],
            'filter' => ['IBLOCK_ELEMENTS_ELEMENT_USER_ADDRESSES_USER_VALUE' => $USER->GetId()],
            'order' => ['ID' => 'desc']
        ])->fetchCollection();

        $db_vars = CSaleOrderPropsVariant::GetList(
            array("SORT" => "ASC"),
            array("ORDER_PROPS_ID" => 5)
        );
        while ($arItem = $db_vars->Fetch())
        {
            $arCities[$arItem['ID']] = $arItem;
        }
        return $arCities;
    }

}