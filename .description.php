<?php
use Bitrix\Main\Loader,
    Bitrix\Main\Localization\Loc,
    Bitrix\Sale\PaySystem;

Loc::loadMessages(__FILE__);

$isAvailable = PaySystem\Manager::HANDLER_AVAILABLE_TRUE;

$licensePrefix = Loader::includeModule('bitrix24') ? \CBitrix24::getLicensePrefix() : '';
$portalZone = Loader::includeModule('intranet') ? CIntranetUtils::getPortalZone() : '';

if (Loader::includeModule('bitrix24'))
{
    if ($licensePrefix !== 'kz')
    {
        $isAvailable = PaySystem\Manager::HANDLER_AVAILABLE_FALSE;
    }
}
elseif (Loader::includeModule('intranet') && $portalZone !== 'kz')
{
    $isAvailable = PaySystem\Manager::HANDLER_AVAILABLE_FALSE;
}

$data = array(
    'NAME' => Loc::getMessage('SALE_HPS_EPAYMENT'),
    'SORT' => 500,
    'IS_AVAILABLE' => $isAvailable,
    'CODES' => array(
        "PS_CHANGE_STATUS_PAY" => array(
            "NAME" => Loc::getMessage("SALE_HPS_EPAYMENT_CHANGE_STATUS_PAY"),
            'SORT' => 100,
            'GROUP' => Loc::getMessage("GENERAL_SETTINGS"),
            "INPUT" => array(
                'TYPE' => 'Y/N'
            ),
            'DEFAULT' => array(
                "PROVIDER_KEY" => "INPUT",
                "PROVIDER_VALUE" => "Y",
            )
        ),
        "PS_IS_TEST" => array(
            "NAME" => Loc::getMessage("SALE_HPS_EPAYMENT_IS_TEST"),
            'SORT' => 200,
            'GROUP' => Loc::getMessage("GENERAL_SETTINGS"),
            "INPUT" => array(
                'TYPE' => 'Y/N'
            )
        ),
        "EPAYMENT_CLIENT_ID" => array(
            "NAME" => Loc::getMessage("SALE_HPS_EPAYMENT_CLIENT_ID"),
            "DESCRIPTION" => Loc::getMessage("SALE_HPS_EPAYMENT_CLIENT_ID_DESC"),
            'SORT' => 300,
            'GROUP' => Loc::getMessage("EPAYMENT_CONNECT_SETTINGS")
        ),
        "EPAYMENT_CLIENT_SECRET" => array(
            "NAME" => Loc::getMessage("SALE_HPS_EPAYMENT_CLIENT_SECRET"),
            "DESCRIPTION" => Loc::getMessage("SALE_HPS_EPAYMENT_CLIENT_SECRET_DESC"),
            'SORT' => 400,
            'GROUP' => Loc::getMessage("EPAYMENT_CONNECT_SETTINGS")
        ),
        "EPAYMENT_TERMINAL_ID" => array(
            "NAME" => Loc::getMessage("SALE_HPS_EPAYMENT_TERMINAL_ID"),
            "DESCRIPTION" => Loc::getMessage("SALE_HPS_EPAYMENT_TERMINAL_ID_DESC"),
            'SORT' => 500,
            'GROUP' => Loc::getMessage("EPAYMENT_CONNECT_SETTINGS")
        ),
    )
);
