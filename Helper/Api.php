<?php

namespace AditumPayment\Magento2\Helper;


use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;

class Api
{
    public $enableExternalExtension = true;

    protected $url;
    protected $scopeConfig;
    protected $logger;
    protected $_storeManager;
    protected $checkoutSession;

    /**
     * @var PriceCurrencyInterface
     */
    protected PriceCurrencyInterface $priceCurrency;

    /**
     * @var CartRepositoryInterface
     */
    protected CartRepositoryInterface $quoteRepository;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Checkout\Model\Session $checkoutSession,
        PriceCurrencyInterface $priceCurrency,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->_storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
        $this->priceCurrency = $priceCurrency;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Get cc Brand by ccNumber
     * @param $cc
     * @return string
     */
    public function getCcBrand($cc)
    {
        \AditumPayments\ApiSDK\Configuration::initialize();
        \AditumPayments\ApiSDK\Configuration::setUrl(\AditumPayments\ApiSDK\Configuration::DEV_URL);
        \AditumPayments\ApiSDK\Configuration::setCnpj($this->getClientId());
        \AditumPayments\ApiSDK\Configuration::setMerchantToken($this->getClientSecret());
        \AditumPayments\ApiSDK\Configuration::setlog(false);
        \AditumPayments\ApiSDK\Configuration::login();
        $response = \AditumPayments\ApiSDK\Helper\Utils::getBrandCardBin($cc);
        if (isset($response['status']) && $response["status"] != false) {
            return $response['brand'];
        }
        return "";
    }

    /**
     * @param \Magento\Sales\Model\Order\Interceptor $order
     * @param $payment
     * @return array|null
     */
    public function createOrderBoleto(\Magento\Sales\Model\Order\Interceptor $order, $payment)
    {
        return $this->extCreateOrderBoleto($order, $payment);
    }

