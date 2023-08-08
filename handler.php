<?php

namespace Sale\Handlers\PaySystem;

use Bitrix\Main;
use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Request;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Context;
use Bitrix\Sale\BusinessValue;
use Bitrix\Sale\PaySystem;
use Bitrix\Sale\Payment;
use Bitrix\Sale\PriceMaths;


Loc::loadMessages(__FILE__);

/**
 * Class EpaymentHandler
 * @package Sale\Handlers\PaySystem
 */
class EpaymentHandler extends PaySystem\ServiceHandler
{
    private const PAYMENT_STATUS_SUCCEEDED = 'ok';
    private const PAYMENT_STATUS_CANCELED = 'error';

    private const SEND_METHOD_HTTP_POST = "POST";
    private const SEND_METHOD_HTTP_GET = "GET";

    /**
     * @param Payment $payment
     * @param Request|null $request
     * @return PaySystem\ServiceResult
     */
    public function initiatePay(Payment $payment, Request $request = null)
    {
        $result = new PaySystem\ServiceResult();

        $paramsResult = $this->prepareParams($payment);
        if ($paramsResult->isSuccess())
        {
            $params = $paramsResult->getData();
            $this->setExtraParams($params);

            $showTemplateResult = $this->showTemplate($payment, "template");
            if ($showTemplateResult->isSuccess())
            {
                $result->setTemplate($showTemplateResult->getTemplate());
            }
            else
            {
                $result->addErrors($showTemplateResult->getErrors());
            }
        }
        else
        {
            $result->addErrors($paramsResult->getErrors());
        }

        return $result;
    }


    /**
     * @param Payment $payment
     * @return PaySystem\ServiceResult
     */
    private function prepareParams(Payment $payment)
    {
        $result = new PaySystem\ServiceResult();

        $authorizationTokenResult = $this->requestAuthorizationToken($payment);
        if (!$authorizationTokenResult->isSuccess())
        {
            $result->addErrors($authorizationTokenResult->getErrors());
            return $result;
        }

        $authorizationTokenData = $authorizationTokenResult->getData();

        $host = (Context::getCurrent()->getRequest()->isHttps()) ? "https://" : "http://";
        $host .= $_SERVER["HTTP_HOST"];

        /** @var PaymentCollection $collection */
        $collection = $payment->getCollection();
        $order = $collection->getOrder();

        $invoiceId = $order->getId();
        $invoiceId = str_pad($invoiceId, 8, 0, STR_PAD_LEFT);

        $url = $this->getUrl($payment, 'pay');
        $backLink = $host;
        $failureBackLink = $host;
        $postLink = $host . '/bitrix/tools/sale_ps_result.php';
        $failurePostLink = '';
        $language = Context::getCurrent()->getLanguage();
        $description = 'Payment in the online store.';
        $sum = PriceMaths::roundPrecision($payment->getSum());
        $currency = $payment->getField("CURRENCY");
        $data = [
            'BX_PAYMENT_NUMBER' => $payment->getId(),
            'BX_PAYSYSTEM_CODE' => $this->service->getField('ID'),
            'BX_HANDLER' => 'EPAYMENT',
        ];

        $params = [
            "URL" => $url,
            "ORDER_ID" => $invoiceId,
            "BACK_LINK" => $backLink,
            "FAILURE_BACK_LINK" => $failureBackLink,
            "POST_LINK" => $postLink,
            "FAILURE_POST_LINK" => $failurePostLink,
            "LANG" => $language,
            "DESCRIPTION" => $description,
            "SUM" => $sum,
            "CURRENCY" => $currency,
            "AUTH" => $authorizationTokenData,
            "DATA" => $data
        ];

        if ($userEmail = $order->getPropertyCollection()->getUserEmail())
        {
            $params['EMAIL'] = $userEmail->getValue();
        }

        if ($userPhone = $order->getPropertyCollection()->getPhone())
        {
            $params['PHONE'] = $userPhone->getValue();
        }

        $result->setData($params);
        return $result;
    }


