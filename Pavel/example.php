<?php

class CompareController extends BaseController
{

    public function addToCompare(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $arParams = $request->getQueryParams();
        if (! $arParams['cityId']) {
            throw new LogicException([CatalogProductErrorsEnum::EMPTY_CITY_ID]);
        }

        $arIds = json_decode($request->getParsedBody()['list'], true);
        if (! $arIds) {
            throw new LogicException([CatalogProductErrorsEnum::EMPTY_PRODUCT_ID]);
        }
        $fUserID = $request->getAttribute(self::F_USER_ATTRIBUTE_NAME);

        $service = new CompareService();

        $result = $service->addToCompare($arParams['cityId'], $arIds, $fUserID);

        return ResponseBuilder::successJSON($response, $result);
    }

    public function getCompare(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $arParams = $request->getQueryParams();
        if (! $arParams['cityId']) {
            throw new LogicException([CatalogProductErrorsEnum::EMPTY_CITY_ID]);
        }
        $fUserID = $request->getAttribute(self::F_USER_ATTRIBUTE_NAME);
        $service = new CompareService();

        $result = $service->getCompare($arParams['cityId'], $fUserID);

        return ResponseBuilder::successJSON($response, $result);
    }

    public function deleteCompareItem(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $arParams = $request->getQueryParams();
        $cityId   = intval($arParams['cityId']);
        $id       = intval($arParams['id']);
        if (! $cityId) {
            throw new LogicException([CatalogProductErrorsEnum::EMPTY_CITY_ID]);
        }
        if (! $id) {
            throw new LogicException([CatalogProductErrorsEnum::EMPTY_PRODUCT_ID]);
        }
        $fUserID = $request->getAttribute(self::F_USER_ATTRIBUTE_NAME);
        $service = new CompareService();

        $result = $service->deleteCompareItem($id, $cityId, $fUserID);
        if (ResponseErrorsService::hasErrors()) {
            return ResponseBuilder::errorJSON($response);
        }
        return ResponseBuilder::successJSON($response, $result);
    }
}

class CompareService
{
    const CITY_IBLOCK_CODE = 'cities';
    private $cityIblockId;

    public function __construct()
    {
        $this->cityIblockId = IblockHelper::getIblockId(self::CITY_IBLOCK_CODE);
    }

    public function checkedProductInCompare(int $id): bool
    {
        $itemList = unserialize($_COOKIE['CATALOG_COMPARE_LIST'])[SwapCityTools::getCurrentCityCatalogId()]['ITEMS'];
        if($itemList && $itemList[$id]) {
            return true;
        }
        return false;
    }

    public function addToCompare($cityId, $arIds, $fUserId)
    {
        $arCity = SwapCityTools::getCityById($cityId);
        if ($arCity) {
            $isError = true;
            if ($arCity['PROPERTIES']['IBLOCK_catalog']['VALUE']) {
                $arCompareProducts = $this->getCompareProducts($fUserId);
                if ($arCompareProducts) {
                    $arCompareIds = array_column($arCompareProducts, 'PRODUCT_ID');
                    $arIds = array_diff($arIds, $arCompareIds);
                }
                if($arIds){
                    $arSelect = ["ID"];
                    $arFilter = ['IBLOCK_ID' => $arCity['PROPERTIES']['IBLOCK_catalog']['VALUE'], 'ID' => $arIds];
                    $res      = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
                    while ($arItem = $res->Fetch()) {
                        $isError = false;
                        ProductCompareTable::add(
                            [
                                'F_USER_ID'  => $fUserId,
                                'PRODUCT_ID' => $arItem['ID'],
                            ]
                        );
                    }
                }

            }
            if ($isError) {
                throw new LogicException(CatalogProductErrorsEnum::PRODUCT_NOT_FOUND);
            }
        } else {
            throw new LogicException(CatalogProductErrorsEnum::INCORRECT_CITY);
        }

        return ['result' => 'ok'];
    }

    public function getCompare($cityId, $fUserId)
    {
        $arItems = [];
        $arCity = SwapCityTools::getCityById($cityId);
        if ($arCity && $arCity['PROPERTIES']['IBLOCK_catalog']['VALUE']) {
            $arCompareItems = $this->getCompareProducts($fUserId);
            if ($arCompareItems) {
                $arItems = ProductService::getCompareProducts(array_column($arCompareItems, 'PRODUCT_ID'),$arCity);
            }
        } else {
            throw new LogicException(CatalogProductErrorsEnum::INCORRECT_CITY);
        }
        return [
            'list' => array_values($arItems),
        ];
    }

    public function deleteCompareItem($id, $cityId, $fUserId)
    {
        $arCity = SwapCityTools::getCityById($cityId);
        if (! $arCity) {
            throw new LogicException(CatalogProductErrorsEnum::INCORRECT_CITY);
        }
        $dsProducts = ProductCompareTable::getList(
            [
                'select'      => ['ID', 'PRODUCT_ID', 'F_USER_ID'],
                'filter'      => ['F_USER_ID' => $fUserId, 'PRODUCT_ID' => $id],
                'count_total' => true,
            ]
        );
        if ($dsProducts->getCount()) {
            ProductCompareTable::delete($dsProducts->fetchObject()->getId());
        } else {
            throw new LogicException(CatalogProductErrorsEnum::INCORRECT_COMPARE_PRODUCT_ID);
        }

        return [
            'result' => 'ok',
        ];
    }

    private function getCompareProducts($fUserId)
    {
        return ProductCompareTable::getList(
            [
                'select' => ['ID', 'PRODUCT_ID', 'F_USER_ID'],
                'filter' => ['F_USER_ID' => $fUserId],
            ]
        )->fetchAll();
    }
}


?>