    /**
     * @param Order $order
     * @param $payment
     * @return array|null
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws NoSuchEntityException
     */
    public function extCreateOrderBoleto(Order $order, $payment)
    {
        \AditumPayments\ApiSDK\Configuration::initialize();
        \AditumPayments\ApiSDK\Configuration::setUrl($this->getApiUrl());
        \AditumPayments\ApiSDK\Configuration::setCnpj($this->getClientId());
        \AditumPayments\ApiSDK\Configuration::setMerchantToken($this->getClientSecret());
        \AditumPayments\ApiSDK\Configuration::setlog(false);
        \AditumPayments\ApiSDK\Configuration::login();

        $gateway = new \AditumPayments\ApiSDK\Gateway;
        $boleto = new \AditumPayments\ApiSDK\Domains\Boleto;

        $quote = $this->checkoutSession->getQuote();
        $billingAddress = $quote->getBillingAddress();
        $boleto->setMerchantChargeId($order->getIncrementId());

        $boleto->setSessionId($payment->getAdditionalInformation('antifraud_token'));

        $boleto->setDeadline($this->scopeConfig->getValue(
            'payment/aditum_boleto/expiration_days',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        ));

        $boleto->customer->setId($order->getIncrementId());
        $boleto->customer->setName($payment->getAdditionalInformation('boletofullname'));
        $boleto->customer->setEmail($quote->getCustomerEmail());
        $cpfCnpj = $payment->getAdditionalInformation('boletodocument');
        $cpfCnpj = filter_var($cpfCnpj, FILTER_SANITIZE_NUMBER_INT);

        $boleto->customer->setDocumentType(\AditumPayments\ApiSDK\Enum\DocumentType::CPF);
        if (strlen($cpfCnpj) == 14) {
            $boleto->customer->setDocumentType(\AditumPayments\ApiSDK\Enum\DocumentType::CNPJ);
        }
        $boleto->customer->setDocument($cpfCnpj);

// Customer->address
        $boleto->customer->address->setStreet($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/street")]);
        $boleto->customer->address->setNumber($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/number")]);
        $boleto->customer->address->setComplement($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/complement")]);
        $boleto->customer->address->setNeighborhood($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/district")]);

        $boleto->customer->address->setCity($billingAddress->getCity());
        $boleto->customer->address->setState($this->codigoUF($billingAddress->getRegion()));
        $boleto->customer->address->setCountry("BR");
        $boleto->customer->address->setZipcode($billingAddress->getPostcode());

// Customer->phone
        $phone_number = filter_var($billingAddress->getTelephone(), FILTER_SANITIZE_NUMBER_INT);
        $boleto->customer->phone->setCountryCode("55");
        $boleto->customer->phone->setAreaCode(substr($phone_number, 0, 2));
        $boleto->customer->phone->setNumber(substr($phone_number, 2));
        $boleto->customer->phone->setType(\AditumPayments\ApiSDK\Enum\PhoneType::MOBILE);

        foreach ($this->getItems($order) as $item) {
            $boleto->products->add(
                $item['name'],
                $item['sku'],
                $item['value'],
                $item['qty']
            );
        }

// Transactions
        $quote = $this->quoteRepository->get($order->getQuoteId());
        $grandTotal = $this->getCentsValue($quote->getGrandTotal());
        $boleto->transactions->setAmount($grandTotal);
        $boleto->transactions->setInstructions("Senhor caixa não receber após o vencimento.");

// Transactions->fine (opcional)
        if ($this->scopeConfig->getValue("payment/aditum_boleto/fine_days")) {
            $boleto->transactions->fine
                ->setStartDate($this->scopeConfig->getValue("payment/aditum_boleto/fine_days"));
        }
        if ($this->scopeConfig->getValue("payment/aditum_boleto/fine_days")
            && $this->scopeConfig->getValue("payment/aditum_boleto/fine_amount")) {
            $boleto->transactions->fine
                ->setAmount($this->scopeConfig->getValue("payment/aditum_boleto/fine_amount"));
        }
        if ($this->scopeConfig->getValue("payment/aditum_boleto/fine_days")
            && $this->scopeConfig->getValue("payment/aditum_boleto/fine_percent")) {
            $boleto->transactions->fine->setInterest(10);
        }

// Transactions->discount (opcional)
//        $boleto->transactions->discount->setType(AditumPayments\ApiSDK\Enum\DiscountType::FIXED);
//        $boleto->transactions->discount->setAmount(200);
//        $boleto->transactions->discount->setDeadline("1");

        $result = $gateway->charge($boleto);

        $this->logger->info("External Apitum API Return: ".json_encode($result));
        return $result;
    }

    /**
     * Get value in cents from float
     *
     * @param float $originalValue
     * @return int
     */
    public function getCentsValue(float $originalValue): int
    {
        return (int)number_format($originalValue, 2, '', '');
    }
    public function createOrderCc(Order $order, $info, $payment, $preAuth = 0)
    {
        return $this->extCreateOrderCc($order, $info, $payment, $preAuth);
    }

    public function extCreateOrderCc(Order $order, $info, $payment, $preAuth = 0)
    {
        \AditumPayments\ApiSDK\Configuration::initialize();
        \AditumPayments\ApiSDK\Configuration::setUrl($this->getApiUrl());
        \AditumPayments\ApiSDK\Configuration::setCnpj($this->getClientId());
        \AditumPayments\ApiSDK\Configuration::setMerchantToken($this->getClientSecret());
        \AditumPayments\ApiSDK\Configuration::login();
        \AditumPayments\ApiSDK\Configuration::setlog(false);

        $gateway = new \AditumPayments\ApiSDK\Gateway;
        $authorization = new \AditumPayments\ApiSDK\Domains\Authorization;
//        if($preAuth){
//            unset($authorization);
//            $authorization = new \AditumPayments\ApiSDK\Domains\PreAuthorization;
//        }
        $quote = $this->checkoutSession->getQuote();
        $billingAddress = $quote->getBillingAddress();

        $this->logger->info("Card CCDC Type: ".$payment->getAdditionalInformation('cc_dc_choice'));
        if ($payment->getAdditionalInformation('cc_dc_choice')=="dc") {
            $authorization->transactions->setPaymentType(\AditumPayments\ApiSDK\Enum\PaymentType::DEBIT);
        } else {
            $authorization->transactions->setPaymentType(\AditumPayments\ApiSDK\Enum\PaymentType::CREDIT);
        }
        $authorization->setMerchantChargeId($order->getIncrementId());

        $authorization->setSessionId($payment->getAdditionalInformation('antifraud_token'));

        $authorization->customer->setName($order->getBillingAddress()->getName());
        $authorization->customer->setEmail($quote->getCustomerEmail());

        $cpfCnpj = $payment->getAdditionalInformation('document');
        $cpfCnpj = filter_var($cpfCnpj, FILTER_SANITIZE_NUMBER_INT);

        $authorization->customer->setDocumentType(\AditumPayments\ApiSDK\Enum\DocumentType::CPF);
        if (strlen($cpfCnpj)==14) {
            $authorization->customer->setDocumentType(\AditumPayments\ApiSDK\Enum\DocumentType::CNPJ);
        }
        $authorization->customer->setDocument($cpfCnpj);

        $authorization->customer->phone->setCountryCode("55");
        $phone_number = filter_var($billingAddress->getTelephone(), FILTER_SANITIZE_NUMBER_INT);
        $authorization->customer->phone->setAreaCode(substr($phone_number, 0, 2));
        $authorization->customer->phone->setNumber(substr($phone_number, 2));
        $authorization->customer->phone->setType(\AditumPayments\ApiSDK\Enum\PhoneType::MOBILE);
        $authorization->customer->address->setStreet($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/street")]);
        $authorization->customer->address->setNumber($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/number")]);
        $authorization->customer->address->setComplement($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/complement")]);
        $authorization->customer->address->setNeighborhood($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/district")]);
        $authorization->customer->address->setCity($billingAddress->getCity());
        $authorization->customer->address->setState($this->codigoUF($billingAddress->getRegion()));
        $authorization->customer->address->setCountry("BR");
        $authorization->customer->address->setZipcode($billingAddress->getPostcode());
        $authorization->transactions->card
            ->setCardNumber(preg_replace('/[\-\s]+/', '', $info->getCcNumber()));
        $authorization->transactions->card->setCVV($payment->getAdditionalInformation('cc_cid'));
        $authorization->transactions->card->setCardholderName($payment->getAdditionalInformation('fullname'));
        $authorization->transactions->card->setExpirationMonth($payment->getAdditionalInformation('cc_exp_month'));
        $authorization->transactions->card->setExpirationYear($payment->getAdditionalInformation('cc_exp_year'));
        $authorization->transactions->card->setCardholderDocument($cpfCnpj);

        $authorization->transactions->card->billingAddress->setStreet($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/street")]);
        $authorization->transactions->card->billingAddress->setNumber($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/number")]);
        $authorization->transactions->card->billingAddress->setComplement($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/complement")]);
        $authorization->transactions->card->billingAddress->setNeighborhood($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/district")]);
        $authorization->transactions->card->billingAddress->setCity($billingAddress->getCity());
        $authorization->transactions->card->billingAddress->setState($this->codigoUF($billingAddress->getRegion()));
        $authorization->transactions->card->billingAddress->setCountry("BR");
        $authorization->transactions->card->billingAddress->setZipcode($billingAddress->getPostcode());
        foreach ($this->getItems($order) as $item) {
            $authorization->products->add(
                $item['name'],
                $item['sku'],
                $item['value'],
                $item['qty']
            );
        }

        if ($payment->getAdditionalInformation('cc_dc_choice')!="dc") {
            $authorization->transactions->setInstallmentNumber($payment->getAdditionalInformation('installments'));
            $authorization->transactions->setInstallmentType(\AditumPayments\ApiSDK\Enum\InstallmentType::NONE);
            if ($payment->getAdditionalInformation('installments')>1) {
                $authorization->transactions->setInstallmentType(\AditumPayments\ApiSDK\Enum\InstallmentType::MERCHANT);
            }
        }
        $quote = $this->quoteRepository->get($order->getQuoteId());
        $grandTotal = $this->getCentsValue($quote->getGrandTotal());
        $authorization->transactions->setAmount($grandTotal);

        $result = $gateway->charge($authorization);

        $this->logger->info("External Apitum API Return: ".json_encode($result));
        return $result;
    }
    public function getApiUrl()
    {
        if (!$this->scopeConfig->getValue(
            'payment/aditum/environment',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return \AditumPayments\ApiSDK\Configuration::DEV_URL;
        }
        return \AditumPayments\ApiSDK\Configuration::PROD_URL;
    }
    public function logError($action, $url, $output, $input = "")
    {
        $this->logger->error("Aditum Request error: ".$action." - ".$url." - ".$input." - ".$output);
        return false;
    }
    public function getClientId()
    {
        return $this->scopeConfig->getValue(
            'payment/aditum/client_id',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    public function getClientSecret()
    {
        return $this->scopeConfig->getValue(
            'payment/aditum/client_secret',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    public function codigoUF($txt_uf)
    {
        $array_ufs = ["Rondônia" => "RO",
            "Acre" => "AC",
            "Amazonas" => "AM",
            "Roraima" => "RR",
            "Pará" => "PA",
            "Amapá" => "AP",
            "Tocantins" => "TO",
            "Maranhão" => "MA",
            "Piauí" => "PI",
            "Ceará" => "CE",
            "Rio Grande do Norte" => "RN",
            "Paraíba" => "PB",
            "Pernambuco" => "PE",
            "Alagoas" => "AL",
            "Sergipe" => "SE",
            "Bahia" => "BA",
            "Minas Gerais" => "MG",
            "Espírito Santo" => "ES",
            "Rio de Janeiro" => "RJ",
            "São Paulo" => "SP",
            "Paraná" => "PR",
            "Santa Catarina" => "SC",
            "Rio Grande do Sul (*)" => "RS",
            "Mato Grosso do Sul" => "MS",
            "Mato Grosso" => "MT",
            "Goiás" => "GO",
            "Distrito Federal" => "DF"];
        $uf = "RJ";
        foreach ($array_ufs as $key => $value) {
            if ($key == $txt_uf) {
                $uf = $value;
                break;
            }
        }
        return $uf;
    }
    public function getDocumentTypeId($type = "cpf")
    {
        if ($type=="cpf") {
            return 1;
        }
        if ($type=="cnpj") {
            return 2;
        }
        return 1;
    }
    public function getError($arrayReturn)
    {
        if (!isset($arrayReturn['charge']['transactions'][0]['errorMessage'])) {
            return "";
        }
        return $arrayReturn['charge']['transactions'][0]['errorMessage'];
    }
    public function getBoletoUrl($result)
    {
        $env = $this->scopeConfig->getValue(
            'payment/aditum/environment',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $url = \AditumPayments\ApiSDK\Configuration::DEV_URL;
        if ($env) {
            $url = \AditumPayments\ApiSDK\Configuration::PROD_URL;
        }
        $url = str_replace("/v2/", "", $url);
        $bankSlipUrl = str_replace("\\", "", $result['charge']['transactions'][0]['bankSlipUrl']);
        return $url . $bankSlipUrl;
    }

    public function createOrderPix($order, $payment)
    {
        $this->logger->info('ADITUM PIX create order started');
        /** @var $order \Magento\Sales\Api\Data\OrderInterface */
        \AditumPayments\ApiSDK\Configuration::initialize();
        \AditumPayments\ApiSDK\Configuration::setUrl($this->getApiUrl());
        \AditumPayments\ApiSDK\Configuration::setCnpj($this->getClientId());
        \AditumPayments\ApiSDK\Configuration::setMerchantToken($this->getClientSecret());
        \AditumPayments\ApiSDK\Configuration::setlog(false);
        \AditumPayments\ApiSDK\Configuration::login();
        $gateway = new \AditumPayments\ApiSDK\Gateway;
        $pix = new \AditumPayments\ApiSDK\Domains\Pix;

        $pix->setMerchantChargeId("");

// Customer
        $pix->customer->setId($order->getIncrementId());
        $pix->customer->setName($payment->getAdditionalInformation('pixfullname'));
        $pix->customer->setName("Gustavo Ulyssea");


        $pix->customer->setEmail($order->getCustomerEmail());
        $cpfCnpj = $payment->getAdditionalInformation('pixdocument');
        $cpfCnpj = filter_var($cpfCnpj, FILTER_SANITIZE_NUMBER_INT);
        $pix->customer->setDocumentType(\AditumPayments\ApiSDK\Enum\DocumentType::CPF);
        if (strlen($cpfCnpj) == 14) {
            $pix->customer->setDocumentType(\AditumPayments\ApiSDK\Enum\DocumentType::CNPJ);
        }

        $pix->customer->setDocument($cpfCnpj);
// Customer->address
        $quote = $this->checkoutSession->getQuote();
        $billingAddress = $quote->getBillingAddress();


        $pix->customer->address->setStreet($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/street")]);
        $pix->customer->address->setNumber($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/number")]);
        $pix->customer->address->setComplement($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/complement")]);
        $pix->customer->address->setNeighborhood($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/district")]);
        $pix->customer->address->setCity($billingAddress->getCity());
        $pix->customer->address->setState($this->codigoUF($billingAddress->getRegion()));
        $pix->customer->address->setCountry("BR");
        $pix->customer->address->setZipcode($billingAddress->getPostcode());
        $pix->customer->address->setComplement("");

// Customer->phone
        $phone_number = filter_var($billingAddress->getTelephone(), FILTER_SANITIZE_NUMBER_INT);
        $pix->customer->phone->setCountryCode("55");
        $pix->customer->phone->setAreaCode(substr($phone_number, 0, 2));
        $pix->customer->phone->setNumber(substr($phone_number, 2));
        $pix->customer->phone->setType(\AditumPayments\ApiSDK\Enum\PhoneType::MOBILE);

        foreach ($this->getItems($order) as $item) {
            $pix->products->add(
                $item['name'],
                $item['sku'],
                $item['value'],
                $item['qty']
            );
        }
// Transactions
        $quote = $this->quoteRepository->get($order->getQuoteId());
        $grandTotal = $this->getCentsValue($quote->getGrandTotal());
        $pix->transactions->setAmount($grandTotal);
        $result = $gateway->charge($pix);
        $this->logger->info(json_encode($result));
        return $result;
    }

    /**
     * Get items from best possibility
     *
     * @param OrderInterface $order
     * @return array
     * @throws NoSuchEntityException
     */
    public function getItems(OrderInterface $order): array
    {
        if (!$items = $this->getItemsDiscountAlreadyApplied($order)) {
            if (!$items = $this->getItemsAndApplyDiscount($order)) {
                if (!$items = $this->getItemsNormalizedShippingAlreadyAppliedDiscount($order)) {
                    $items = $this->getGeneralNormalizedItems($order);
                }
            }
        }
        return $items;
    }

    /**
     * Get items if we can trust that discount has already been applied
     *
     * @param OrderInterface $order
     * @return array|null
     */
    public function getItemsDiscountAlreadyApplied(OrderInterface $order): ?array
    {
        $grandTotal = 0;
        foreach ($order->getItems() as $item) {
            $items[] = [
                'name' => $item->getName(),
                'sku' => $item->getSku(),
                'value' => $this->getCentsValue($item->getPrice()),
                'qty' => $item->getQtyOrdered()
            ];
            $grandTotal += $this->getCentsValue($item->getPrice()) * $item->getQtyOrdered();
        }
        $items[] = [
            'name' => 'Envio',
            'sku' => 'Envio',
            'value' => $this->getCentsValue($order->getShippingAmount()),
            'qty' => 1
        ];
        $grandTotal += $this->getCentsValue($order->getShippingAmount());
        if ($this->getCentsValue($order->getGrandTotal()) == $grandTotal) {
            return $items;
        }
        return null;
    }

    /**
     * Get items and apply discount if subtotal and shipping values sum are OK
     *
     * @param OrderInterface $order
     * @return array|null
     */
    public function getItemsAndApplyDiscount(OrderInterface $order): ?array
    {
        $grandTotal = 0;
        foreach ($order->getItems() as $item) {
            $items[] = [
                'name' => $item->getName(),
                'sku' => $item->getSku(),
                'value' => $this->getCentsValue($item->getPrice() - $item->getDiscountAmount()),
                'qty' => $item->getQtyOrdered()
            ];
            $grandTotal += $this->getCentsValue($item->getPrice() - $item->getDiscountAmount())
                * $item->getQtyOrdered();
        }
        $items[] = [
            'name' => 'Envio',
            'sku' => 'Envio',
            'value' => $this->getCentsValue($order->getShippingAmount()),
            'qty' => 1
        ];
        $grandTotal += $this->getCentsValue($order->getShippingAmount());
        if ($this->getCentsValue($order->getGrandTotal()) == $grandTotal) {
            return $items;
        }
        return null;
    }

    /**
     * Try values without discount, if not apply discount and forcefully add shipping
     *
     * @param OrderInterface $order
     * @return array|null
     */
    public function getItemsNormalizedShippingAlreadyAppliedDiscount(OrderInterface $order): ?array
    {
        $subTotal = 0;
        $items = [];
        foreach ($order->getItems() as $item) {
            $items[] = [
                'name' => $item->getName(),
                'sku' => $item->getSku(),
                'value' => $this->getCentsValue($item->getPrice()),
                'qty' => $item->getQtyOrdered()
            ];
            $subTotal += $this->getCentsValue($item->getPrice()) * $item->getQtyOrdered();
        }

        if ($subTotal > $this->getCentsValue($order->getGrandTotal())) {
            $items = [];
            $subTotal = 0;
            foreach ($order->getItems() as $item) {
                $items[] = [
                    'name' => $item->getName(),
                    'sku' => $item->getSku(),
                    'value' => $this->getCentsValue($item->getPrice() - $item->getDiscountAmount()),
                    'qty' => $item->getQtyOrdered()
                ];
                $subTotal += $this->getCentsValue($item->getPrice() - $item->getDiscountAmount())
                    * $item->getQtyOrdered();
            }
            if ($subTotal > $this->getCentsValue($order->getGrandTotal())) {
                return null;
            }
        }
        if ($subTotal < $this->getCentsValue($order->getGrandTotal())) {
            $items[] = [
                'name' => 'Envio',
                'sku' => 'Envio',
                'value' => $this->getCentsValue($order->getGrandTotal()) - $subTotal,
                'qty' => 1
            ];
        }
        return $items;
    }

    /**
     * Divide grand total by total items and adds shipping if there is a difference
     *
     * @param OrderInterface $order
     * @return array
     * @throws NoSuchEntityException
     */
    public function getGeneralNormalizedItems(OrderInterface $order): array
    {
        $quote = $this->quoteRepository->get($order->getQuoteId());
        $grandTotal = $this->getCentsValue($quote->getGrandTotal());
        $totalItemsQty = 0;
        foreach ($order->getItems() as $item) {
            $totalItemsQty += $item->getQtyOrdered();
        }
        $unitValue = (int)floor($grandTotal / $totalItemsQty);
        $items = [];
        foreach ($order->getItems() as $item) {
            $items[] = [
                'name' => $item->getName(),
                'sku' => $item->getSku(),
                'value' => $unitValue,
                'qty' => $item->getQtyOrdered()
            ];
        }
        if ($grandTotal > $totalItemsQty * $unitValue) {
            $items[] = [
                'name' => 'Envio',
                'sku' => 'Envio',
                'value' => (int)($grandTotal - $totalItemsQty * $unitValue),
                'qty' => 1
            ];
        }
        return $items;
    }
}
