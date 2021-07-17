<?php


namespace Tookantech\Multipay\Tests\Drivers;

use Tookantech\Multipay\Abstracts\Driver;
use Tookantech\Multipay\Contracts\ReceiptInterface;
use Tookantech\Multipay\Invoice;
use Tookantech\Multipay\Receipt;
use Tookantech\Multipay\RedirectionForm;

class BarDriver extends Driver
{
    public const DRIVER_NAME = 'bar';
    public const TRANSACTION_ID = 'random_transaction_id';
    public const REFERENCE_ID = 'random_reference_id';

    protected $invoice;

    protected $settings;

    public function __construct(Invoice $invoice, $settings)
    {
        $this->invoice($invoice);
        $this->settings=$settings;
    }

    public function purchase()
    {
        return static::TRANSACTION_ID;
    }

    public function pay(): RedirectionForm
    {
        return $this->redirectWithForm('/', [
            'amount' => $this->invoice->getAmount()
        ], 'GET');
    }

    public function verify(): ReceiptInterface
    {
        return new Receipt(static::DRIVER_NAME, static::REFERENCE_ID);
    }
}