    /**
     * @param Payment $payment
     * @return PaySystem\ServiceResult
     */
    private function requestAuthorizationToken(Payment $payment)
    {
        $result = new PaySystem\ServiceResult();

        /** @var PaymentCollection $collection */
        $collection = $payment->getCollection();
        $order = $collection->getOrder();

        $url = $this->getUrl($payment, 'oauth2');
        $invoiceId = $order->getId();
        $invoiceId = str_pad($invoiceId, 8, 0, STR_PAD_LEFT);
        $clientId = $this->getBusinessValue($payment, 'EPAYMENT_CLIENT_ID');
        $clientSecret = $this->getBusinessValue($payment, 'EPAYMENT_CLIENT_SECRET');
        $amount = (int) PriceMaths::roundPrecision($payment->getSum());
        $currency = $payment->getField("CURRENCY");
        $terminal = $this->getBusinessValue($payment, 'EPAYMENT_TERMINAL_ID');

        $params = [
            'grant_type' => 'client_credentials',
            'scope' => 'webapi usermanagement email_send verification statement statistics payment',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'invoiceID' => $invoiceId,
            'amount' => $amount,
            'currency' => $currency,
            'terminal' => $terminal,
            'postLink' => '',
            'failurePostLink' => ''
        ];

        $sendResult = $this->sendRequest(self::SEND_METHOD_HTTP_POST, $url, $params);
        if (!$sendResult->isSuccess())
        {
            $result->addErrors($sendResult->getErrors());
            return $result;
        }

        $response = $sendResult->getData();
        $result->setData($response);

        return $result;
    }


    /**
     * @param Payment $payment
     * @param Request $request
     * @return PaySystem\ServiceResult
     * @throws Main\ArgumentException
     * @throws Main\ArgumentNullException
     * @throws Main\ArgumentOutOfRangeException
     * @throws Main\ArgumentTypeException
     * @throws Main\ObjectException
     */
    public function processRequest(Payment $payment, Request $request)
    {
        $result = new PaySystem\ServiceResult();

        $inputStream = self::readFromStream();
        $response = self::decode($inputStream);
        parse_str($response['data'], $data);

        if ($response !== false)
        {
            if ($response["code"] === self::PAYMENT_STATUS_SUCCEEDED)
            {
                $description = Loc::getMessage("SALE_HPS_EPAYMENT_TRANSACTION", [
                    "#ID#" => $response["id"],
                    "#PAYMENT_NUMBER#" => $data["BX_PAYMENT_NUMBER"]
                ]);

                $fields = array(
                    "PS_INVOICE_ID" => $response['invoiceId'],
                    "PS_STATUS_CODE" => $response["reasonCode"],
                    "PS_STATUS_CODE" => 2,
                    "PS_STATUS_DESCRIPTION" => $description,
                    "PS_SUM" => $response["amount"],
                    "PS_STATUS" => "N",
                    'PS_CURRENCY' => $response['currency'],
                    "PS_RESPONSE_DATE" => new Main\Type\DateTime()
                );

                if ($this->isSumCorrect($payment, $response["amount"]))
                {
                    $fields["PS_STATUS"] = "Y";

                    PaySystem\Logger::addDebugInfo(
                        __CLASS__ . ": PS_CHANGE_STATUS_PAY=" . $this->getBusinessValue($payment, "PS_CHANGE_STATUS_PAY")
                    );

                    if ($this->getBusinessValue($payment, "PS_CHANGE_STATUS_PAY") === "Y")
                    {
                        $result->setOperationType(PaySystem\ServiceResult::MONEY_COMING);
                    }
                }
                else
                {
                    $error = Loc::getMessage("SALE_HPS_EPAYMENT_ERROR_SUM");
                    $fields["PS_STATUS_DESCRIPTION"] .= " " . $error;
                    $result->addError(new Main\Error($error));
                }

                $result->setPsData($fields);
            }
        }
        else
        {
            $result->addError(PaySystem\Error::create(Localization\Loc::getMessage('SALE_HPS_EPAYMENT_CHECKOUT_ERROR_QUERY')));
        }

        if (!$result->isSuccess())
        {
            $error = __CLASS__ . ": processRequest: " . join("\n", $result->getErrorMessages());
            PaySystem\Logger::addError($error);
        }

        return $result;
    }


