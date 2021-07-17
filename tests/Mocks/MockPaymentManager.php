<?php

namespace Tookantech\Multipay\Tests\Mocks;

use Tookantech\Multipay\Payment;

class MockPaymentManager extends Payment
{
    public function getDriver() : string
    {
        return $this->driver;
    }

    public function getConfig() : array
    {
        return $this->config;
    }

    public function getCallbackUrl() : string
    {
        return $this->settings['callbackUrl'];
    }

    public function getInvoice()
    {
        return $this->invoice;
    }

    public function getCurrentDriverSetting()
    {
        return $this->settings;
    }
}
