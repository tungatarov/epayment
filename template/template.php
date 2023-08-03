<?
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Page\Asset;

Loc::loadMessages(__FILE__);

$sum = round($params['SUM'], 2);
$paymentObj = (object) [
    'invoiceId' => htmlspecialcharsbx($params['ORDER_ID']),
    'backLink' => $params['BACK_LINK'],
    'failureBackLink' => $params['FAILURE_BACK_LINK'],
    'postLink' => $params['POST_LINK'],
    'failurePostLink' => $params['FAILURE_POST_LINK'],
    'language' => $params['LANG'],
    'description' => $params['DESCRIPTION'],
    'accountId' => htmlspecialcharsbx($params['PAYMENT_BUYER_ID']),
    'terminal' => htmlspecialcharsbx($params['EPAYMENT_TERMINAL_ID']),
    'amount' => $params['SUM'],
    'currency' => $params['CURRENCY'],
    'auth' => (object) $params['AUTH'],
    'data' => (object) $params['DATA']
];
?>
<div class="mb-5" >
    <p><?= Loc::getMessage('SALE_HANDLERS_PAY_SYSTEM_EPAYMENT_DESCRIPTION') . " " . SaleFormatCurrency($sum, $params['CURRENCY']); ?></p>
    <div class="d-flex align-items-center justify-content-start mb-3">
        <input type="button"
               class="btn btn-lg btn-primary pl-4 pr-4 rounded-pill mr-2"
               value="<?=Loc::getMessage('SALE_HANDLERS_PAY_SYSTEM_EPAYMENT_BUTTON_PAID')?>"
               onclick='halyk.pay(<?= json_encode($paymentObj) ?>)'
        >
        <p class="m-0"><?=Loc::getMessage('SALE_HANDLERS_PAY_SYSTEM_EPAYMENT_REDIRECT');?></p>
    </div>
    <p><?=Loc::getMessage('SALE_HANDLERS_PAY_SYSTEM_EPAYMENT_WARNING_RETURN');?></p>
    <script src="<?= $params['URL'] ?>"></script>
</div>