    /**
     * @param $method
     * @param $url
     * @param array $params
     * @param array $headers
     * @return PaySystem\ServiceResult
     * @throws Main\ArgumentException
     * @throws Main\ArgumentNullException
     * @throws Main\ArgumentOutOfRangeException
     * @throws Main\ArgumentTypeException
     * @throws Main\ObjectException
     */
    private function sendRequest($method, $url, array $params = array(), array $headers = array()): PaySystem\ServiceResult
    {
        $result = new PaySystem\ServiceResult();

        $httpClient = new HttpClient();

        foreach ($headers as $name => $value)
        {
            $httpClient->setHeader($name, $value);
        }

        if ($method === self::SEND_METHOD_HTTP_GET)
        {
            $response = $httpClient->get($url);
        }
        else
        {
            PaySystem\Logger::addDebugInfo(__CLASS__.': request data: ' . static::encode($params));

            $response = $httpClient->post($url, $params);
        }

        if ($response === false)
        {
            $errors = $httpClient->getError();
            if ($errors)
            {
                $errorMessages = [];
                foreach ($errors as $code => $message)
                {
                    $errorMessages[] = "{$code}={$message}";
                }

                PaySystem\Logger::addDebugInfo(
                    __CLASS__ . ': response error: ' . implode(', ', $errorMessages)
                );
            }

            $result->addError(new Main\Error(Localization\Loc::getMessage('SALE_HPS_EPAYMENT_CHECKOUT_ERROR_QUERY')));
            return $result;
        }

        PaySystem\Logger::addDebugInfo(__CLASS__.': response data: '.$response);

        $httpStatus = $httpClient->getStatus();
        if ($httpStatus !== 200)
        {
            $result->addErrors(
                [
                    new Error(
                        Loc::getMessage("SALE_HPS_EPAYMENT_ERROR_HTTP_STATUS", [
                            "#STATUS_CODE#" => $httpStatus
                        ])
                    ),
                    new Error(self::encode($response))
                ]
            );

            return $result;
        }

        $response = self::decode($response);
        if ($response)
        {
            $result->setData($response);
        }

        return $result;
    }


    /**
     * @param Payment $payment
     * @param $amount
     * @return bool
     * @throws Main\ArgumentNullException
     * @throws Main\ArgumentOutOfRangeException
     * @throws Main\ArgumentTypeException
     * @throws Main\ObjectException
     */
    private function isSumCorrect(Payment $payment, $amount)
    {
        PaySystem\Logger::addDebugInfo(
            __CLASS__.": sum=".PriceMaths::roundPrecision($amount)."; paymentSum=".PriceMaths::roundPrecision($payment->getSum())
        );

        return PriceMaths::roundPrecision($amount) === PriceMaths::roundPrecision($payment->getSum());
    }


    /**
     * @param Request $request
     * @param int $paySystemId
     * @return bool
     */
    public static function isMyResponse(Request $request, $paySystemId)
    {
        $inputStream = self::readFromStream();

        if ($inputStream)
        {
            $response = static::decode($inputStream);

            if ($response === false)
            {
                return false;
            }

            parse_str($response['data'], $data);

            if (isset($data['BX_PAYSYSTEM_CODE'])
                && (int) $data['BX_PAYSYSTEM_CODE'] === (int) $paySystemId
            ) {
                return true;
            }
        }

        return false;
    }


    /**
     * @return bool|string
     */
    private static function readFromStream()
    {
        return file_get_contents("php://input");
    }


    /**
     * @param Request $request
     * @return mixed
     */
    public function getPaymentIdFromRequest(Request $request)
    {
        $inputStream = self::readFromStream();

        if ($inputStream)
        {
            $response = static::decode($inputStream);

            if ($response === false)
            {
                return false;
            }

            parse_str($response['data'], $data);
            return $data['BX_PAYMENT_NUMBER'];
        }

        return false;
    }


    /**
     * @return mixed
     */
    protected function getUrlList()
    {
        return array(
            'oauth2' => array(
                self::TEST_URL => 'https://testoauth.homebank.kz/epay2/oauth2/token',
                self::ACTIVE_URL => 'https://epay-oauth.homebank.kz/oauth2/token'
            ),
            'pay' => array(
                self::TEST_URL => 'https://test-epay.homebank.kz/payform/payment-api.js',
                self::ACTIVE_URL => 'https://epay.homebank.kz/payform/payment-api.js'
            ),
            'confirm' => array(
                self::ACTIVE_URL => 'https://testepay.homebank.kz/api/operation/:id/charge',
                self::TEST_URL => 'https://epay-api.homebank.kz/operation/:id/charge'
            ),
        );
    }


    /**
     * @param Payment $payment
     * @return bool
     */
    protected function isTestMode(Payment $payment = null)
    {
        return ($this->getBusinessValue($payment, 'PS_IS_TEST') == 'Y');
    }


    /**
     * @return array
     */
    public function getCurrencyList()
    {
        return array('KZT');
    }


    /**
     * @param array $data
     * @return mixed
     * @throws Main\ArgumentException
     */
    private static function encode(array $data)
    {
        return Main\Web\Json::encode($data, JSON_UNESCAPED_UNICODE);
    }


    /**
     * @param string $data
     * @return mixed
     */
    private static function decode($data)
    {
        try {
            return Main\Web\Json::decode($data);
        }
        catch (Main\ArgumentException $exception) {
            return false;
        }
    }
}