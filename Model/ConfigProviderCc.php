<?php
namespace AditumPayment\Magento2\Model;

use Magento\Framework\Locale\Bundle\DataBundle;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Escaper;
use Magento\Payment\Model\CcConfig;
use Magento\Framework\View\Asset\Source;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Customer\Model\Session;

class ConfigProviderCc extends \AditumPayment\Magento2\Model\ConfigProvider implements ConfigProviderInterface
{
    const YEARS_RANGE = 20;

    /**
     * @var string[]
     */
    protected $methodCodes = [
        'aditumcc'
    ];

    protected $_ccoptions = [
        'mastercard' => 'Mastercard',
        'visa' => 'Visa',
        'amex' => 'American Express',
        'diners' => 'Diners',
        'elo' => 'Elo',
        'hipercard' => 'Hipercard',
        'hiper' => 'HIPER'
    ];
    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];

    /**
     * @var Escaper
     */
    protected $escaper;

    /**
     * @var array
     */
    private $icons = [];

    /**
     * @var CcConfig
     */
    protected $ccConfig;
    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $localeResolver;
    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $_helper;
    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    protected $_priceCurrency;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var \Magento\Framework\View\Asset\Source
     */
    protected $assetSource;
    /**
     * @var \Magento\Framework\Pricing\Helper\Data
     */
    protected $_priceFiler;
    /**
     * @var Session
     */
    protected $_customerSession;

    public function __construct(
        PaymentHelper $paymentHelper,
        Escaper $escaper,
        CcConfig $ccConfig,
        Source $assetSource,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        Session $customerSession,
        \Magento\Checkout\Model\Session $_checkoutSession,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Pricing\Helper\Data $priceFilter,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->ccConfig = $ccConfig;
        $this->assetSource = $assetSource;
        $this->escaper = $escaper;
        $this->localeResolver = $localeResolver;
        $this->_date = $date;
        $this->_priceCurrency = $priceCurrency;
        $this->_customerSession = $customerSession;
        $this->_checkoutSession = $_checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->_priceFiler = $priceFilter;
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }
        parent::__construct($scopeConfig, $assetRepo, $storeManager);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [];
        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $config['payment'][$code]['ccavailabletypes'] = $this->getCcAvailableTypes();
                $config['payment'][$code]['cctypes'] = array_keys($this->getCcAvailableTypes());
                $config['payment'][$code]['years'] = $this->getYears();
                $config['payment'][$code]['months'] = $this->getMonths();
                $config['payment'][$code]['icons'] = $this->getIcons();
                $config['payment'][$code]['currency'] = $this->getCurrencyData();
                $config['payment'][$code]['type_interest'] = $this->typeInstallment();
                $config['payment'][$code]['info_interest'] = $this->getInfoParcelamentoJuros();
                $config['payment'][$code]['max_installment'] = $this->maxInstallment();
                $config['payment'][$code]['min_installment'] = $this->minInstallment();
                $config['payment'][$code]['publickey'] = $this->getPublicKey();
                $config['payment'][$code]['image_cvv'] = $this->getCvvImg();
                $config['payment'][$code]['get_document'] = $this->getUseDocument();
                $config['payment'][$code]['terms_url'] = $this->getTermsUrl();
                $config['payment'][$code]['terms_txt'] = $this->getTermsTxt();
                $config['payment'][$code]['singleicon'] = $this->getSingleIcon();
                $config['payment'][$code]['cc_dc_choice'] = "";
                $config['payment'][$code]['document'] = $this->getTaxVat();
                $config['payment'][$code]['antifraud_type'] = $this->getAntiFraudType();
                $config['payment'][$code]['antifraud_id'] = $this->getAntiFraudId();
                $config['payment'][$code]['static_url'] = $this->getStaticUrl();
            }
        }

        return $config;
    }
    /**
     * @return array
     */
    protected function getCcAvailableTypes()
    {
        return $this->_ccoptions;
    }

    public function getCvvImg()
    {
        $asset = $this->ccConfig
                    ->createAsset('AditumPayment_Magento2::images/cc/cvv.gif');
        return $asset->getUrl();
    }
    public function getSingleIcon()
    {
        $asset = $this->ccConfig
            ->createAsset('AditumPayment_Magento2::images/cc/bandeiras-checkout3.png');
        list($width, $height) = getimagesize($asset->getSourceFile());
        return [
            'url' => $asset->getUrl(),
            'width' => $width,
            'height' => $height
        ];
    }
    /**
     * @return array
     */
    public function getIcons()
    {
        if (!empty($this->icons)) {
            return $this->icons;
        }

        $types = $this->_ccoptions;
        foreach (array_keys($types) as $code) {

            if (!array_key_exists($code, $this->icons)) {
                $asset = $this->ccConfig
                    ->createAsset('AditumPayment_Magento2::images/cc/' . strtolower($code) . '.png');
                $placeholder = $this->assetSource->findSource($asset);
                if ($placeholder) {
                    list($width, $height) = getimagesize($asset->getSourceFile());
                    $this->icons[$code] = [
                        'url' => $asset->getUrl(),
                        'width' => $width,
                        'height' => $height
                    ];
                }

            }
        }
        return $this->icons;
    }

    /**
     * @return array
     */
    public function getMonths()
    {
        $data = [];
        $months = (new DataBundle())->get(
            $this->localeResolver->getLocale()
        )['calendar']['gregorian']['monthNames']['format']['wide'];
        foreach ($months as $key => $value) {
            $monthNum = ++$key < 10 ? '0' . $key : $key;
            $data[$key] = $monthNum;// . ' - ' . $value;
        }
        return $data;
    }

    /**
     * @return array
     */
    public function getYears()
    {
        $years = [];
        $first = (int)$this->_date->date('Y');
        for ($index = 0; $index <= self::YEARS_RANGE; $index++) {
            $year = $first + $index;
            $years[$year] = $year;
        }
        return $years;
    }

    /**
     * @return mixed
     */
    public function getUseDocument()
    {
        return $this->scopeConfig->getValue("payment/aditumcc/document/getdocument");
    }

    /**
     * @return array
     */
    public function getInfoParcelamentoJuros()
    {
        $maxInstallment = $this->maxInstallment();
        $juros = [];
        for ($i=0; $i<=$maxInstallment; $i++) {
            $juros[(string)$i] = 0;
        }
        return $juros;
    }

    /**
     * @return string
     */
    public function getTaxVat()
    {
        if ($this->_customerSession->isLoggedIn()) {
            return $this->_customerSession->getCustomer()->getTaxvat();
        } else {
            return "";
        }
    }

    /**
     * @return mixed
     */
    public function getCurrencyData()
    {
        $currencySymbol = $this->_priceCurrency
            ->getCurrency()->getCurrencySymbol();
        return $currencySymbol;
    }

    /**
     * @return string
     */
    public function typeInstallment()
    {
        $type = "percent";//$this->scopeConfig->getValue('payment/aditumcc/installment/type_interest');
        return $type;
    }
    public function getMinInstallmentValue()
    {
        return $this->scopeConfig->getValue("payment/aditumcc/document/min_installment_value");
    }

    /**
     * @return string
     */
    public function minInstallment()
    {
        return "1";
    }

    public function getTotalPrice()
    {
        return $this->_checkoutSession->getQuote()->getGrandTotal();
    }

    /**
     * @return mixed
     */
    public function maxInstallment()
    {
        $maxInstallmentConfig = (int) $this->scopeConfig->getValue('payment/aditumcc/installments');
        $minInstallmentValue = $this->scopeConfig->getValue("payment/aditumcc/min_installment_value");
        $maxInstallment = 1;
        for ($i=1; $i<=$maxInstallmentConfig; $i++) {
            if ($this->getTotalPrice()/$i < $minInstallmentValue) {
                break;
            }
            $maxInstallment = $i;
        }

        return $maxInstallment;
    }

    /**
     * @return string
     */
    public function getEnvironmentMode()
    {
        return "";
    }

    /**
     * @return mixed
     */
    public function getPublicKey()
    {
        $_environment = $this->getEnvironmentMode();
        $publickey = $this->scopeConfig->getValue('payment/moipbase/publickey_'.$_environment);
        return $publickey;
    }
}
