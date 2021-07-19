<?php

namespace Tookantech\Multipay\Drivers\Walleta;

use GuzzleHttp\Client;
use Tookantech\Multipay\Abstracts\Driver;
use Tookantech\Multipay\Exceptions\PurchaseFailedException;
use Tookantech\Multipay\Contracts\ReceiptInterface;
use Tookantech\Multipay\Invoice;
use Tookantech\Multipay\Receipt;
use Tookantech\Multipay\RedirectionForm;

class Walleta extends Driver
{
    /**
     * Invoice
     *
     * @var Invoice
     */
    protected $invoice;

    /**
     * Response
     *
     * @var object
     */
    protected $response;

    /**
     * Driver settings
     *
     * @var object
     */
    protected $settings;

    /**
     * Walleta constructor.
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
     * Purchase Invoice.
     *
     * @return string
     *
     * @throws PurchaseFailedException
     */
    public function purchase()
    {
        $result = $this->token();

        if (!isset($result['status_code']) or $result['status_code'] != 200) {
            $this->purchaseFailed($result['status_code']);
        }

        $this->invoice->transactionId($result['content']);

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
        $data = [
            'RefID' => $this->invoice->getTransactionId()
        ];

        //set mobileap for get user cards
        if (!empty($this->invoice->getDetails()['mobile'])) {
            $data['mobileap'] = $this->invoice->getDetails()['mobile'];
        }

        return $this->redirectWithForm($this->settings->apiPaymentUrl, $data, 'POST');
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
        $result = $this->transactionResult();

        if (!isset($result['status_code']) or $result['status_code'] != 200) {
            $this->purchaseFailed($result['status_code']);
        }

        $this->payGateTransactionId = $result['content']['payGateTranID'];

        //step1: verify
        $verify_result = $this->verifyTransaction();

        if (!isset($verify_result['status_code']) or $verify_result['status_code'] != 200) {
            $this->purchaseFailed($verify_result['status_code']);
        }

        //step2: settlement
        $this->settlement();

        $receipt = $this->createReceipt($this->payGateTransactionId);
        $receipt->detail([
            'traceNo' => $this->payGateTransactionId,
            'referenceNo' => $result['content']['rrn'],
            'transactionId' => $result['content']['refID'],
            'cardNo' => $result['content']['cardNumber'],
        ]);

        return $receipt;
    }

    /**
     * send request to Walleta
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
        $receipt = new Receipt('walleta', $referenceId);

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
            'merchant_code' => $this->settings->merchantId,
            'invoice_reference' => $this->invoice->getUuid(),
            'invoice_date' => now()->toDateTimeLocalString(),###
            'invoice_amount' => $this->invoice->getAmount(),
            'payer_first_name' => $this->invoice->getDetails()['first_name'],
            'payer_last_name' => $this->invoice->getDetails()['last_name'],
            'payer_national_code' => $this->invoice->getDetails()['national_code'],
            'payer_mobile' => $$this->invoice->getDetails()['cellphone'],
            'callback_url' => $this->settings->callbackUrl,
            'items' => $this->invoice->getDetails()['items'],
        ]);
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

        ];

        if (array_key_exists($status, $translations)) {
            throw new PurchaseFailedException($translations[$status]);
        } else {
            throw new PurchaseFailedException('خطای ناشناخته ای رخ داده است.');
        }
    }
}
