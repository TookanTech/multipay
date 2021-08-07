<?php

namespace Tookantech\Multipay\Drivers\Etebarino;

use GuzzleHttp\Client;
use Tookantech\Multipay\Abstracts\Driver;
use Tookantech\Multipay\Exceptions\PurchaseFailedException;
use Tookantech\Multipay\Contracts\ReceiptInterface;
use Tookantech\Multipay\Invoice;
use Tookantech\Multipay\Receipt;
use Tookantech\Multipay\RedirectionForm;

class Etebarino extends Driver
{
    /**
     * Invoice
     *
     * @var Invoice
     */
    protected $invoice;

    /**
     * Driver settings
     *
     * @var object
     */
    protected $settings;

    /**
     * Etebarino constructor.
     * Construct the class with the relevant settings.
     *
     * @param Invoice $invoice
     * @param $settings
     */
    public function __construct(Invoice $invoice, $settings)
    {
        $this->invoice($invoice);
        $this->settings = (object)$settings;
    }

    /**
     * Purchase Invoice
     *
     * @return string
     *
     * @throws PurchaseFailedException
     */
    public function purchase()
    {

        $result = $this->token();

        if (!isset($result['status_code']) or $result['status_code'] != 200) {
            $this->purchaseFailed($result['content']);
        }

        $this->invoice->transactionId($result['content']['token']);

        // return the transaction's id
        return $this->invoice->getTransactionId();
    }

    /**
     * Pay the Invoice
     *
     * @return RedirectionForm
     */
    public function pay(): RedirectionForm
    {
        return $this->redirectWithForm($this->settings->apiPaymentUrl, [
            'token' => $this->invoice->getTransactionId()
        ], 'GET');
    }

    /**
     * Verify payment
     *
     * @return mixed|Receipt
     *
     * @throws PurchaseFailedException
     */
    public function verify(): ReceiptInterface
    {
        $result = $this->verifyTransaction();

        if (!isset($result['status_code']) or $result['status_code'] != 200) {
            $this->purchaseFailed($result['content']);
        }

        $receipt = $this->createReceipt($this->invoice->getTransactionId());

        return $receipt;
    }

    /**
     * send request to etebarino
     *
     * @param $method
     * @param $url
     * @param array $data
     * @return array
     */
    protected function callApi($method, $url, $data = []): array
    {
        $client = new Client();

        $response = $client->request($method, $url, [
            "json" => $data,
            "headers" => [
                'Content-Type' => 'application/json',
            ],
            "http_errors" => false,
        ]);

        return [
            'status_code' => $response->getStatusCode(),
            'content' => json_decode($response->getBody()->getContents(), true)
        ];
    }

    /**
     * Generate the payment's receipt
     *
     * @param $referenceId
     *
     * @return Receipt
     */
    protected function createReceipt($referenceId): Receipt
    {
        $receipt = new Receipt('etebarino', $referenceId);

        return $receipt;
    }

    /**
     * call create token request
     *
     * @return array
     */
    public function token(): array
    {
        return $this->callApi('POST', $this->settings->apiPurchaseUrl, [
            'terminalCode' => (int)$this->settings->terminalId,
            'merchantCode' => (int)$this->settings->merchantId,
            'termidalUser' => (int)$this->settings->username,
            'terminalPass' => $this->settings->password,
            'merchantRefCode' => 123465987,
            'description' => $this->invoice->getUuid(),
            'returnUrl' => $this->settings->callbackUrl,
            'paymentItems' => [
                [
                    'productGroup'=>1001,
                    'amount'=>25000,
                    'description' =>'desc'
                ]
            ],
        ]);
    }

    /**
     * call verift transaction request
     *
     * @return array
     */
    public function verifyTransaction(): array
    {
        return $this->callApi('POST', $this->settings->apiVerificationUrl, [
            'terminalCode' => $this->settings->terminalId,
            'merchantCode' => $this->settings->merchantId,
            'termidalUser' => $this->settings->username,
            'terminalPass' => $this->settings->password,
            'merchantRefCode' => $this->invoice->getUuid(),
        ]);
    }

    /**
     * get Items for
     *
     *
     */
    private function getItems()
    {
        /**
         * example data
         *
         *   $items = [
         *       [
         *           "productGroup" => 1000,
         *           "amount" => 1000,
         *           "description" => "desc"
         *       ]
         *   ];
         */
        return $this->invoice->getDetails()['items'];
    }


    /**
     * Trigger an exception
     *
     * @param $status
     *
     * @throws PurchaseFailedException
     */
    protected function purchaseFailed($status)
    {
        $translations = [
            "ACCESS_DENIED" => "اطلاعات ارسال‌شده درست نمی‌باشد.",
            "invalid_payment_item" => "گروه‌های کالایی صحیح نمی‌باشد.",
            "BAD_INPUT" => "پارامترهای نوع داده‌ها یا ساختار گروه‌های کالایی یا کدیکتای سفارش صحیح نمی‌باشد.",
            "merchant_not_found" => "کد فروشنده صحیح نمی‌باشد."
        ];

        if (array_key_exists($status, $translations)) {
            throw new PurchaseFailedException($translations[$status]);
        } else {
            throw new PurchaseFailedException('خطای ناشناخته ای رخ داده است.');
        }
    }
}
