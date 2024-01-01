<?php

namespace DCS\AaioPayments\Payment;

use XF;
use XF\Entity\PaymentProfile;
use XF\Entity\PurchaseRequest;
use XF\Http\Request;
use XF\Mvc\Controller;
use XF\Payment\AbstractProvider;
use XF\Payment\CallbackState;
use XF\Purchasable\Purchase;
use GuzzleHttp\Client;


class Aaio extends AbstractProvider
{

    public function getTitle(): string
    {
        return 'Aaio';
    }


    public function verifyConfig(array &$options, &$errors = [])
    {
        if (empty($options['merchant_id']) || empty($options['secret']) || empty($options['api_key'])) {
            $errors[] = XF::phrase("dcs_you_must_enter_all_details");
        }

        return !$errors;
    }


    public function getApiEndpoint(): string
    {
        return 'https://aaio.io/merchant/pay';
    }

    public function initiatePayment(Controller $controller, PurchaseRequest $purchaseRequest, Purchase $purchase): XF\Mvc\Reply\AbstractReply
    {
        $paymentProfile = $purchase->paymentProfile;

        $merchant_id = $paymentProfile->options['merchant_id'];
        $secret = $paymentProfile->options['secret'];

        $orderId = XF::generateRandomString(10);

        $sign = hash('sha256', implode(':', [$merchant_id, $purchaseRequest->cost_amount,
            $purchaseRequest->cost_currency, $secret, $orderId]));

        $params = [
            'merchant_id'    => $merchant_id,
            'amount'         => $purchaseRequest->cost_amount,
            'order_id'       => $orderId,
            'sign'           => $sign,
            'currency'       => $purchaseRequest->cost_currency,
            'desc'           => $purchase->title,
            'us_reqKey'      => $purchaseRequest->request_key,
        ];

        $endpointUrl = $this->getApiEndpoint();
        $endpointUrl .= '?' . http_build_query($params);

        return $controller->redirect($endpointUrl);
    }

    public function setupCallback(Request $request): CallbackState
    {
        $state = new CallbackState();

        $state->_POST = $_POST;

        $state->transactionId = $request->filter('order_id', 'str');
        $state->requestKey = $request->filter('us_reqKey', 'str');

        $state->signature = $request->filter('sign', 'str');
        $state->currency = $request->filter('currency', 'str');
        $state->cost = $request->filter('amount', 'int');

        return $state;
    }

    public function validateCallback(CallbackState $state)
    {
        $purchaseRequest = $state->getPurchaseRequest();

        if (!$state->requestKey || !$purchaseRequest)
        {
            $state->logType = 'error';
            $state->logMessage = 'Notifications does not contain purchase request';

            return false;
        }

        return true;
    }

    public function validateTransaction(CallbackState $state)
    {
        if (!$state->transactionId)
        {
            $state->logType = 'info';
            $state->logMessage = 'No transaction id';

            return false;
        }

        return parent::validateTransaction($state);
    }

    public function validateCost(CallbackState $state)
    {
        $purchaseRequest = $state->getPurchaseRequest();

        $costValidated = (
            $state->cost == $purchaseRequest->cost_amount
            && $state->currency == $purchaseRequest->cost_currency
        );

        if (!$costValidated) {
            $state->logType = 'error';
            $state->logMessage = 'Invalid cost amount';

            return false;
        }

        return parent::validateCost($state);

    }


    public function getPaymentResult(CallbackState $state): void
    {
        $purchaseRequest = $state->getPurchaseRequest();

        if ($state->_POST['status'] == 'success' and $state->requestKey === $purchaseRequest->request_key) {
            $state->paymentResult = CallbackState::PAYMENT_RECEIVED;
        }
    }

    public function prepareLogData(CallbackState $state)
    {
        $state->logDetails = [
            '_GET'  => $_GET,
            '_POST' => $_POST
        ];
    }
}