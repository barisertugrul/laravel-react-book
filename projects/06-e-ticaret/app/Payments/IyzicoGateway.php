<?php

namespace App\Payments;

use App\Models\Order;
use App\Support\Money;
use Iyzipay\Model\Address;
use Iyzipay\Model\BasketItem;
use Iyzipay\Model\BasketItemType;
use Iyzipay\Model\Buyer;
use Iyzipay\Model\CheckoutForm;
use Iyzipay\Model\CheckoutFormInitialize;
use Iyzipay\Model\Currency;
use Iyzipay\Model\Locale;
use Iyzipay\Model\PaymentGroup;
use Iyzipay\Options;
use Iyzipay\Request\CreateCheckoutFormInitializeRequest;
use Iyzipay\Request\RetrieveCheckoutFormRequest;
use RuntimeException;

/**
 * iyzico Checkout Form: müşteri iyzico'nun ödeme sayfasında kart bilgisini girer,
 * kart bilgisi sunucumuza hiç gelmez.
 *
 * Akış: start() → iyzico ödeme sayfası → iyzico müşteriyi callbackUrl'e POST eder (token ile)
 *       → retrieve() ile sonucu SUNUCUDAN SUNUCUYA sorarız (bkz. IyzicoCallbackController).
 */
class IyzicoGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'iyzico';
    }

    public function start(Order $order): string
    {
        $order->loadMissing(['items', 'user']);

        $request = new CreateCheckoutFormInitializeRequest;
        $request->setLocale(Locale::TR);
        $request->setConversationId($order->number);
        $request->setBasketId($order->number);
        $request->setPrice(Money::toDecimal($order->total));     // sepet kalemlerinin toplamı
        $request->setPaidPrice(Money::toDecimal($order->total)); // müşteriden çekilecek tutar
        $request->setCurrency(Currency::TL);
        $request->setPaymentGroup(PaymentGroup::PRODUCT);
        $request->setCallbackUrl(route('iyzico.callback'));
        $request->setEnabledInstallments([1]);

        [$firstName, $lastName] = $this->splitName($order->shipping_name);

        $buyer = new Buyer;
        $buyer->setId((string) $order->user_id);
        $buyer->setName($firstName);
        $buyer->setSurname($lastName);
        $buyer->setGsmNumber($order->shipping_phone);
        $buyer->setEmail($order->user->email);
        // iyzico bu alanı zorunlu tutar. Gerçek mağazada TC kimlik no toplamanız gerekip
        // gerekmediğini iyzico ile netleştirin; sandbox 11 haneli herhangi bir değeri kabul eder.
        $buyer->setIdentityNumber('11111111111');
        $buyer->setRegistrationAddress($order->shipping_address);
        $buyer->setCity($order->shipping_city);
        $buyer->setCountry('Turkey');
        $buyer->setIp((string) request()->ip());
        $request->setBuyer($buyer);

        $address = new Address;
        $address->setContactName($order->shipping_name);
        $address->setCity($order->shipping_city);
        $address->setCountry('Turkey');
        $address->setAddress($order->shipping_address);
        $request->setShippingAddress($address);
        $request->setBillingAddress($address);

        // Sepet kalemlerinin toplamı price ile kuruşu kuruşuna eşit olmalı; kargo da bir kalem
        $items = $order->items->map(function ($item) {
            $basketItem = new BasketItem;
            $basketItem->setId((string) $item->product_id);
            $basketItem->setName($item->product_name);
            $basketItem->setCategory1('Kahve');
            $basketItem->setItemType(BasketItemType::PHYSICAL);
            $basketItem->setPrice(Money::toDecimal($item->total));

            return $basketItem;
        })->all();

        if ($order->shipping > 0) {
            $shipping = new BasketItem;
            $shipping->setId('kargo');
            $shipping->setName('Kargo');
            $shipping->setCategory1('Kargo');
            $shipping->setItemType(BasketItemType::VIRTUAL);
            $shipping->setPrice(Money::toDecimal($order->shipping));
            $items[] = $shipping;
        }
        $request->setBasketItems($items);

        $form = CheckoutFormInitialize::create($request, $this->options());

        if ($form->getStatus() !== 'success') {
            throw new RuntimeException('iyzico ödeme başlatılamadı: '.$form->getErrorMessage());
        }

        return $form->getPaymentPageUrl();
    }

    /** Ödeme sonucunu token ile iyzico'ya sorar */
    public function retrieve(string $token): CheckoutForm
    {
        $request = new RetrieveCheckoutFormRequest;
        $request->setLocale(Locale::TR);
        $request->setToken($token);

        return CheckoutForm::retrieve($request, $this->options());
    }

    private function options(): Options
    {
        $options = new Options;
        $options->setApiKey((string) config('shop.iyzico.api_key'));
        $options->setSecretKey((string) config('shop.iyzico.secret_key'));
        $options->setBaseUrl((string) config('shop.iyzico.base_url'));

        return $options;
    }

    /** "Ayşe Nur Yılmaz" → ["Ayşe Nur", "Yılmaz"] */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name));
        $last = count($parts) > 1 ? array_pop($parts) : '-';

        return [implode(' ', $parts), $last];
    }
}
